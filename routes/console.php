<?php

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('getingo:create-admin {email?} {--name=Administrator} {--promote}', function () {
    $email = strtolower(trim((string) ($this->argument('email') ?: $this->ask('Admin email címe'))));
    $name = trim((string) $this->option('name'));

    $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
    if ($existing) {
        if (! $this->option('promote')) {
            $this->error('Ez az email már létezik. Meglévő felhasználó adminná tételéhez használd a --promote kapcsolót.');
            return 1;
        }

        $existing->forceFill([
            'role' => 'admin',
            'is_banned' => false,
            'email_verified_at' => $existing->email_verified_at ?: now(),
        ])->save();

        $this->info("Meglévő felhasználó adminná téve: {$existing->email}");
        return 0;
    }

    $password = $this->secret('Adj meg egy erős admin jelszót');
    $confirmation = $this->secret('Jelszó újra');

    $validator = Validator::make([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $confirmation,
    ], [
        'name' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
        'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }
        return 1;
    }

    $user = User::create([
        'name' => strip_tags($name),
        'email' => $email,
        'password' => $password,
    ]);

    $user->forceFill([
        'role' => 'admin',
        'is_banned' => false,
        'email_verified_at' => now(),
    ])->save();

    $this->info("Admin létrehozva és email-verifikálva: {$user->email}");
    return 0;
})->purpose('Biztonságosan létrehoz vagy --promote esetén adminná tesz egy felhasználót.');

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::call(function (): void {
    AdminAuditLog::where(
        'created_at',
        '<',
        now()->subDays((int) config('security.audit_log_retention_days', 90))
    )->delete();
})->daily();
