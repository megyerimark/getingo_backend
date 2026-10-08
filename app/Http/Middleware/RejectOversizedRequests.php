<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectOversizedRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $maxBytes = (int) config('security.max_request_bytes', 1048576);
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);

        if ($contentLength > $maxBytes) {
            return response()->json([
                'message' => 'A kérés túl nagy.',
            ], 413);
        }

        return $next($request);
    }
}
