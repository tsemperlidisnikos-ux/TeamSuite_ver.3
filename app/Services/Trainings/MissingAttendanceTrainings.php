<?php

namespace App\Services\Trainings;

use App\Models\AcademyClass;
use App\Models\Attendance;
use App\Models\ClubSeason;
use App\Models\Training;
use Carbon\Carbon;

/**
 * Legacy-compatible list of ended trainings that still lack attendance marks.
 * Mirrors TeamSuite `listMissingAttendanceTrainings` (classId|date key).
 */
class MissingAttendanceTrainings
{
    /**
     * @param  list<string>|null  $classIds  When set, only these classes are considered.
     * @return list<array{id: string, class_id: string, class_name: string, date: string, start_time: string, end_time: string, location: string|null}>
     */
    public function forClub(string $clubId, ?array $classIds = null, ?Carbon $now = null): array
    {
        $now ??= now();
        $lookback = $now->copy()->subDays(90)->startOfDay();

        $season = ClubSeason::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $now->toDateString())
            ->whereDate('ends_on', '>=', $now->toDateString())
            ->orderByDesc('starts_on')
            ->first(['id', 'starts_on', 'ends_on']);

        if (! $season) {
            $season = ClubSeason::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->whereDate('starts_on', '<=', $now->toDateString())
                ->whereDate('ends_on', '>=', $now->toDateString())
                ->orderByDesc('starts_on')
                ->first(['id', 'starts_on', 'ends_on']);
        }

        $classesQuery = AcademyClass::query()->withoutGlobalScopes()
            ->where('club_id', $clubId);
        if ($classIds !== null) {
            $classesQuery->whereIn('id', $classIds);
        }
        $classes = $classesQuery->get(['id', 'name', 'meta']);

        $eligibleClassIds = [];
        $classNameById = [];
        foreach ($classes as $class) {
            $meta = is_array($class->meta) ? $class->meta : [];
            $required = $meta['attendanceRequired'] ?? $meta['attendance_required'] ?? true;
            if ($required === false || $required === 0 || $required === '0') {
                continue;
            }
            $eligibleClassIds[] = $class->id;
            $classNameById[$class->id] = $class->name;
        }

        if ($eligibleClassIds === []) {
            return [];
        }

        $trainingsQuery = Training::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereIn('academy_class_id', $eligibleClassIds)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            })
            ->where(function ($q) use ($now) {
                $q->where(function ($inner) use ($now) {
                    $inner->whereNotNull('ends_at')->where('ends_at', '<', $now);
                })->orWhere(function ($inner) use ($now) {
                    $inner->whereNull('ends_at')->where('starts_at', '<', $now);
                });
            });

        if ($season?->starts_on && $season?->ends_on) {
            $trainingsQuery
                ->whereDate('starts_at', '>=', $season->starts_on)
                ->whereDate('starts_at', '<=', $season->ends_on);
        } else {
            $trainingsQuery->where('starts_at', '>=', $lookback);
        }

        $trainings = $trainingsQuery
            ->orderBy('starts_at')
            ->limit(500)
            ->get(['id', 'academy_class_id', 'starts_at', 'ends_at', 'title', 'meta']);

        if ($trainings->isEmpty()) {
            return [];
        }

        $minDate = $trainings->min(fn (Training $t) => optional($t->starts_at)->toDateString()) ?? $lookback->toDateString();

        $attendanceRows = Attendance::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereDate('attended_on', '>=', $minDate)
            ->get(['attended_on', 'training_id', 'meta']);

        $attendedKeys = [];
        foreach ($attendanceRows as $row) {
            $day = optional($row->attended_on)->format('Y-m-d');
            if (! $day) {
                continue;
            }
            $metaClass = data_get($row->meta, 'class_id');
            if (is_string($metaClass) && $metaClass !== '') {
                $attendedKeys[$metaClass.'|'.$day] = true;
            }
            if ($row->training_id) {
                $attendedKeys['training:'.$row->training_id] = true;
            }
        }

        $missing = [];
        foreach ($trainings as $training) {
            $classId = $training->academy_class_id;
            if (! $classId) {
                continue;
            }
            $day = optional($training->starts_at)->format('Y-m-d');
            if (! $day) {
                continue;
            }
            if (isset($attendedKeys[$classId.'|'.$day]) || isset($attendedKeys['training:'.$training->id])) {
                continue;
            }

            $meta = is_array($training->meta) ? $training->meta : [];
            $missing[] = [
                'id' => $training->id,
                'class_id' => $classId,
                'class_name' => $classNameById[$classId] ?? ($meta['class_name'] ?? 'Τμήμα'),
                'date' => $day,
                'start_time' => optional($training->starts_at)->format('H:i') ?? '—',
                'end_time' => optional($training->ends_at)->format('H:i') ?? '—',
                'location' => $training->title ?: ($meta['location'] ?? null),
            ];
        }

        return $missing;
    }
}
