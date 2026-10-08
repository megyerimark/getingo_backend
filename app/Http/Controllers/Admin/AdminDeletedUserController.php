<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeletedUserRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminDeletedUserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = min(max($request->integer('per_page', 50), 1), 100);

        return response()->json(
            DeletedUserRecord::query()
                ->with('deletedBy:id,name,email')
                ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")))
                ->latest('deleted_at')
                ->paginate($perPage)
        );
    }

    public function evidence(DeletedUserRecord $deletedUser)
    {
        abort_unless($deletedUser->evidence_path, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($deletedUser->evidence_path), 404);

        return $disk->download(
            $deletedUser->evidence_path,
            $deletedUser->evidence_original_name ?: 'bizonyitek'
        );
    }
}
