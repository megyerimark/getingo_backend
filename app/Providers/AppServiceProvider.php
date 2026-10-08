<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('public-api', function (Request $request) {
            return Limit::perMinute(60)->by('public:'.$request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(20)->by('search:'.$request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));
            $identity = hash('sha256', $email.'|'.$request->ip());

            return [
                Limit::perMinute(5)->by('login:identity:'.$identity),
                Limit::perHour(30)->by('login:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return [
                Limit::perMinute(3)->by('register:minute:'.$request->ip()),
                Limit::perHour(10)->by('register:hour:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));
            $identity = hash('sha256', $email.'|'.$request->ip());

            return [
                Limit::perMinute(5)->by('password-reset:identity:'.$identity),
                Limit::perHour(20)->by('password-reset:ip:'.$request->ip()),
            ];
        });


        RateLimiter::for('code-runner', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();
            return [
                Limit::perMinute(12)->by('code-runner:minute:'.$key),
                Limit::perHour(60)->by('code-runner:hour:'.$key),
                Limit::perDay(150)->by('code-runner:day:'.$key),
            ];
        });

        RateLimiter::for('user-api', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(120)->by('user:'.$key);
        });

        RateLimiter::for('quiz', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(15)->by('quiz:'.$key);
        });

        RateLimiter::for('admin-api', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(120)->by('admin:'.$key);
        });

        RateLimiter::for('gdpr', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perHour(3)->by('gdpr:'.$key);
        });
    }
}
