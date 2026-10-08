<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();
        $originalEmail = Str::lower(trim((string) $user->email));

        $request->merge([
            'email' => Str::lower(trim((string) $request->email)),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'string'],
        ]);

        $emailChanged = $originalEmail !== $validated['email'];

        if ($emailChanged && (! isset($validated['current_password']) || ! Hash::check($validated['current_password'], $user->password))) {
            throw ValidationException::withMessages([
                'current_password' => ['Az email cím módosításához add meg helyesen a jelenlegi jelszavadat.'],
            ]);
        }

        $user->name = trim($validated['name']);
        $user->email = $validated['email'];

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => $emailChanged
                ? 'A profil frissült. Az új email cím megerősítéséhez elküldtük a linket.'
                : 'A profil frissítése sikerült.',
            'user' => $user->fresh(),
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'A jelenlegi jelszó hibás.',
            ], 422);
        }

        if (Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Az új jelszó legyen eltérő a jelenlegi jelszótól.',
            ], 422);
        }

        $user->password = $validated['password'];
        $user->setRememberToken(Str::random(60));
        $user->save();

        if ($request->hasSession()) {
            $currentSessionId = $request->session()->getId();

            if (config('session.driver') === 'database') {
                DB::table((string) config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $currentSessionId)
                    ->delete();
            }

            $request->session()->regenerate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'A jelszó módosítása sikerült. A többi aktív munkamenetet kijelentkeztettük.',
        ]);
    }
}
