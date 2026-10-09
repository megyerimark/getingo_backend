<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminBugReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BugReport::query()
            ->with('user:id,name,email')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->boolean('unread')) {
            $query->whereNull('seen_at');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json(
            $query->paginate(20)
        );
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => BugReport::whereNull('seen_at')->count(),
        ]);
    }

    public function show(BugReport $bugReport): JsonResponse
    {
        if ($bugReport->seen_at === null) {
            $bugReport->update([
                'seen_at' => now(),
            ]);
        }

        $bugReport->load('user:id,name,email');

        return response()->json([
            'report' => $bugReport,
        ]);
    }

    public function update(Request $request, BugReport $bugReport): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'new',
                    'in_progress',
                    'resolved',
                    'closed',
                ]),
            ],
        ]);

        $bugReport->update([
            'status' => $validated['status'],
            'seen_at' => $bugReport->seen_at ?? now(),
        ]);

        return response()->json([
            'message' => 'A hibajelentés státusza frissítve.',
            'report' => $bugReport->fresh(),
        ]);
    }

    public function markAllSeen(): JsonResponse
    {
        BugReport::whereNull('seen_at')
            ->update([
                'seen_at' => now(),
            ]);

        return response()->json([
            'message' => 'Minden hibajelentés megjelölve olvasottként.',
        ]);
    }

    public function screenshot(BugReport $bugReport)
    {
        abort_unless(
            $bugReport->screenshot
            && Storage::disk('public')->exists($bugReport->screenshot),
            404
        );

        return Storage::disk('public')
            ->response($bugReport->screenshot);
    }
}