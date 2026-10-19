<?php

namespace App\Services\Audit;

use App\Models\ClubActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Throwable;

class ClubActivityLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        ?string $clubId,
        string $action,
        string $summary,
        string $description,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $meta = [],
        ?User $actor = null,
    ): void {
        if (! is_string($clubId) || $clubId === '') {
            return;
        }

        try {
            $user = $actor ?? Auth::user();
            $impersonatorId = session('impersonator_id');

            if ($impersonatorId) {
                $meta['impersonator_id'] = $impersonatorId;
                $impersonator = User::query()->find($impersonatorId);
                if ($impersonator) {
                    $meta['impersonator_name'] = $impersonator->name;
                }
            }

            ClubActivityLog::query()->create([
                'club_id' => $clubId,
                'user_id' => $user?->id,
                'action' => Str::limit($action, 64, ''),
                'summary' => Str::limit(trim($summary), 255, ''),
                'description' => trim($description),
                'subject_type' => $subjectType ? Str::limit($subjectType, 64, '') : null,
                'subject_id' => $subjectId !== null ? (string) $subjectId : null,
                'ip_address' => Request::ip(),
                'meta' => $meta === [] ? null : $meta,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
            Log::warning('Club activity log failed', [
                'club_id' => $clubId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
