<?php

namespace App\Http\Controllers;

use App\Models\BugReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BugReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],

            'type' => [
                'required',
                Rule::in([
                    'ui',
                    'function',
                    'performance',
                    'other',
                ]),
            ],

            'priority' => [
                'required',
                Rule::in([
                    'low',
                    'medium',
                    'high',
                ]),
            ],

            'page_url' => ['nullable', 'string', 'max:2000'],
            'browser' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:255'],

            'screenshot' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        $screenshotPath = null;

        if ($request->hasFile('screenshot')) {
            $screenshotPath = $request
                ->file('screenshot')
                ->store('bug-reports', 'public');
        }

        $report = BugReport::create([
            'user_id' => $request->user()?->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'type' => $validated['type'],
            'priority' => $validated['priority'],
            'page_url' => $validated['page_url'] ?? null,
            'browser' => $validated['browser'] ?? null,
            'platform' => $validated['platform'] ?? null,
            'screenshot' => $screenshotPath,
            'status' => 'new',
        ]);

        return response()->json([
            'message' => 'A hibajelentést sikeresen elküldted.',
            'report' => [
                'id' => $report->id,
                'status' => $report->status,
                'created_at' => $report->created_at,
            ],
        ], 201);
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