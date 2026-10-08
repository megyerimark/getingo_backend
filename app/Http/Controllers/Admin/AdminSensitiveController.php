<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSensitiveController extends Controller
{
    public function unlock(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string', 'max:1024']]);
        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'A megadott admin jelszó hibás.'], 422);
        }

        $token = Str::random(64);
        Cache::put($this->key($user->id, $token), true, now()->addMinutes(10));

        return response()->json([
            'token' => $token,
            'expires_in' => 600,
            'message' => 'Az érzékeny admin terület 10 percre feloldva.',
        ]);
    }

    private function key(int $userId, string $token): string
    {
        return 'admin-reauth:'.$userId.':'.hash('sha256', $token);
    }
}
