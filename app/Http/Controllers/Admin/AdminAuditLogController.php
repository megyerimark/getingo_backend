<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $this->validatedFilters($request);
        return response()->json($this->query($validated)->paginate($validated['per_page'] ?? 50));
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $this->validatedFilters($request);
        $rows = $this->query($validated)->limit(10000)->get();
        $filename = 'getingo-audit-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Dátum', 'Admin', 'Admin email', 'Művelet', 'Cél típus', 'Cél ID', 'IP', 'Metaadat'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row->created_at)->format('Y-m-d H:i:s'),
                    $row->actor?->name,
                    $row->actor?->email,
                    $row->action,
                    $row->target_type,
                    $row->target_id,
                    $row->ip_address,
                    json_encode($row->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    private function query(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        return AdminAuditLog::query()
            ->with('actor:id,name,email')
            ->when(! empty($filters['action']), fn ($q) => $q->where('action', $filters['action']))
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('action', 'like', "%{$search}%")
                    ->orWhere('target_type', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('actor', fn ($actor) => $actor
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->latest();
    }
}
