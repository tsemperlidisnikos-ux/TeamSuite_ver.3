<?php

namespace App\Services\Backup;

use App\Models\AcademyClass;
use App\Models\AmkaAccessLog;
use App\Models\Announcement;
use App\Models\Association;
use App\Models\Athlete;
use App\Models\AthleteChangeLog;
use App\Models\AthleteTransaction;
use App\Models\Attendance;
use App\Models\BudgetLine;
use App\Models\CashAccount;
use App\Models\ClothingPackage;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubSeason;
use App\Models\Coach;
use App\Models\DiscountReason;
use App\Models\DocumentProtocolEntry;
use App\Models\EmailUnsubscribe;
use App\Models\Expense;
use App\Models\Facility;
use App\Models\FeeChargeTemplate;
use App\Models\GalleryPhoto;
use App\Models\GdprAuditLog;
use App\Models\OnlineCheckout;
use App\Models\ParentAthleteLink;
use App\Models\PartnerBusiness;
use App\Models\PartnerOffer;
use App\Models\ProgressReport;
use App\Models\ReceiptIssue;
use App\Models\ReceiptSeries;
use App\Models\RegistrationApplication;
use App\Models\RentalBooking;
use App\Models\RentalSetting;
use App\Models\Revenue;
use App\Models\ScheduleSlot;
use App\Models\SizeChart;
use App\Models\Sport;
use App\Models\SportMatch;
use App\Models\StaffMember;
use App\Models\StockMovement;
use App\Models\SuppressedFeeChargeKey;
use App\Models\Training;
use App\Models\User;
use App\Models\WarehouseProduct;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Export a club as legacy TeamSuite Club JSON (importable by TeamSuiteBackupRestoreService).
 */
class TeamSuiteClubExporter
{
    /** @var array<string, array<string, string>> mapName => [uuid => exportId] */
    private array $idMaps = [];

    /**
     * @return array{
     *     exportedAt: string,
     *     scope: string,
     *     sourceClubId: string,
     *     clubs: list<array<string, mixed>>,
     *     users: list<array<string, mixed>>,
     *     appData: array<string, mixed>,
     *     appDataByClub: array<string, array<string, mixed>>
     * }
     */
    public function export(Club $club): array
    {
        $club->loadMissing(['settings', 'licensePackage', 'users']);
        $this->buildIdMaps($club->id);
        $appData = $this->exportAppData($club);

        return [
            'exportedAt' => now()->toIso8601String(),
            'scope' => 'club',
            'sourceClubId' => $club->id,
            'clubs' => [$this->exportClubRecord($club)],
            'users' => $this->exportUsers($club),
            'appData' => $appData,
            'appDataByClub' => [
                $club->id => $appData,
            ],
        ];
    }

