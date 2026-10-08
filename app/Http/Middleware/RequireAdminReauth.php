<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminReauth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) $request->header('X-Admin-Reauth'));
        $userId = $request->user()?->id;
        $key = $userId && $token !== ''
            ? 'admin-reauth:'.$userId.':'.hash('sha256', $token)
            : null;

        if (! $key || ! Cache::get($key)) {
            return response()->json([
                'message' => 'Az admin újrahitelesítés lejárt. Add meg újra a jelszavadat.',
            ], 423);
        }

        return $next($request);
    }
}
