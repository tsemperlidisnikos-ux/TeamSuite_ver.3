<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Jobs\SendDebtReminderEmailsJob;
use App\Models\AcademyClass;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\ClubSeason;
use App\Models\FeeChargeTemplate;
use App\Models\Sport;
use App\Services\Audit\ClubActivityLogger;
use App\Support\PreferredNotifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FeeController extends Controller
{
    use InteractsWithClubResource;

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $owedOnly = $request->boolean('owed');
        $classId = (string) $request->query('class_id', '');

        $athletes = $this->clubQuery(Athlete::class, $request)
            ->with(['classes:id,name,sport'])
            ->where('status', '!=', 'inactive')
            ->when($q !== '', function ($builder) use ($q) {
                $like = '%'.$q.'%';
                $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                $builder->where(function ($w) use ($like, $op) {
                    $w->where('first_name', $op, $like)
                        ->orWhere('last_name', $op, $like);
                });
            })
            ->when($classId !== '', fn ($b) => $b->whereHas(
                'classes',
                fn ($c) => $c->where('academy_classes.id', $classId)
            ))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'status', 'monthly_fee', 'sport', 'email', 'guardian_phone', 'phone', 'meta']);

        $clubId = $this->clubId($request);
        $athleteIds = $athletes->pluck('id');

        $balances = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereIn('athlete_id', $athleteIds)
            ->selectRaw("athlete_id, SUM(CASE WHEN type = 'charge' THEN amount ELSE 0 END) as charges, SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as payments")
            ->groupBy('athlete_id')
            ->get()
            ->keyBy('athlete_id');

        $lastPayments = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('type', 'payment')
            ->whereIn('athlete_id', $athleteIds)
            ->orderByDesc('created_at')
            ->get(['athlete_id', 'amount', 'created_at'])
            ->unique('athlete_id')
            ->keyBy('athlete_id');

        $oldestCharges = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('type', 'charge')
            ->whereIn('athlete_id', $athleteIds)
            ->orderBy('created_at')
            ->get(['athlete_id', 'created_at'])
            ->unique('athlete_id')
            ->keyBy('athlete_id');

        $templates = $this->clubQuery(FeeChargeTemplate::class, $request)
            ->orderBy('name')
            ->get()
            ->map(fn (FeeChargeTemplate $t) => $this->serializeTemplate($t));

        $defaultReminderDays = (int) ($templates->max(fn ($t) => (int) ($t['reminder_days'] ?? 7)) ?: 7);

        $rows = $athletes->map(function (Athlete $a) use ($balances, $lastPayments, $oldestCharges, $defaultReminderDays) {
            $b = $balances->get($a->id);
            $charges = (float) ($b->charges ?? 0);
            $payments = (float) ($b->payments ?? 0);
            $owed = $charges - $payments;
            $last = $lastPayments->get($a->id);
            $oldest = $oldestCharges->get($a->id);
            $daysOverdue = $oldest?->created_at
                ? (int) $oldest->created_at->diffInDays(now())
                : 0;

            return [
                'id' => $a->id,
                'first_name' => $a->first_name,
                'last_name' => $a->last_name,
                'status' => $a->status,
                'monthly_fee' => $a->monthly_fee,
                'sport' => $a->sport,
                'class_names' => $a->classes->pluck('name')->filter()->join(', '),
                'charges' => $charges,
                'payments' => $payments,
                'owed' => $owed,
                'last_payment_amount' => $last ? (float) $last->amount : null,
                'last_payment_at' => $last?->created_at?->toIso8601String(),
                'oldest_charge_at' => $oldest?->created_at?->toIso8601String(),
                'days_overdue' => $daysOverdue,
                'reminder_days' => $defaultReminderDays,
                'notify_email' => $this->resolveNotifyEmail($a),
                'notify_phone' => $a->guardian_phone ?: $a->phone,
            ];
        })->when($owedOnly, fn ($c) => $c->filter(fn ($r) => $r['owed'] > 0.009)->values());

        $reminders = $rows
            ->filter(fn ($r) => $r['owed'] > 0.009 && $r['days_overdue'] >= (int) $r['reminder_days'])
            ->values()
            ->map(fn ($r) => [
                'athlete_id' => $r['id'],
                'athlete_name' => trim($r['last_name'].' '.$r['first_name']),
                'balance' => $r['owed'],
                'days_overdue' => $r['days_overdue'],
                'reminder_days' => $r['reminder_days'],
                'oldest_charge_date' => $r['oldest_charge_at']
                    ? substr((string) $r['oldest_charge_at'], 0, 10)
                    : null,
                'email' => $r['notify_email'],
                'phone' => $r['notify_phone'],
            ]);

        $totals = [
            'owed' => round($rows->sum(fn ($r) => max(0, $r['owed'])), 2),
            'credit' => round($rows->sum(fn ($r) => max(0, -$r['owed'])), 2),
            'with_debt' => $rows->filter(fn ($r) => $r['owed'] > 0.009)->count(),
            'active_athletes' => $athletes->count(),
        ];

        $seasons = $this->clubQuery(ClubSeason::class, $request)
            ->orderByDesc('starts_on')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (ClubSeason $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'is_active' => (bool) $s->is_active,
            ]);

        if ($seasons->isEmpty()) {
            $y = (int) now()->year;
            $seasons = collect([
                ['id' => null, 'name' => ($y - 1).'–'.$y, 'is_active' => false],
                ['id' => null, 'name' => $y.'–'.($y + 1), 'is_active' => true],
            ]);
        }

        $sports = $this->clubQuery(Sport::class, $request)
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();

        if ($sports->isEmpty()) {
            $sports = $this->clubQuery(AcademyClass::class, $request)
                ->whereNotNull('sport')
                ->orderBy('sport')
                ->pluck('sport')
                ->unique()
                ->values();
        }

        return Inertia::render('Admin/Fees/Index', [
            'rows' => $rows->values(),
            'templates' => $templates,
            'reminders' => $reminders,
            'classes' => $this->clubQuery(AcademyClass::class, $request)
                ->orderBy('name')
                ->get(['id', 'name', 'sport']),
            'seasons' => $seasons,
            'sports' => $sports,
            'athletes' => $athletes->map(fn (Athlete $a) => [
                'id' => $a->id,
                'first_name' => $a->first_name,
                'last_name' => $a->last_name,
                'sport' => $a->sport,
                'class_ids' => $a->classes->pluck('id')->values()->all(),
            ]),
            'filters' => [
                'q' => $q,
                'owed' => $owedOnly ? '1' : '',
                'class_id' => $classId,
            ],
            'totals' => $totals,
            'defaultMonth' => now()->month,
            'defaultYear' => now()->year,
            'monthLabels' => $this->seasonMonthLabels(),
            'appliesToOptions' => [
                ['value' => 'all', 'label' => 'Όλοι οι αθλητές'],
                ['value' => 'class', 'label' => 'Τμήμα'],
                ['value' => 'athlete', 'label' => 'Αθλητής'],
            ],
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $this->validatedTemplate($request);
        $meta = $this->templateMetaFrom($data);

        FeeChargeTemplate::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $this->clubId($request),
            'name' => $data['name'],
            'amount' => $data['amount'],
            'applies_to' => $data['applies_to'] ?? 'all',
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'auto_generate' => filter_var($data['auto_generate'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'meta' => $meta,
        ]);

        return back()->with('success', 'Το πρότυπο χρέωσης αποθηκεύτηκε.');
    }

    public function updateTemplate(Request $request, string $id): RedirectResponse
    {
        $data = $this->validatedTemplate($request);
        $row = $this->clubQuery(FeeChargeTemplate::class, $request)->findOrFail($id);

        $row->fill([
            'name' => $data['name'],
            'amount' => $data['amount'],
            'applies_to' => $data['applies_to'] ?? 'all',
            'is_active' => filter_var($data['is_active'] ?? $row->is_active, FILTER_VALIDATE_BOOLEAN),
            'auto_generate' => filter_var($data['auto_generate'] ?? $row->auto_generate, FILTER_VALIDATE_BOOLEAN),
            'meta' => array_merge($row->meta ?? [], $this->templateMetaFrom($data)),
        ])->save();

        return back()->with('success', 'Το πρότυπο ενημερώθηκε.');
    }

    public function destroyTemplate(Request $request, string $id): RedirectResponse
    {
        $this->clubQuery(FeeChargeTemplate::class, $request)->findOrFail($id)->delete();

        return back()->with('success', 'Το πρότυπο διαγράφηκε.');
    }

    public function generateFromTemplates(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'template_ids' => 'required|array|min:1',
            'template_ids.*' => 'uuid',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $clubId = $this->clubId($request);
        $year = (int) ($data['year'] ?? now()->year);
        $created = 0;
        $athleteIds = [];

        $templates = $this->clubQuery(FeeChargeTemplate::class, $request)
            ->whereIn('id', $data['template_ids'])
            ->get();

        abort_if($templates->isEmpty(), 422, 'Δεν βρέθηκαν πρότυπα.');

        DB::transaction(function () use ($templates, $clubId, $year, &$created, &$athleteIds) {
            foreach ($templates as $tpl) {
                $meta = $tpl->meta ?? [];
                $months = array_values(array_filter(
                    array_map('intval', $meta['months'] ?? [(int) now()->month]),
                    fn (int $m) => $m >= 1 && $m <= 12
                ));
                if ($months === []) {
                    $months = [(int) now()->month];
                }

                $targets = $this->athletesForTemplate($clubId, $tpl);

                foreach ($targets as $athlete) {
                    $athleteIds[$athlete->id] = true;
                    foreach ($months as $month) {
                        $exists = AthleteTransaction::query()->withoutGlobalScopes()
                            ->where('club_id', $clubId)
                            ->where('athlete_id', $athlete->id)
                            ->where('type', 'charge')
                            ->where('month', $month)
                            ->where('year', $year)
                            ->where('meta->template_id', $tpl->id)
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $amount = (float) $tpl->amount;
                        $registration = (float) ($meta['registration_fee'] ?? 0);
                        $ticketAmount = (float) ($meta['season_ticket_amount'] ?? 0);
                        $ticketMonths = array_map('intval', $meta['season_ticket_months'] ?? []);
                        $isFirstMonth = $month === min($months);

                        if ($isFirstMonth && $registration > 0) {
                            $amount += $registration;
                        }
                        if ($ticketAmount > 0 && in_array($month, $ticketMonths, true) && count($ticketMonths) > 0) {
                            $amount += round($ticketAmount / count($ticketMonths), 2);
                        }

                        if ($amount <= 0) {
                            continue;
                        }

                        AthleteTransaction::query()->withoutGlobalScopes()->create([
                            'id' => (string) Str::uuid(),
                            'club_id' => $clubId,
                            'athlete_id' => $athlete->id,
                            'type' => 'charge',
                            'amount' => $amount,
                            'month' => $month,
                            'year' => $year,
                            'comments' => $tpl->name,
                            'meta' => [
                                'template_id' => $tpl->id,
                                'generated' => true,
                            ],
                        ]);
                        $created++;
                    }
                }
            }
        });

        $athletesCount = count($athleteIds);

        return back()->with(
            'success',
            "ΕΓΙΝΕ ΔΗΜΙΟΥΡΓΙΑ ΧΡΕΩΣΗΣ · {$created} χρεώσεις για {$athletesCount} αθλητές."
        );
    }

    public function remindDebts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'athlete_ids' => 'nullable|array',
            'athlete_ids.*' => 'uuid',
            'send' => 'boolean',
        ]);

        $clubId = $this->clubId($request);
        $send = filter_var($data['send'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $athletes = $this->clubQuery(Athlete::class, $request)
            ->where('status', '!=', 'inactive')
            ->when(
                ! empty($data['athlete_ids']),
                fn ($q) => $q->whereIn('id', $data['athlete_ids'])
            )
            ->get(['id', 'first_name', 'last_name', 'email', 'meta', 'guardian_phone', 'phone']);

        $balances = AthleteTransaction::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereIn('athlete_id', $athletes->pluck('id'))
            ->selectRaw("athlete_id, SUM(CASE WHEN type = 'charge' THEN amount ELSE 0 END) as charges, SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as payments")
            ->groupBy('athlete_id')
            ->get()
            ->keyBy('athlete_id');

        $payload = [];
        foreach ($athletes as $athlete) {
            $b = $balances->get($athlete->id);
            $owed = (float) ($b->charges ?? 0) - (float) ($b->payments ?? 0);
            if ($owed <= 0.009) {
                continue;
            }
            $email = $this->resolveNotifyEmail($athlete);
            if (! $email) {
                continue;
            }
            $payload[] = [
                'athlete_id' => $athlete->id,
                'name' => trim($athlete->last_name.' '.$athlete->first_name),
                'email' => $email,
                'amount' => round($owed, 2),
            ];
        }

        if ($payload === []) {
            return back()->with('success', 'Δεν υπάρχουν οφειλές με email προς υπενθύμιση.');
        }

        if ($send) {
            SendDebtReminderEmailsJob::dispatch($clubId, $payload);
        }

        $count = count($payload);

        return back()->with(
            'success',
            $send
                ? "Η υπενθύμιση οφειλών τέθηκε σε ουρά για {$count} παραλήπτη/ες."
                : "Βρέθηκαν {$count} οφειλές με email (χωρίς αποστολή)."
        );
    }

    public function bulkCharge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'athlete_ids' => 'required|array|min:1',
            'athlete_ids.*' => 'uuid',
            'amount' => 'nullable|numeric|min:0.01',
            'template_id' => 'nullable|uuid',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'comments' => 'nullable|string|max:500',
            'use_monthly_fee' => 'boolean',
        ]);

        $clubId = $this->clubId($request);
        $amount = null;
        $label = $data['comments'] ?? 'Μηνιαία χρέωση';

        if (! empty($data['template_id'])) {
            $tpl = $this->clubQuery(FeeChargeTemplate::class, $request)->findOrFail($data['template_id']);
            $amount = (float) $tpl->amount;
            $label = $tpl->name;
        } elseif (! empty($data['amount'])) {
            $amount = (float) $data['amount'];
        }

        $useMonthly = filter_var($data['use_monthly_fee'] ?? false, FILTER_VALIDATE_BOOLEAN);
        abort_unless($amount !== null || $useMonthly, 422, 'Ορίστε ποσό ή πρότυπο.');

        $athletes = $this->clubQuery(Athlete::class, $request)
            ->whereIn('id', $data['athlete_ids'])
            ->get(['id', 'monthly_fee']);

        $created = 0;
        DB::transaction(function () use ($athletes, $clubId, $amount, $useMonthly, $data, $label, &$created) {
            foreach ($athletes as $athlete) {
                $chargeAmount = $useMonthly
                    ? (float) $athlete->monthly_fee
                    : (float) $amount;

                if ($chargeAmount <= 0) {
                    continue;
                }

                AthleteTransaction::query()->withoutGlobalScopes()->create([
                    'id' => (string) Str::uuid(),
                    'club_id' => $clubId,
                    'athlete_id' => $athlete->id,
                    'type' => 'charge',
                    'amount' => $chargeAmount,
                    'month' => $data['month'],
                    'year' => $data['year'],
                    'comments' => $label,
                ]);
                $created++;
            }
        });

        if ($created > 0) {
            app(ClubActivityLogger::class)->record(
                $clubId,
                'fee.bulk_charge',
                'Δημιούργησε χρεώσεις συνδρομών',
                sprintf(
                    '%d χρεώσεις · %02d/%d · %s',
                    $created,
                    (int) $data['month'],
                    (int) $data['year'],
                    $label
                ),
                'athlete_transaction',
                null,
                ['athlete_count' => count($data['athlete_ids']), 'created' => $created],
            );
        }

        return back()->with('success', "Δημιουργήθηκαν {$created} χρεώσεις.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTemplate(Request $request): array
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:160',
            'type_label' => 'nullable|string|max:160',
            'amount' => 'nullable|numeric|min:0',
            'monthly_amount' => 'nullable|numeric|min:0',
            'applies_to' => 'nullable|string|max:80',
            'is_active' => 'boolean',
            'auto_generate' => 'boolean',
            'season' => 'nullable|string|max:80',
            'sport' => 'nullable|string|max:120',
            'months' => 'nullable|array',
            'months.*' => 'integer|min:1|max:12',
            'reminder_days' => 'nullable|integer|min:0|max:365',
            'registration_fee' => 'nullable|numeric|min:0',
            'season_ticket_amount' => 'nullable|numeric|min:0',
            'season_ticket_months' => 'nullable|array',
            'season_ticket_months.*' => 'integer|min:1|max:12',
            'class_id' => 'nullable|uuid',
            'athlete_id' => 'nullable|uuid',
        ]);

        $monthly = (float) ($data['monthly_amount'] ?? $data['amount'] ?? 0);
        $typeLabel = trim((string) ($data['type_label'] ?? $data['name'] ?? 'Συνδρομή'));
        $season = trim((string) ($data['season'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = $season !== '' ? "{$typeLabel} · {$season}" : $typeLabel;
        }

        $data['name'] = $name;
        $data['amount'] = $monthly;
        $data['type_label'] = $typeLabel;
        $data['applies_to'] = $data['applies_to'] ?? 'all';

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function templateMetaFrom(array $data): array
    {
        return [
            'season' => $data['season'] ?? '',
            'sport' => $data['sport'] ?? '',
            'type_label' => $data['type_label'] ?? 'Συνδρομή',
            'months' => array_values(array_map('intval', $data['months'] ?? [])),
            'reminder_days' => (int) ($data['reminder_days'] ?? 7),
            'registration_fee' => (float) ($data['registration_fee'] ?? 0),
            'season_ticket_amount' => (float) ($data['season_ticket_amount'] ?? 0),
            'season_ticket_months' => array_values(array_map('intval', $data['season_ticket_months'] ?? [])),
            'class_id' => $data['class_id'] ?? null,
            'athlete_id' => $data['athlete_id'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTemplate(FeeChargeTemplate $t): array
    {
        $meta = $t->meta ?? [];

        return [
            'id' => $t->id,
            'name' => $t->name,
            'amount' => (float) $t->amount,
            'applies_to' => $t->applies_to ?: 'all',
            'is_active' => (bool) $t->is_active,
            'auto_generate' => (bool) $t->auto_generate,
            'season' => (string) ($meta['season'] ?? ''),
            'sport' => (string) ($meta['sport'] ?? ''),
            'type_label' => (string) ($meta['type_label'] ?? $t->name),
            'months' => array_values(array_map('intval', $meta['months'] ?? [])),
            'reminder_days' => (int) ($meta['reminder_days'] ?? 7),
            'registration_fee' => (float) ($meta['registration_fee'] ?? 0),
            'season_ticket_amount' => (float) ($meta['season_ticket_amount'] ?? 0),
            'season_ticket_months' => array_values(array_map('intval', $meta['season_ticket_months'] ?? [])),
            'class_id' => $meta['class_id'] ?? null,
            'athlete_id' => $meta['athlete_id'] ?? null,
        ];
    }

    /**
     * @return Collection<int, Athlete>
     */
    private function athletesForTemplate(string $clubId, FeeChargeTemplate $tpl)
    {
        $meta = $tpl->meta ?? [];
        $appliesTo = $tpl->applies_to ?: 'all';
        $sport = trim((string) ($meta['sport'] ?? ''));
        $classId = $meta['class_id'] ?? null;
        $athleteId = $meta['athlete_id'] ?? null;

        $query = Athlete::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('status', 'active');

        if ($appliesTo === 'athlete' && $athleteId) {
            $query->where('id', $athleteId);
        } elseif ($appliesTo === 'class' && $classId) {
            $query->whereHas('classes', fn ($c) => $c->where('academy_classes.id', $classId));
        }

        if ($sport !== '') {
            $query->where(function ($w) use ($sport) {
                $w->where('sport', $sport)
                    ->orWhere('meta->sports', 'like', '%'.$sport.'%')
                    ->orWhereHas('classes', fn ($c) => $c->where('sport', $sport));
            });
        }

        return $query->get(['id', 'monthly_fee', 'sport', 'meta']);
    }

    private function resolveNotifyEmail(Athlete $athlete): ?string
    {
        return PreferredNotifyEmail::resolvePrimary($athlete);
    }

    /**
     * @return list<array{month: int, label: string}>
     */
    private function seasonMonthLabels(): array
    {
        return [
            ['month' => 9, 'label' => 'Σεπ'],
            ['month' => 10, 'label' => 'Οκτ'],
            ['month' => 11, 'label' => 'Νοε'],
            ['month' => 12, 'label' => 'Δεκ'],
            ['month' => 1, 'label' => 'Ιαν'],
            ['month' => 2, 'label' => 'Φεβ'],
            ['month' => 3, 'label' => 'Μαρ'],
            ['month' => 4, 'label' => 'Απρ'],
            ['month' => 5, 'label' => 'Μάι'],
            ['month' => 6, 'label' => 'Ιούν'],
            ['month' => 7, 'label' => 'Ιούλ'],
            ['month' => 8, 'label' => 'Αύγ'],
        ];
    }
}
