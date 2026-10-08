<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Érvénytelen vagy lejárt megerősítő link.');
        }

        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Érvénytelen megerősítő link.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/');

        return redirect()->away($frontendUrl . '/email-verified');
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Az email cím már meg van erősítve.',
                'verified' => true,
            ]);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::error('Verification email resend failed.', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A levélküldő szolgáltatás átmenetileg nem elérhető. Próbáld újra később.',
                'verified' => false,
            ], 503);
        }

        return response()->json([
            'message' => 'Új megerősítő emailt küldtünk.',
            'verified' => false,
        ]);
    }
}
