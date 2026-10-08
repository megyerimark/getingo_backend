<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AuditLogger
{
    public static function record(
        Request $request,
        string $action,
        ?Model $target = null,
        array $metadata = []
    ): void {
        try {
            AdminAuditLog::create([
                'actor_user_id' => $request->user()?->id,
                'action' => $action,
                'target_type' => $target ? $target::class : null,
                'target_id' => $target?->getKey(),
                'metadata' => $metadata ?: null,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Admin audit log write failed.', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
