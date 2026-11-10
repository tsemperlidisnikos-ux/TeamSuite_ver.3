<?php

namespace App\Services\Gdpr;

use App\Models\AmkaAccessLog;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Attendance;
use App\Models\GdprAuditLog;
use App\Models\ParentAthleteLink;
use App\Models\ProgressReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GdprSubjectExportService
{
    /**
     * @return array<string, mixed>
     */
    public function export(Athlete $athlete): array
    {
        $athlete->loadMissing(['classes:id,name']);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'athlete' => [
                'id' => $athlete->id,
                'first_name' => $athlete->first_name,
                'last_name' => $athlete->last_name,
                'email' => $athlete->email,
                'phone' => $athlete->phone,
                'birth_date' => optional($athlete->birth_date)?->format('Y-m-d'),
                'status' => $athlete->status,
                'sport' => $athlete->sport,
                'guardian_name' => $athlete->guardian_name,
                'guardian_phone' => $athlete->guardian_phone,
                'gdpr_consent' => $athlete->gdpr_consent,
                'health_card' => (bool) $athlete->health_card,
                'health_card_expires' => optional($athlete->health_card_expires)?->format('Y-m-d'),
                'amka_set' => filled($athlete->amka_encrypted),
                'classes' => $athlete->classes->pluck('name')->values()->all(),
                'meta' => $this->safeMeta($athlete->meta ?? []),
            ],
            'transactions' => AthleteTransaction::query()->withoutGlobalScopes()
                ->where('athlete_id', $athlete->id)
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(['id', 'type', 'amount', 'month', 'year', 'payment_method', 'receipt_number', 'comments', 'created_at'])
                ->map(fn (AthleteTransaction $tx) => [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'amount' => (float) $tx->amount,
                    'month' => $tx->month,
                    'year' => $tx->year,
                    'payment_method' => $tx->payment_method,
                    'receipt_number' => $tx->receipt_number,
                    'comments' => $tx->comments,
                    'created_at' => optional($tx->created_at)?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'attendance' => Attendance::query()->withoutGlobalScopes()
                ->where('athlete_id', $athlete->id)
                ->orderByDesc('attended_on')
                ->limit(500)
                ->get(['id', 'attended_on', 'status', 'meta'])
                ->map(fn (Attendance $row) => [
                    'id' => $row->id,
                    'attended_on' => optional($row->attended_on)?->format('Y-m-d'),
                    'status' => $row->status,
                    'meta' => $row->meta,
                ])
                ->values()
                ->all(),
            'parent_links' => ParentAthleteLink::query()->withoutGlobalScopes()
                ->where('athlete_id', $athlete->id)
                ->get(['id', 'parent_user_id', 'club_id', 'created_at'])
                ->map(fn (ParentAthleteLink $link) => [
                    'id' => $link->id,
                    'parent_user_id' => $link->parent_user_id,
                    'created_at' => optional($link->created_at)?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'progress_reports' => ProgressReport::query()->withoutGlobalScopes()
                ->where('athlete_id', $athlete->id)
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(['id', 'created_at'])
                ->map(fn (ProgressReport $report) => [
                    'id' => $report->id,
                    'created_at' => optional($report->created_at)?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'amka_access_logs' => AmkaAccessLog::query()->withoutGlobalScopes()
                ->where('athlete_id', $athlete->id)
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(['id', 'user_id', 'action', 'created_at'])
                ->map(fn (AmkaAccessLog $log) => [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'action' => $log->action,
                    'created_at' => optional($log->created_at)?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];

        GdprAuditLog::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $athlete->club_id,
            'user_id' => Auth::id(),
            'action' => 'dsar.export',
            'meta' => [
                'subject_athlete_id' => $athlete->id,
                'detail' => 'Exported subject data JSON',
            ],
        ]);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function safeMeta(array $meta): array
    {
        unset($meta['amka'], $meta['AMKA']);

        return $meta;
    }
}
