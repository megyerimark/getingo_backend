<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\NoStorePrivateResponses;
use App\Http\Middleware\RejectOversizedRequests;
use App\Http\Middleware\RequireAdminReauth;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RejectOversizedRequests::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: [
        'stripe/*',
    ]);

        $trustedHosts = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) Env::get(
                'TRUSTED_HOSTS',
                '^localhost$,^127\\.0\\.0\\.1$,^(.+\\.)?getingo\\.hu$'
            ))
        )));

        $middleware->trustHosts(
            at: $trustedHosts,
            subdomains: false,
        );

        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) Env::get('TRUSTED_PROXIES', ''))
        )));

        if ($trustedProxies !== []) {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        $middleware->alias([
            'admin' => IsAdmin::class,
            'active' => EnsureUserIsActive::class,
            'no-store' => NoStorePrivateResponses::class,
            'verified' => EnsureEmailIsVerified::class,
            'admin.reauth' => RequireAdminReauth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*') ||
                $request->expectsJson(),
        );
    })
    ->create();
