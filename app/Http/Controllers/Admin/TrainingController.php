<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademyClass;
use App\Models\Training;
use App\Services\Trainings\MissingAttendanceTrainings;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class TrainingController extends ClubResourceController
{
    /** @var array<string, AcademyClass|null> */
    private array $classLookup = [];

    public function __construct(
        private readonly MissingAttendanceTrainings $missingAttendance,
    ) {}

    protected function resourceKey(): string
    {
        return 'trainings';
    }

    public function index(Request $request): Response
    {
        $this->classLookup = [];
        $response = parent::index($request);

        $clubId = $this->clubId($request);
        $missing = $this->missingAttendance->forClub($clubId);

        return $response->with([
            'missingAttendance' => array_slice($missing, 0, 50),
            'missingAttendanceCount' => count($missing),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  class-string  $modelClass
     * @return array<string, mixed>
     */
    protected function enrichIndexRow(array $row, string $modelClass): array
    {
        $row = parent::enrichIndexRow($row, $modelClass);

        $classId = $row['academy_class_id'] ?? null;
        if (is_string($classId) && $classId !== '') {
            if (! array_key_exists($classId, $this->classLookup)) {
                $this->classLookup[$classId] = AcademyClass::query()->withoutGlobalScopes()
                    ->whereKey($classId)
                    ->first(['id', 'name', 'sport']);
            }
            $class = $this->classLookup[$classId];
            if ($class) {
                $row['class_name'] = $class->name;
                $row['sport'] = $class->sport ?: ($row['sport'] ?? '—');
            }
        }

        return $row;
    }

    public function remindTomorrow(Request $request): RedirectResponse
    {
        $start = now()->addDay()->startOfDay();
        $end = $start->copy()->endOfDay();

        $count = $this->clubQuery(Training::class, $request)
            ->whereBetween('starts_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->count();

        return back()->with(
            'success',
            $count > 0
                ? "Προετοιμάστηκε υπενθύμιση για {$count} προπονήσεις αύριο."
                : 'Δεν υπάρχουν προπονήσεις αύριο για υπενθύμιση.',
        );
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid'],
            'status' => ['required', 'in:scheduled,done,cancelled'],
        ]);

        $updated = $this->clubQuery(Training::class, $request)
            ->whereIn('id', $data['ids'])
            ->update(['status' => $data['status']]);

        return back()->with('success', "Ενημερώθηκαν {$updated} προπονήσεις.");
    }

    public function createRecurring(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'min:0', 'max:6'],
            'starts_time' => ['required', 'date_format:H:i'],
            'ends_time' => ['nullable', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:160'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $weeks = (int) ($data['weeks'] ?? 4);
        $title = $data['title'] ?: 'Προπόνηση';
        $clubId = $this->clubId($request);
        $created = 0;
        $weekdays = array_map('intval', $data['weekdays']);

        $cursor = now()->startOfDay();
        $endHorizon = $cursor->copy()->addWeeks($weeks);

        while ($cursor->lt($endHorizon)) {
            if (in_array($cursor->dayOfWeek, $weekdays, true)) {
                $startsAt = Carbon::parse($cursor->toDateString().' '.$data['starts_time']);
                $endsAt = ! empty($data['ends_time'])
                    ? Carbon::parse($cursor->toDateString().' '.$data['ends_time'])
                    : $startsAt->copy()->addHour()->addMinutes(30);

                $exists = $this->clubQuery(Training::class, $request)
                    ->where('title', $title)
                    ->where('starts_at', $startsAt)
                    ->exists();

                if (! $exists) {
                    Training::query()->withoutGlobalScopes()->create([
                        'id' => (string) Str::uuid(),
                        'club_id' => $clubId,
                        'title' => $title,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'notes' => $data['notes'] ?? null,
                        'status' => 'scheduled',
                    ]);
                    $created++;
                }
            }
            $cursor->addDay();
        }

        return back()->with('success', "Δημιουργήθηκαν {$created} επαναλαμβανόμενες προπονήσεις.");
    }
}
