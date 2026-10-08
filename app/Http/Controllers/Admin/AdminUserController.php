<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeletedUserRecord;
use App\Models\User;
use App\Notifications\AccountDeletedNotification;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use Throwable;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 50), 1), 100);
        $search = trim((string) $request->query('search', ''));

        return response()->json(
            User::query()
                ->select('id', 'name', 'email', 'role', 'is_banned', 'xp_points', 'current_streak', 'created_at')
                ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
                ->orderByDesc('created_at')
                ->paginate($perPage)
        );
    }

    public function updateRole(Request $request, int $id)
    {
        $validated = $request->validate(['role' => ['required', 'in:admin,student']]);
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'A saját szerepkörödet nem módosíthatod.'], 403);
        }

        $oldRole = $user->role;
        $user->role = $validated['role'];
        $user->save();
        AuditLogger::record($request, 'user.role.updated', $user, ['old_role' => $oldRole, 'new_role' => $user->role]);

        return response()->json([
            'message' => 'Szerepkör sikeresen frissítve!',
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_banned']),
        ]);
    }

    public function toggleBan(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Saját magadat nem tilthatod ki!'], 403);
        }

        $user->is_banned = ! $user->is_banned;
        $user->save();

        if ($user->is_banned) $this->revokeSessions($user);
        AuditLogger::record($request, 'user.ban.toggled', $user, ['is_banned' => $user->is_banned]);

        return response()->json([
            'message' => $user->is_banned ? 'Felhasználó kitiltva!' : 'Felhasználó visszaengedve!',
            'is_banned' => $user->is_banned,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:1024'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'evidence' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf,txt,eml'],
        ]);

        $admin = $request->user();
        if (! Hash::check($validated['password'], $admin->password)) {
            return response()->json(['message' => 'A megadott admin jelszó hibás.'], 422);
        }

        $user = User::findOrFail($id);
        if ($user->id === $admin->id) {
            return response()->json(['message' => 'Saját admin fiókodat ezen a felületen nem törölheted.'], 403);
        }
        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return response()->json(['message' => 'Az utolsó adminisztrátori fiók nem törölhető.'], 409);
        }

        if ($user->stripe_subscription_id && ! in_array($user->subscription_status, ['canceled', 'incomplete_expired'], true)) {
            $secret = config('services.stripe.secret');
            if (! $secret) {
                return response()->json(['message' => 'Az aktív előfizetés miatt a törléshez előbb konfigurálni kell a Stripe kapcsolatot.'], 503);
            }
            try {
                (new StripeClient($secret))->subscriptions->cancel($user->stripe_subscription_id, []);
            } catch (Throwable $exception) {
                Log::warning('Admin account deletion Stripe cancellation failed.', ['user_id' => $user->id, 'message' => $exception->getMessage()]);
                return response()->json(['message' => 'Az előfizetés lemondása nem sikerült, ezért a fiókot nem töröltük.'], 502);
            }
        }

        $evidencePath = null;
        $evidenceName = null;
        if ($request->hasFile('evidence')) {
            $file = $request->file('evidence');
            $evidenceName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $evidencePath = $file->storeAs(
                'admin-deletion-evidence/'.now()->format('Y/m'),
                Str::uuid().'.'.$extension,
                'local'
            );
        }

        $snapshot = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role];

        try {
            $record = DB::transaction(function () use ($request, $admin, $user, $validated, $evidencePath, $evidenceName, $snapshot) {
                $record = DeletedUserRecord::create([
                    'original_user_id' => $snapshot['id'],
                    'name' => $snapshot['name'],
                    'email' => $snapshot['email'],
                    'role' => $snapshot['role'],
                    'reason' => trim($validated['reason']),
                    'evidence_path' => $evidencePath,
                    'evidence_original_name' => $evidenceName,
                    'deleted_by_user_id' => $admin->id,
                    'deleted_at' => now(),
                ]);

                AuditLogger::record($request, 'user.deleted', $user, [
                    'deleted_user_record_id' => $record->id,
                    'reason' => trim($validated['reason']),
                    'evidence_original_name' => $evidenceName,
                ]);

                $this->revokeSessions($user);
                if (Schema::hasTable('password_reset_tokens')) {
                    DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                }

                // subscription_payments szándékosan megmarad; a nullable FK nullázódik.
                $user->delete();
                return $record;
            });
        } catch (Throwable $exception) {
            if ($evidencePath) Storage::disk('local')->delete($evidencePath);
            throw $exception;
        }

        $emailSent = true;
        try {
            Notification::route('mail', $snapshot['email'])
                ->notify(new AccountDeletedNotification($snapshot['name']));
        } catch (Throwable $exception) {
            $emailSent = false;
            Log::error('Deleted account confirmation email failed.', [
                'deleted_user_record_id' => $record->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $emailSent
                ? 'A felhasználó törölve, a törlés naplózva, a visszaigazoló email elküldve.'
                : 'A felhasználó törölve és a törlés naplózva, de a visszaigazoló email küldése nem sikerült.',
            'email_sent' => $emailSent,
            'record_id' => $record->id,
        ]);
    }

    private function revokeSessions(User $user): void
    {
        $user->tokens()->delete();
        if (Schema::hasTable((string) config('session.table', 'sessions'))) {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
