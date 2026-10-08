<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function forgot(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->email)),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Password::broker()->sendResetLink([
            'email' => $validated['email'],
        ]);

        return response()->json([
            'message' => 'Ha ehhez az email címhez tartozik Getingo-fiók, elküldtük a jelszó-visszaállító linket.',
        ]);
    }

    public function reset(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->email)),
        ]);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(12)->mixedCase()->numbers(),
            ],
        ]);

        $status = Password::broker()->reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                if (config('session.driver') === 'database') {
                    DB::table((string) config('session.table', 'sessions'))
                        ->where('user_id', $user->id)
                        ->delete();
                }

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['A jelszó-visszaállító link hibás vagy lejárt. Kérj új linket.'],
            ]);
        }

        return response()->json([
            'message' => 'A jelszavad sikeresen megváltozott. Most már bejelentkezhetsz az új jelszóval.',
        ]);
    }
}
