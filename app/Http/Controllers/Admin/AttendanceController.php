<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\AcademyClass;
use App\Models\Athlete;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    use InteractsWithClubResource;

    public function index(Request $request): Response
    {
        $date = $request->query('date', now()->toDateString());
        $classId = (string) $request->query('class_id', '');
        $sport = trim((string) $request->query('sport', ''));

        $classes = $this->clubQuery(AcademyClass::class, $request)
            ->when($sport !== '', fn ($q) => $q->where('sport', 'like', '%'.$sport.'%'))
            ->orderBy('name')
            ->get(['id', 'name', 'sport']);

        if ($classId === '' && $classes->isNotEmpty()) {
            $classId = (string) $classes->first()->id;
        }

        $athletes = collect();
        $marks = [];
        $summary = ['present' => 0, 'absent' => 0, 'excused' => 0, 'none' => 0];

        if ($classId !== '') {
            $athletes = Athlete::query()->withoutGlobalScopes()
                ->where('club_id', $this->clubId($request))
                ->where('status', '!=', 'inactive')
                ->whereHas('classes', fn ($q) => $q->where('academy_classes.id', $classId))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'status']);

            $rows = Attendance::query()->withoutGlobalScopes()
                ->where('club_id', $this->clubId($request))
                ->whereDate('attended_on', $date)
                ->whereIn('athlete_id', $athletes->pluck('id'))
                ->get(['id', 'athlete_id', 'status', 'meta']);

            foreach ($rows as $row) {
                $metaClass = data_get($row->meta, 'class_id');
                if ($metaClass && $metaClass !== $classId) {
                    continue;
                }
                $marks[$row->athlete_id] = [
                    'id' => $row->id,
                    'status' => $row->status,
                ];
            }

            foreach ($athletes as $athlete) {
                $st = $marks[$athlete->id]['status'] ?? 'none';
                if (isset($summary[$st])) {
                    $summary[$st]++;
                } else {
                    $summary['none']++;
                }
            }
        }

        $sports = $this->clubQuery(AcademyClass::class, $request)
            ->whereNotNull('sport')
            ->where('sport', '!=', '')
            ->distinct()
            ->orderBy('sport')
            ->pluck('sport');

        $historyDates = [];
        $history = [];
        $anchor = Carbon::parse($date);
        for ($i = 10; $i >= 0; $i--) {
            $historyDates[] = $anchor->copy()->subDays($i)->toDateString();
        }

        if ($classId !== '' && $athletes->isNotEmpty()) {
            $histRows = Attendance::query()->withoutGlobalScopes()
                ->where('club_id', $this->clubId($request))
                ->whereIn('attended_on', $historyDates)
                ->whereIn('athlete_id', $athletes->pluck('id'))
                ->get(['athlete_id', 'attended_on', 'status', 'meta']);

            foreach ($histRows as $row) {
                $metaClass = data_get($row->meta, 'class_id');
                if ($metaClass && $metaClass !== $classId) {
                    continue;
                }
                $day = optional($row->attended_on)->format('Y-m-d');
                if (! $day) {
                    continue;
                }
                $history[$row->athlete_id][$day] = $row->status;
            }
        }

        $selectedClass = $classes->firstWhere('id', $classId);
        $dateLabel = $anchor->locale('el')->translatedFormat('l, j F Y');

        return Inertia::render('Admin/Attendance/Index', [
            'classes' => $classes,
            'sports' => $sports,
            'athletes' => $athletes->map(fn (Athlete $a) => [
                'id' => $a->id,
                'first_name' => $a->first_name,
                'last_name' => $a->last_name,
                'status' => $a->status,
                'mark' => $marks[$a->id]['status'] ?? 'none',
                'history' => $history[$a->id] ?? [],
            ]),
            'filters' => [
                'date' => $date,
                'class_id' => $classId,
                'sport' => $sport,
            ],
            'summary' => $summary,
            'historyDates' => $historyDates,
            'dateLabel' => $dateLabel,
            'classSport' => $selectedClass?->sport,
        ]);
    }

    public function upsert(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => 'required|uuid',
            'date' => 'required|date',
            'athlete_id' => 'required|uuid',
            'status' => 'required|in:present,absent,excused,none',
        ]);

        $clubId = $this->clubId($request);

        $this->clubQuery(AcademyClass::class, $request)->whereKey($data['class_id'])->firstOrFail();
        Athlete::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereKey($data['athlete_id'])
            ->firstOrFail();

        $existing = Attendance::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('athlete_id', $data['athlete_id'])
            ->whereDate('attended_on', $data['date'])
            ->where(function ($q) use ($data) {
                $q->whereNull('training_id')
                    ->where(function ($inner) use ($data) {
                        $inner->where('meta->class_id', $data['class_id'])
                            ->orWhereNull('meta');
                    });
            })
            ->first();

        if ($data['status'] === 'none') {
            $existing?->delete();

            return back();
        }

        if ($existing) {
            $existing->update([
                'status' => $data['status'],
                'meta' => array_merge($existing->meta ?? [], ['class_id' => $data['class_id']]),
            ]);
        } else {
            Attendance::query()->withoutGlobalScopes()->create([
                'id' => (string) Str::uuid(),
                'club_id' => $clubId,
                'athlete_id' => $data['athlete_id'],
                'training_id' => null,
                'status' => $data['status'],
                'attended_on' => $data['date'],
                'meta' => ['class_id' => $data['class_id']],
            ]);
        }

        return back();
    }

    public function markAll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => 'required|uuid',
            'date' => 'required|date',
            'status' => 'required|in:present,absent',
        ]);

        $clubId = $this->clubId($request);
        $this->clubQuery(AcademyClass::class, $request)->whereKey($data['class_id'])->firstOrFail();

        $athleteIds = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('status', '!=', 'inactive')
            ->whereHas('classes', fn ($q) => $q->where('academy_classes.id', $data['class_id']))
            ->pluck('id');

        foreach ($athleteIds as $athleteId) {
            $existing = Attendance::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->where('athlete_id', $athleteId)
                ->whereDate('attended_on', $data['date'])
                ->whereNull('training_id')
                ->first();

            if ($existing) {
                $existing->update([
                    'status' => $data['status'],
                    'meta' => array_merge($existing->meta ?? [], ['class_id' => $data['class_id']]),
                ]);
            } else {
                Attendance::query()->withoutGlobalScopes()->create([
                    'id' => (string) Str::uuid(),
                    'club_id' => $clubId,
                    'athlete_id' => $athleteId,
                    'training_id' => null,
                    'status' => $data['status'],
                    'attended_on' => $data['date'],
                    'meta' => ['class_id' => $data['class_id']],
                ]);
            }
        }

        return back()->with('success', 'Οι παρουσίες ενημερώθηκαν.');
    }
}
