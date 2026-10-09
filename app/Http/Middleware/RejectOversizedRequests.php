<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectOversizedRequests
{
public function handle(Request $request, Closure $next): Response
    {
        $maxBytes = 8 * 1024 * 1024;

        $contentLength = (int) $request->server(
            'CONTENT_LENGTH',
            0
        );

        if ($contentLength > $maxBytes) {
            return response()->json([
                'message' => 'A kérés túl nagy. Maximum 5 MB-os képernyőkép tölthető fel.',
            ], 413);
        }

        return $next($request);
    }
}
