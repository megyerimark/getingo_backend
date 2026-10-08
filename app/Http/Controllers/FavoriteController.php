<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
        ]);

        $user = $request->user();

        $result = DB::transaction(function () use ($user, $validated): array {
            $favorite = Favorite::where('user_id', $user->id)
                ->where('lesson_id', $validated['lesson_id'])
                ->first();

            if ($favorite) {
                $favorite->delete();

                return [
                    'message' => 'Eltávolítva a kedvencek közül!',
                    'is_favorite' => false,
                ];
            }

            Favorite::create([
                'user_id' => $user->id,
                'lesson_id' => $validated['lesson_id'],
            ]);

            return [
                'message' => 'Hozzáadva a kedvencekhez!',
                'is_favorite' => true,
            ];
        });

        return response()->json($result);
    }
}
