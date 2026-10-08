<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->email)),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers(),
            ],
            'privacy_accepted' => ['required', 'accepted'],
        ], [
            'privacy_accepted.accepted' => 'A regisztrációhoz el kell olvasnod és tudomásul kell venned az Adatkezelési tájékoztatót.',
            'privacy_accepted.required' => 'A regisztrációhoz el kell olvasnod és tudomásul kell venned az Adatkezelési tájékoztatót.',
        ]);

        // Ne adjunk részletes unique-validációs hibát, amely közvetlenül felfedi,
        // hogy egy email cím már regisztrálva van. A publikus válasz szándékosan általános.
        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => 'A regisztráció ezekkel az adatokkal nem fejezhető be. Ellenőrizd az adatokat, vagy ha már van fiókod, használd a bejelentkezést / jelszó-visszaállítást.',
            ], 422);
        }

        try {
            $user = User::create([
                'name' => trim($validated['name']),
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'privacy_accepted_at' => now(),
                'privacy_policy_version' => config('privacy.version'),
            ]);
        } catch (QueryException $exception) {
            // A DB unique index a párhuzamos regisztrációs versenyhelyzetet is lezárja.
            // Ilyenkor ugyanazt az általános választ adjuk, mint a normál duplicate ellenőrzésnél.
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                return response()->json([
                    'message' => 'A regisztráció ezekkel az adatokkal nem fejezhető be. Ellenőrizd az adatokat, vagy ha már van fiókod, használd a bejelentkezést / jelszó-visszaállítást.',
                ], 422);
            }

            throw $exception;
        }

        $verificationEmailSent = true;
        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            $verificationEmailSent = false;
            Log::error('Verification email sending failed after registration.', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }

        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => $verificationEmailSent
                ? 'Sikeres regisztráció! Küldtünk egy megerősítő emailt.'
                : 'A fiók létrejött, de a megerősítő emailt most nem tudtuk elküldeni. A megerősítő oldalon kérj új levelet.',
            'user' => $user->fresh(),
            'verification_email_sent' => $verificationEmailSent,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->email)),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Hibás email cím vagy jelszó.',
            ], 401);
        }

        if ($user->is_banned) {
            return response()->json([
                'message' => 'A felhasználói fiók le van tiltva.',
            ], 403);
        }

        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Sikeres bejelentkezés!',
            'user' => $user->fresh(),
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->email)),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Ugyanaz a publikus válasz létező és nem létező címre is, így nem lehet fiókokat felderíteni.
        try {
            $status = PasswordBroker::sendResetLink(['email' => $validated['email']]);
        } catch (Throwable $exception) {
            Log::error('Password reset email sending failed.', [
                'email_hash' => hash('sha256', $validated['email']),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A levélküldő szolgáltatás átmenetileg nem elérhető. Próbáld újra később.',
            ], 503);
        }

        if (! in_array($status, [PasswordBroker::RESET_LINK_SENT, PasswordBroker::INVALID_USER], true)) {
            return response()->json([
                'message' => 'A jelszó-visszaállítás most nem indítható el. Próbáld újra később.',
            ], 503);
        }

        return response()->json([
            'message' => 'Ha a megadott email címhez tartozik Getingo fiók, elküldtük a jelszó-visszaállító linket.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->email)),
        ]);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers(),
            ],
        ]);

        $status = PasswordBroker::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Egy jelszó-visszaállítás minden korábbi hozzáférést érvénytelenít.
                $user->tokens()->delete();
                if (Schema::hasTable('sessions')) {
                    DB::table((string) config('session.table', 'sessions'))
                        ->where('user_id', $user->id)
                        ->delete();
                }
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'A jelszavad sikeresen megváltozott. Most már bejelentkezhetsz az új jelszóval.',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->fresh(),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Sikeres kijelentkezés!',
        ]);
    }
}