    private function buildIdMaps(string $clubId): void
    {
        $this->idMaps = [
            'classes' => $this->idMapFor($clubId, AcademyClass::class),
            'students' => $this->idMapFor($clubId, Athlete::class),
            'coaches' => $this->idMapFor($clubId, Coach::class),
            'clubSeasons' => $this->idMapFor($clubId, ClubSeason::class),
            'facilities' => $this->idMapFor($clubId, Facility::class),
            'trainings' => $this->idMapFor($clubId, Training::class),
            'products' => $this->idMapFor($clubId, WarehouseProduct::class),
            'cashAccounts' => $this->idMapFor($clubId, CashAccount::class),
            'partnerBusinesses' => $this->idMapFor($clubId, PartnerBusiness::class),
            'transactions' => $this->idMapFor($clubId, AthleteTransaction::class),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, string>
     */
    private function idMapFor(string $clubId, string $modelClass): array
    {
        $map = [];
        foreach ($this->clubRows($clubId, $modelClass) as $row) {
            $map[(string) $row->getKey()] = $this->exportId($row);
        }

        return $map;
    }

    private function mappedId(string $mapName, mixed $uuid): ?string
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $key = (string) $uuid;

        return $this->idMaps[$mapName][$key] ?? $key;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportClubRecord(Club $club): array
    {
        $settings = $club->settings;
        $smtp = is_array($settings?->smtp) ? $settings->smtp : [];
        $sms = is_array($settings?->sms) ? $settings->sms : [];
        $viva = is_array($settings?->viva) ? $settings->viva : [];
        $profile = is_array($settings?->profile) ? $settings->profile : [];

        return array_filter([
            'id' => $club->id,
            'name' => $club->name,
            'slug' => $club->slug,
            'athleteLicenseLimit' => $club->licensePackage?->athlete_cap,
            'smtp' => array_merge($smtp, ['password' => '']),
            'sms' => array_merge($sms, ['apiKey' => '']),
            'viva' => array_merge($viva, ['clientSecret' => '']),
            'profile' => $profile,
        ], fn ($v) => $v !== null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportUsers(Club $club): array
    {
        return $club->users()
            ->get()
            ->map(function (User $user) use ($club) {
                $pivot = $user->pivot;
                $permissions = $pivot->permissions ?? [];
                if (is_string($permissions)) {
                    $permissions = json_decode($permissions, true) ?: [];
                }

                return [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'fullName' => $user->name,
                    'email' => $user->email,
                    'clubId' => $club->id,
                    'role' => (string) ($pivot->role ?? 'staff'),
                    'password' => '',
                    'active' => (bool) ($pivot->is_active ?? true),
                    'permissions' => is_array($permissions) ? $permissions : [],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function exportAppData(Club $club): array
    {
        $clubId = $club->id;

        return [
            'sports' => $this->mapSimple($clubId, Sport::class, ['name' => 'name']),
            'clubSeasons' => $this->exportClubSeasons($clubId),
            'associations' => $this->mapSimple($clubId, Association::class, ['name' => 'name']),
            'cashAccounts' => $this->exportCashAccounts($clubId),
            'facilities' => $this->exportFacilities($clubId),
            'rentalSettings' => $this->exportRentalSettings($clubId),
            'coaches' => $this->exportCoaches($clubId),
            'staff' => $this->exportStaff($clubId),
            'clubMembers' => $this->exportClubMembers($clubId),
            'classes' => $this->exportClasses($clubId),
            'students' => $this->exportStudents($clubId),
            'parentLinks' => $this->exportParentLinks($clubId),
            'schedule' => $this->exportSchedule($clubId),
            'trainings' => $this->exportTrainings($clubId),
            'attendance' => $this->exportAttendance($clubId),
            'matches' => $this->exportMatches($clubId),
            'athleteChangeLogs' => $this->exportAthleteChangeLogs($clubId),
            'feeChargeTemplates' => $this->exportFeeTemplates($clubId),
            'transactions' => $this->exportTransactions($clubId),
            'revenues' => $this->exportRevenues($clubId),
            'expenses' => $this->exportExpenses($clubId),
            'budgets' => $this->exportBudgets($clubId),
            'receiptNumberRanges' => $this->exportReceiptSeries($clubId),
            'receiptNextBySeries' => $this->exportReceiptNextBySeries($clubId),
            'receiptIssues' => $this->exportReceiptIssues($clubId),
            'onlineCheckouts' => $this->exportOnlineCheckouts($clubId),
            'discountReasons' => $this->exportDiscountReasons($clubId),
            'products' => $this->exportProducts($clubId),
            'stockMovements' => $this->exportStockMovements($clubId),
            'clothingPackages' => $this->exportClothingPackages($clubId),
            'sizeChart' => $this->exportSizeChart($clubId),
            'rentalBookings' => $this->exportRentalBookings($clubId),
            'announcements' => $this->exportAnnouncements($clubId),
            'photos' => $this->exportPhotos($clubId),
            'partnerBusinesses' => $this->exportPartnerBusinesses($clubId),
            'partnerOffers' => $this->exportPartnerOffers($clubId),
            'progressReports' => $this->exportProgressReports($clubId),
            'registrationApplications' => $this->exportRegistrationApplications($clubId),
            'documentProtocolEntries' => $this->exportProtocolEntries($clubId),
            'amkaAccessLogs' => $this->exportAmkaLogs($clubId),
            'gdprAuditLogs' => $this->exportGdprLogs($clubId),
            'emailUnsubscribes' => $this->exportEmailUnsubscribes($clubId),
            'suppressedFeeChargeKeys' => $this->exportSuppressedKeys($clubId),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, string>  $fieldMap  legacyKey => column
     * @return list<array<string, mixed>>
     */
    private function mapSimple(string $clubId, string $modelClass, array $fieldMap): array
    {
        return $this->clubRows($clubId, $modelClass)
            ->map(function (Model $row) use ($fieldMap) {
                $out = ['id' => $this->exportId($row)];
                foreach ($fieldMap as $legacy => $column) {
                    $out[$legacy] = $this->exportValue($row->getAttribute($column));
                }

                return $out;
            })
            ->values()
            ->all();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return Collection<int, Model>
     */
    private function clubRows(string $clubId, string $modelClass): Collection
    {
        return $modelClass::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->orderBy('created_at')
            ->get();
    }

    private function exportId(Model $row): string
    {
        $legacy = $row->getAttribute('legacy_id');
        if (is_string($legacy) && $legacy !== '') {
            return $legacy;
        }

        return (string) $row->getKey();
    }

    private function exportValue(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return $value;
    }

    private function exportDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function exportTime(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i');
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}:\d{2})/', $value, $m)) {
            return $m[1];
        }

        return $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportClubSeasons(string $clubId): array
    {
        return $this->clubRows($clubId, ClubSeason::class)->map(fn (ClubSeason $s) => [
            'id' => $this->exportId($s),
            'name' => $s->name,
            'startsOn' => $this->exportDate($s->starts_on),
            'endsOn' => $this->exportDate($s->ends_on),
            'isActive' => (bool) $s->is_active,
            'isCurrent' => (bool) $s->is_active,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportCashAccounts(string $clubId): array
    {
        return $this->clubRows($clubId, CashAccount::class)->map(fn (CashAccount $a) => [
            'id' => $this->exportId($a),
            'name' => $a->name,
            'type' => $a->type,
            'openingBalance' => (float) $a->opening_balance,
            'isActive' => (bool) $a->is_active,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportFacilities(string $clubId): array
    {
        return $this->clubRows($clubId, Facility::class)->map(fn (Facility $f) => array_filter([
            'id' => $this->exportId($f),
            'name' => $f->name,
            'address' => $f->address,
            'isRentable' => (bool) $f->is_rentable,
            'hourlyRate' => data_get($f->meta, 'hourlyRate') ?? data_get($f->meta, 'hourly_rate'),
        ], fn ($v) => $v !== null))->values()->all();
    }

    private function exportRentalSettings(string $clubId): ?array
    {
        $row = RentalSetting::query()->withoutGlobalScopes()->where('club_id', $clubId)->first();
        if (! $row) {
            return null;
        }

        $rules = is_array($row->rules) ? $row->rules : [];

        return array_merge($rules, [
            'publicEnabled' => (bool) $row->public_enabled,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportCoaches(string $clubId): array
    {
        return $this->clubRows($clubId, Coach::class)->map(fn (Coach $c) => array_filter([
            'id' => $this->exportId($c),
            'firstName' => $c->first_name,
            'lastName' => $c->last_name,
            'email' => $c->email,
            'phone' => $c->phone,
            'sport' => $c->sport,
            'hireDate' => $this->exportDate($c->hire_date),
            'isActive' => (bool) $c->is_active,
            'ggaCode' => $c->gga_code,
            'licenseLevel' => $c->license_level,
            'userId' => $c->user_id ? (string) $c->user_id : null,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportStaff(string $clubId): array
    {
        return $this->clubRows($clubId, StaffMember::class)->map(fn (StaffMember $s) => array_filter([
            'id' => $this->exportId($s),
            'firstName' => $s->first_name,
            'lastName' => $s->last_name,
            'roleTitle' => $s->role_title,
            'email' => $s->email,
            'phone' => $s->phone,
            'isActive' => (bool) $s->is_active,
            'userId' => $s->user_id ? (string) $s->user_id : null,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportClubMembers(string $clubId): array
    {
        return $this->clubRows($clubId, ClubMember::class)->map(fn (ClubMember $m) => [
            'id' => $this->exportId($m),
            'firstName' => $m->first_name,
            'lastName' => $m->last_name,
            'email' => $m->email,
            'phone' => $m->phone,
            'memberNumber' => $m->member_number,
            'status' => $m->status,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportClasses(string $clubId): array
    {
        return $this->clubRows($clubId, AcademyClass::class)->map(fn (AcademyClass $c) => array_filter([
            'id' => $this->exportId($c),
            'name' => $c->name,
            'sport' => $c->sport,
            'ageGroup' => $c->age_group,
            'gender' => $c->gender,
            'maxStudents' => $c->max_students,
            'monthlyFee' => $c->monthly_fee !== null ? (float) $c->monthly_fee : null,
            'scheduleSummary' => $c->schedule_summary,
            'coachId' => $this->mappedId('coaches', $c->coach_id),
            'clubSeasonId' => $this->mappedId('clubSeasons', $c->club_season_id),
            'seasonId' => $this->mappedId('clubSeasons', $c->club_season_id),
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportStudents(string $clubId): array
    {
        return Athlete::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->with('classes:id,legacy_id')
            ->orderBy('last_name')
            ->get()
            ->map(function (Athlete $a) {
                $classIds = $a->classes->map(fn (AcademyClass $c) => $this->exportId($c))->values()->all();
                $meta = is_array($a->meta) ? $a->meta : [];

                return array_filter([
                    'id' => $this->exportId($a),
                    'firstName' => $a->first_name,
                    'lastName' => $a->last_name,
                    'email' => $a->email,
                    'phone' => $a->phone,
                    'birthDate' => $this->exportDate($a->birth_date),
                    'status' => $a->status,
                    'monthlyFee' => $a->monthly_fee !== null ? (float) $a->monthly_fee : null,
                    'enrolledAt' => $this->exportDate($a->enrolled_at),
                    'gender' => $a->gender,
                    'guardianName' => $a->guardian_name,
                    'guardianPhone' => $a->guardian_phone,
                    'sport' => $a->sport,
                    'gdprConsent' => (bool) $a->gdpr_consent,
                    'amkaFp' => $a->amka_fingerprint,
                    'healthCardStatus' => $a->health_card ? 'valid' : 'none',
                    'healthCardExpires' => $this->exportDate($a->health_card_expires),
                    'classIds' => $classIds,
                    'classId' => $classIds[0] ?? null,
                    'fatherEmail' => $meta['father_email'] ?? null,
                    'motherEmail' => $meta['mother_email'] ?? null,
                    'preferredParentEmail' => $meta['preferred_parent_email'] ?? null,
                ], fn ($v) => $v !== null && $v !== []);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportParentLinks(string $clubId): array
    {
        return ParentAthleteLink::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get()
            ->map(fn (ParentAthleteLink $l) => array_filter([
                'id' => $this->exportId($l),
                'parentUserId' => $l->parent_user_id ? (string) $l->parent_user_id : null,
                'athleteId' => $this->mappedId('students', $l->athlete_id),
                'relation' => $l->relation,
                'receivesPush' => (bool) $l->receives_push,
                'receivesSms' => (bool) $l->receives_sms,
            ], fn ($v) => $v !== null))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportSchedule(string $clubId): array
    {
        return $this->clubRows($clubId, ScheduleSlot::class)->map(fn (ScheduleSlot $s) => array_filter([
            'id' => $this->exportId($s),
            'classId' => $this->mappedId('classes', $s->academy_class_id),
            'facilityId' => $this->mappedId('facilities', $s->facility_id),
            'dayOfWeek' => $s->weekday,
            'weekday' => $s->weekday,
            'startTime' => $this->exportTime($s->starts_at),
            'endTime' => $this->exportTime($s->ends_at),
            'location' => $s->title,
            'title' => $s->title,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportTrainings(string $clubId): array
    {
        return $this->clubRows($clubId, Training::class)->map(function (Training $t) {
            $location = data_get($t->meta, 'location') ?: $t->title;

            return array_filter([
                'id' => $this->exportId($t),
                'classId' => $this->mappedId('classes', $t->academy_class_id),
                'coachId' => $this->mappedId('coaches', $t->coach_id),
                'facilityId' => $this->mappedId('facilities', $t->facility_id),
                'startsAt' => $this->exportValue($t->starts_at),
                'endsAt' => $this->exportValue($t->ends_at),
                'location' => $location,
                'title' => $t->title,
                'notes' => $t->notes,
                'status' => $t->status,
            ], fn ($v) => $v !== null);
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportAttendance(string $clubId): array
    {
        return Attendance::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get()
            ->map(function (Attendance $a) {
                $status = (string) $a->status;
                $present = in_array($status, ['present', 'late'], true);

                return array_filter([
                    'id' => $this->exportId($a),
                    'athleteId' => $this->mappedId('students', $a->athlete_id),
                    'trainingId' => $this->mappedId('trainings', $a->training_id),
                    'attendedOn' => $this->exportDate($a->attended_on),
                    'status' => $status,
                    'present' => $present,
                    'absenceReason' => data_get($a->meta, 'absence_reason'),
                    'classId' => $this->mappedId('classes', data_get($a->meta, 'class_id')),
                ], fn ($v) => $v !== null);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportMatches(string $clubId): array
    {
        return $this->clubRows($clubId, SportMatch::class)->map(fn (SportMatch $m) => array_filter([
            'id' => $this->exportId($m),
            'classId' => $this->mappedId('classes', $m->academy_class_id),
            'startsAt' => $this->exportValue($m->starts_at),
            'opponent' => $m->opponent,
            'venue' => $m->venue,
            'location' => $m->venue,
            'homeAway' => $m->home_away,
            'scoreFor' => $m->score_for,
            'scoreAgainst' => $m->score_against,
            'homeScore' => $m->score_for,
            'awayScore' => $m->score_against,
            'status' => $m->status,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportAthleteChangeLogs(string $clubId): array
    {
        return $this->clubRows($clubId, AthleteChangeLog::class)->map(fn (AthleteChangeLog $l) => [
            'id' => $this->exportId($l),
            'athleteId' => $this->mappedId('students', $l->athlete_id),
            'userId' => $l->user_id ? (string) $l->user_id : null,
            'field' => $l->field,
            'oldValue' => $l->old_value,
            'newValue' => $l->new_value,
            'changedAt' => $this->exportValue($l->created_at),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportFeeTemplates(string $clubId): array
    {
        return $this->clubRows($clubId, FeeChargeTemplate::class)->map(fn (FeeChargeTemplate $t) => array_filter([
            'id' => $this->exportId($t),
            'name' => $t->name,
            'amount' => $t->amount !== null ? (float) $t->amount : null,
            'appliesTo' => $t->applies_to,
            'autoGenerate' => (bool) $t->auto_generate,
            'isActive' => (bool) $t->is_active,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportTransactions(string $clubId): array
    {
        return AthleteTransaction::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->get()
            ->map(fn (AthleteTransaction $t) => array_filter([
                'id' => $this->exportId($t),
                'athleteId' => $this->mappedId('students', $t->athlete_id),
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'month' => $t->month,
                'year' => $t->year,
                'paymentMethod' => $t->payment_method,
                'receiptNumber' => $t->receipt_number,
                'receiptSeries' => $t->receipt_series,
                'receiptSeq' => $t->receipt_seq,
                'comments' => $t->comments,
                'allocatesChargeId' => $this->mappedId('transactions', $t->allocates_charge_id),
            ], fn ($v) => $v !== null))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportRevenues(string $clubId): array
    {
        return $this->clubRows($clubId, Revenue::class)->map(fn (Revenue $r) => array_filter([
            'id' => $this->exportId($r),
            'amount' => (float) $r->amount,
            'occurredOn' => $this->exportDate($r->occurred_on),
            'category' => $r->category,
            'description' => $r->description,
            'cashAccountId' => $this->mappedId('cashAccounts', $r->cash_account_id),
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportExpenses(string $clubId): array
    {
        return $this->clubRows($clubId, Expense::class)->map(fn (Expense $e) => array_filter([
            'id' => $this->exportId($e),
            'amount' => (float) $e->amount,
            'occurredOn' => $this->exportDate($e->occurred_on),
            'category' => $e->category,
            'description' => $e->description,
            'cashAccountId' => $this->mappedId('cashAccounts', $e->cash_account_id),
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportBudgets(string $clubId): array
    {
        return $this->clubRows($clubId, BudgetLine::class)->map(fn (BudgetLine $b) => array_filter([
            'id' => $this->exportId($b),
            'label' => $b->label,
            'year' => $b->year,
            'amount' => (float) $b->amount,
            'seasonStart' => data_get($b->meta, 'seasonStart', $b->year),
            'type' => data_get($b->meta, 'type'),
            'subcategory' => data_get($b->meta, 'subcategory'),
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportReceiptSeries(string $clubId): array
    {
        return $this->clubRows($clubId, ReceiptSeries::class)->map(fn (ReceiptSeries $s) => [
            'id' => $this->exportId($s),
            'series' => $s->series,
            'rangeFrom' => $s->range_from,
            'from' => $s->range_from,
            'rangeTo' => $s->range_to,
            'to' => $s->range_to,
            'nextNumber' => $s->next_number,
        ])->values()->all();
    }

    /**
     * @return array<string, int>
     */
    private function exportReceiptNextBySeries(string $clubId): array
    {
        $out = [];
        foreach ($this->clubRows($clubId, ReceiptSeries::class) as $s) {
            /** @var ReceiptSeries $s */
            $out[(string) $s->series] = (int) $s->next_number;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportReceiptIssues(string $clubId): array
    {
        return $this->clubRows($clubId, ReceiptIssue::class)->map(fn (ReceiptIssue $i) => array_filter([
            'id' => $this->exportId($i),
            'series' => $i->series,
            'number' => $i->number,
            'status' => $i->status,
            'issuedAt' => $this->exportValue($i->issued_at),
            'transactionId' => $this->mappedId('transactions', $i->athlete_transaction_id),
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportOnlineCheckouts(string $clubId): array
    {
        return $this->clubRows($clubId, OnlineCheckout::class)->map(fn (OnlineCheckout $c) => array_filter([
            'id' => $this->exportId($c),
            'provider' => $c->provider,
            'orderCode' => $c->order_code,
            'status' => $c->status,
            'amount' => (float) $c->amount,
            'idempotencyKey' => $c->idempotency_key,
            'athleteId' => $this->mappedId('students', $c->athlete_id),
            'payload' => is_array($c->payload) ? $c->payload : null,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportDiscountReasons(string $clubId): array
    {
        return $this->clubRows($clubId, DiscountReason::class)->map(fn (DiscountReason $d) => array_filter([
            'id' => $this->exportId($d),
            'name' => $d->name,
            'sport' => $d->sport,
            'defaultAmount' => $d->default_amount !== null ? (float) $d->default_amount : null,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportProducts(string $clubId): array
    {
        return $this->clubRows($clubId, WarehouseProduct::class)->map(fn (WarehouseProduct $p) => array_filter([
            'id' => $this->exportId($p),
            'name' => $p->name,
            'sku' => $p->sku,
            'unitPrice' => $p->unit_price !== null ? (float) $p->unit_price : null,
            'salePrice' => $p->unit_price !== null ? (float) $p->unit_price : null,
            'stockQty' => (int) $p->stock_qty,
            'lowStockThreshold' => $p->low_stock_threshold,
            'minStock' => $p->low_stock_threshold,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportStockMovements(string $clubId): array
    {
        return $this->clubRows($clubId, StockMovement::class)->map(fn (StockMovement $m) => array_filter([
            'id' => $this->exportId($m),
            'productId' => $this->mappedId('products', $m->warehouse_product_id),
            'quantity' => (int) $m->quantity,
            'reason' => $m->reason,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportClothingPackages(string $clubId): array
    {
        return $this->clubRows($clubId, ClothingPackage::class)->map(fn (ClothingPackage $p) => [
            'id' => $this->exportId($p),
            'name' => $p->name,
            'price' => $p->price !== null ? (float) $p->price : null,
        ])->values()->all();
    }

    private function exportSizeChart(string $clubId): ?array
    {
        $row = SizeChart::query()->withoutGlobalScopes()->where('club_id', $clubId)->first();
        if (! $row) {
            return null;
        }

        $chart = is_array($row->chart) ? $row->chart : [];

        return $chart !== [] ? $chart : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportRentalBookings(string $clubId): array
    {
        return $this->clubRows($clubId, RentalBooking::class)->map(fn (RentalBooking $b) => array_filter([
            'id' => $this->exportId($b),
            'facilityId' => $this->mappedId('facilities', $b->facility_id),
            'startsAt' => $this->exportValue($b->starts_at),
            'endsAt' => $this->exportValue($b->ends_at),
            'customerName' => $b->customer_name,
            'customerEmail' => $b->customer_email,
            'customerPhone' => $b->customer_phone,
            'status' => $b->status,
            'amount' => $b->amount !== null ? (float) $b->amount : null,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportAnnouncements(string $clubId): array
    {
        $roleMap = [
            'parent' => 'parents',
            'athlete' => 'athletes',
            'coach' => 'coaches',
            'staff' => 'staff',
        ];

        return Announcement::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Announcement $a) use ($roleMap) {
                $audience = $a->displayAudience();
                $roles = array_values(array_filter(array_map(
                    fn ($r) => $roleMap[$r] ?? null,
                    $audience['roles'] ?? []
                )));
                $classIds = array_map(
                    fn ($classId) => $this->mappedId('classes', $classId) ?? (string) $classId,
                    $audience['class_ids'] ?? []
                );
                $stats = $a->emailStats();
                $imageUrl = $a->imageUrl();
                if (is_string($imageUrl) && str_starts_with($imageUrl, '/')) {
                    $imageUrl = url($imageUrl);
                }

                return array_filter([
                    'id' => $this->exportId($a),
                    'title' => $a->title,
                    'message' => $a->displayBody(),
                    'createdAt' => $this->exportValue($a->created_at),
                    'updatedAt' => $this->exportValue($a->updated_at),
                    'createdBy' => $a->creatorDisplayName(),
                    'status' => $a->published_at ? 'published' : 'draft',
                    'publishedAt' => $this->exportValue($a->published_at),
                    'audienceRoles' => $roles,
                    'classIds' => $classIds,
                    'emailStats' => $stats,
                    'imageUrl' => $imageUrl,
                    'priority' => data_get($a->meta, 'priority'),
                    'highPriority' => (bool) data_get($a->meta, 'highPriority', false),
                    'sportCategories' => $audience['sport'] ?: data_get($a->meta, 'sportCategories'),
                ], fn ($v) => $v !== null && $v !== [] && $v !== false);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportPhotos(string $clubId): array
    {
        return $this->clubRows($clubId, GalleryPhoto::class)->map(function (GalleryPhoto $p) {
            $url = $p->path
                ? (str_starts_with((string) $p->path, 'http') ? $p->path : Storage::disk('public')->url($p->path))
                : data_get($p->meta, 'imageUrl');

            return array_filter([
                'id' => $this->exportId($p),
                'title' => $p->title,
                'caption' => $p->title,
                'path' => $p->path,
                'imageUrl' => $url,
                'gdprCleared' => (bool) $p->gdpr_cleared,
            ], fn ($v) => $v !== null);
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportPartnerBusinesses(string $clubId): array
    {
        return $this->clubRows($clubId, PartnerBusiness::class)->map(fn (PartnerBusiness $b) => [
            'id' => $this->exportId($b),
            'name' => $b->name,
            'contact' => $b->contact,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportPartnerOffers(string $clubId): array
    {
        return $this->clubRows($clubId, PartnerOffer::class)->map(fn (PartnerOffer $o) => [
            'id' => $this->exportId($o),
            'title' => $o->title,
            'description' => $o->description,
            'partnerBusinessId' => $this->mappedId('partnerBusinesses', $o->partner_business_id),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportProgressReports(string $clubId): array
    {
        return $this->clubRows($clubId, ProgressReport::class)->map(fn (ProgressReport $r) => array_filter([
            'id' => $this->exportId($r),
            'athleteId' => $this->mappedId('students', $r->athlete_id),
            'coachId' => $this->mappedId('coaches', $r->coach_id),
            'reportDate' => $this->exportDate($r->report_date),
            'body' => $r->body,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportRegistrationApplications(string $clubId): array
    {
        return $this->clubRows($clubId, RegistrationApplication::class)->map(fn (RegistrationApplication $a) => array_filter([
            'id' => $this->exportId($a),
            'type' => $a->type,
            'status' => $a->status,
            'firstName' => $a->first_name,
            'lastName' => $a->last_name,
            'email' => $a->email,
            'phone' => $a->phone,
            'formSnapshot' => is_array($a->form_snapshot) ? $a->form_snapshot : null,
            'gdprAccepted' => (bool) $a->gdpr_accepted,
            'signaturePath' => $a->signature_path,
        ], fn ($v) => $v !== null))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportProtocolEntries(string $clubId): array
    {
        return $this->clubRows($clubId, DocumentProtocolEntry::class)->map(fn (DocumentProtocolEntry $e) => [
            'id' => $this->exportId($e),
            'direction' => $e->direction,
            'protocolNumber' => $e->protocol_number,
            'subject' => $e->subject,
            'title' => $e->subject,
            'documentDate' => $this->exportDate($e->document_date),
            'occurredOn' => $this->exportDate($e->document_date),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportAmkaLogs(string $clubId): array
    {
        return $this->clubRows($clubId, AmkaAccessLog::class)->map(fn (AmkaAccessLog $l) => [
            'id' => $this->exportId($l),
            'athleteId' => $this->mappedId('students', $l->athlete_id),
            'userId' => $l->user_id ? (string) $l->user_id : null,
            'action' => $l->action,
            'accessedAt' => $this->exportValue($l->created_at),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportGdprLogs(string $clubId): array
    {
        return $this->clubRows($clubId, GdprAuditLog::class)->map(fn (GdprAuditLog $l) => [
            'id' => $this->exportId($l),
            'action' => $l->action,
            'userId' => $l->user_id ? (string) $l->user_id : null,
            'meta' => is_array($l->meta) ? $l->meta : [],
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportEmailUnsubscribes(string $clubId): array
    {
        return $this->clubRows($clubId, EmailUnsubscribe::class)->map(fn (EmailUnsubscribe $u) => [
            'id' => $this->exportId($u),
            'email' => $u->email,
        ])->values()->all();
    }

    /**
     * @return list<string>
     */
    private function exportSuppressedKeys(string $clubId): array
    {
        return SuppressedFeeChargeKey::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->pluck('suppress_key')
            ->filter(fn ($k) => is_string($k) && $k !== '')
            ->values()
            ->all();
    }
}
