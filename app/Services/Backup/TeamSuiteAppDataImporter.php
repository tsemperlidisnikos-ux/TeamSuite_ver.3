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
use App\Models\WarehouseProduct;
use App\Services\Gdpr\AmkaService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamSuiteAppDataImporter
{
    /** @var array<string, string> */
    private array $idMaps = [];

    public function __construct(
        private readonly AmkaService $amka,
    ) {}

    /**
     * @param  array<string, mixed>  $appData
     * @return array<string, int>
     */
    public function import(string $clubId, array $appData, string $mode = 'merge'): array
    {
        if ($mode === 'replace') {
            $this->wipeClubAppData($clubId);
        }

        // Preserve user map set by restore before app-data import (parentLinks need it).
        $userMap = $this->idMaps['users'] ?? [];
        $this->idMaps = [];
        if ($userMap !== []) {
            $this->idMaps['users'] = $userMap;
        }

        $counts = [];
        $counts['sports'] = $this->importSimpleList($clubId, $appData['sports'] ?? [], Sport::class, ['name' => 'name']);
        $counts['clubSeasons'] = $this->importClubSeasons($clubId, $appData['clubSeasons'] ?? []);
        $counts['associations'] = $this->importSimpleList($clubId, $appData['associations'] ?? [], Association::class, ['name' => 'name']);
        $counts['cashAccounts'] = $this->importCashAccounts($clubId, $appData['cashAccounts'] ?? []);
        $counts['facilities'] = $this->importFacilities($clubId, $appData['facilities'] ?? []);
        $this->importRentalSettings($clubId, $appData['rentalSettings'] ?? null);
        $counts['coaches'] = $this->importCoaches($clubId, $appData['coaches'] ?? []);
        $counts['staff'] = $this->importStaff($clubId, $appData['staff'] ?? []);
        $counts['clubMembers'] = $this->importClubMembers($clubId, $appData['clubMembers'] ?? []);
        $counts['classes'] = $this->importClasses($clubId, $appData['classes'] ?? []);
        $counts['students'] = $this->importStudents($clubId, $appData['students'] ?? []);
        $counts['parentLinks'] = $this->importParentLinks($clubId, $appData['parentLinks'] ?? []);
        $counts['schedule'] = $this->importSchedule($clubId, $appData['schedule'] ?? []);
        $counts['trainings'] = $this->importTrainings($clubId, $appData['trainings'] ?? []);
        $counts['attendance'] = $this->importAttendance($clubId, $appData['attendance'] ?? []);
        $counts['matches'] = $this->importMatches($clubId, $appData['matches'] ?? []);
        $counts['athleteChangeLogs'] = $this->importAthleteChangeLogs($clubId, $appData['athleteChangeLogs'] ?? []);
        $counts['feeChargeTemplates'] = $this->importFeeTemplates($clubId, $appData['feeChargeTemplates'] ?? []);
        $counts['transactions'] = $this->importTransactions($clubId, $appData['transactions'] ?? []);
        $counts['revenues'] = $this->importRevenues($clubId, $appData['revenues'] ?? []);
        $counts['expenses'] = $this->importExpenses($clubId, $appData['expenses'] ?? []);
        $counts['budgets'] = $this->importBudgets($clubId, $appData['budgets'] ?? []);
        $counts['receiptNumberRanges'] = $this->importReceiptSeries(
            $clubId,
            $appData['receiptNumberRanges'] ?? [],
            is_array($appData['receiptNextBySeries'] ?? null) ? $appData['receiptNextBySeries'] : []
        );
        $counts['receiptIssues'] = $this->importReceiptIssues($clubId, $appData['receiptIssues'] ?? []);
        $counts['onlineCheckouts'] = $this->importOnlineCheckouts($clubId, $appData['onlineCheckouts'] ?? []);
        $counts['discountReasons'] = $this->importDiscountReasons($clubId, $appData['discountReasons'] ?? []);
        $counts['products'] = $this->importProducts($clubId, $appData['products'] ?? []);
        $counts['stockMovements'] = $this->importStockMovements($clubId, $appData['stockMovements'] ?? []);
        $counts['clothingPackages'] = $this->importClothingPackages($clubId, $appData['clothingPackages'] ?? []);
        $counts['sizeChart'] = $this->importSizeChart($clubId, $appData['sizeChart'] ?? null);
        $counts['rentalBookings'] = $this->importRentalBookings($clubId, $appData['rentalBookings'] ?? []);
        $counts['announcements'] = $this->importAnnouncements($clubId, $appData['announcements'] ?? []);
        $counts['photos'] = $this->importPhotos($clubId, $appData['photos'] ?? []);
        $counts['partnerBusinesses'] = $this->importPartnerBusinesses($clubId, $appData['partnerBusinesses'] ?? []);
        $counts['partnerOffers'] = $this->importPartnerOffers($clubId, $appData['partnerOffers'] ?? []);
        $counts['progressReports'] = $this->importProgressReports($clubId, $appData['progressReports'] ?? []);
        $counts['registrationApplications'] = $this->importRegistrationApplications($clubId, $appData['registrationApplications'] ?? []);
        $counts['documentProtocolEntries'] = $this->importProtocolEntries($clubId, $appData['documentProtocolEntries'] ?? []);
        $counts['amkaAccessLogs'] = $this->importAmkaLogs($clubId, $appData['amkaAccessLogs'] ?? []);
        $counts['gdprAuditLogs'] = $this->importGdprLogs($clubId, $appData['gdprAuditLogs'] ?? []);
        $counts['emailUnsubscribes'] = $this->importEmailUnsubscribes($clubId, $appData['emailUnsubscribes'] ?? []);
        $counts['suppressedFeeChargeKeys'] = $this->importSuppressedKeys($clubId, $appData['suppressedFeeChargeKeys'] ?? []);

        return $counts;
    }

    /** @param  array<string, int|string>  $userIdMap legacy user id → users.id */
    public function setUserIdMap(array $userIdMap): void
    {
        $this->idMaps['users'] = $userIdMap;
    }

    /**
     * Re-run parent↔athlete links after users are imported (e.g. repair of an older restore).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function reimportParentLinks(string $clubId, array $rows): int
    {
        $this->idMaps['students'] = Athlete::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereNotNull('legacy_id')
            ->pluck('id', 'legacy_id')
            ->all();

        return $this->importParentLinks($clubId, $rows);
    }

    private function wipeClubAppData(string $clubId): void
    {
        // Detach athlete↔class pivots before hard-deleting parents (faster than cascade thrash).
        $athleteIds = Athlete::query()->withoutGlobalScopes()->withTrashed()
            ->where('club_id', $clubId)->pluck('id');
        if ($athleteIds->isNotEmpty()) {
            DB::table('academy_class_athlete')->whereIn('athlete_id', $athleteIds)->delete();
        }

        $models = [
            AmkaAccessLog::class,
            GdprAuditLog::class,
            AthleteChangeLog::class,
            Attendance::class,
            ProgressReport::class,
            ParentAthleteLink::class,
            ReceiptIssue::class,
            AthleteTransaction::class,
            StockMovement::class,
            PartnerOffer::class,
            ScheduleSlot::class,
            Training::class,
            SportMatch::class,
            RentalBooking::class,
            RegistrationApplication::class,
            DocumentProtocolEntry::class,
            GalleryPhoto::class,
            Announcement::class,
            OnlineCheckout::class,
            Athlete::class,
            AcademyClass::class,
            Coach::class,
            StaffMember::class,
            ClubMember::class,
            FeeChargeTemplate::class,
            Revenue::class,
            Expense::class,
            BudgetLine::class,
            CashAccount::class,
            ReceiptSeries::class,
            DiscountReason::class,
            WarehouseProduct::class,
            ClothingPackage::class,
            PartnerBusiness::class,
            Facility::class,
            Association::class,
            ClubSeason::class,
            Sport::class,
            EmailUnsubscribe::class,
            SuppressedFeeChargeKey::class,
        ];

        foreach ($models as $modelClass) {
            /** @var class-string<Model> $modelClass */
            $table = (new $modelClass)->getTable();
            $query = DB::table($table)->where('club_id', $clubId);
            $query->delete();
        }

        DB::table((new RentalSetting)->getTable())->where('club_id', $clubId)->delete();
        DB::table((new SizeChart)->getTable())->where('club_id', $clubId)->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importSimpleList(string $clubId, array $rows, string $modelClass, array $fieldMap): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, $fieldMap);
            $attrs['meta'] = $this->metaRemainder($row, array_keys($fieldMap));
            $this->upsert($modelClass, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importClubSeasons(string $clubId, array $rows): int
    {
        return $this->importSimpleList($clubId, $rows, ClubSeason::class, [
            'name' => 'name',
            'startsOn' => 'starts_on',
            'endsOn' => 'ends_on',
            'isActive' => 'is_active',
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importCashAccounts(string $clubId, array $rows): int
    {
        return $this->importSimpleList($clubId, $rows, CashAccount::class, [
            'name' => 'name',
            'openingBalance' => 'opening_balance',
            'isActive' => 'is_active',
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importFacilities(string $clubId, array $rows): int
    {
        return $this->importSimpleList($clubId, $rows, Facility::class, [
            'name' => 'name',
            'address' => 'address',
            'isRentable' => 'is_rentable',
        ]);
    }

    /** @param  array<string, mixed>|null  $settings */
    private function importRentalSettings(string $clubId, ?array $settings): void
    {
        if ($settings === null || $settings === []) {
            return;
        }

        RentalSetting::query()->withoutGlobalScopes()->updateOrCreate(
            ['club_id' => $clubId],
            [
                'public_enabled' => (bool) ($settings['publicEnabled'] ?? $settings['public_enabled'] ?? false),
                'rules' => $settings['rules'] ?? Arr::except($settings, ['publicEnabled', 'public_enabled']),
            ]
        );
    }

    /**
     * Legacy TeamSuite sizeChart: { kids: string[], men: string[], women: string[], groupLabels? }.
     * Some payloads use a flat string[] list of sizes.
     *
     * @return int 1 when a chart was stored, otherwise 0
     */
    private function importSizeChart(string $clubId, mixed $chart): int
    {
        if (! is_array($chart) || $chart === []) {
            return 0;
        }

        if (array_is_list($chart)) {
            $normalized = [
                'kids' => array_values(array_filter(array_map('strval', $chart), fn (string $s) => $s !== '')),
                'men' => [],
                'women' => [],
            ];
        } else {
            $normalized = [
                'kids' => array_values(array_filter(array_map('strval', $chart['kids'] ?? []), fn (string $s) => $s !== '')),
                'men' => array_values(array_filter(array_map('strval', $chart['men'] ?? []), fn (string $s) => $s !== '')),
                'women' => array_values(array_filter(array_map('strval', $chart['women'] ?? []), fn (string $s) => $s !== '')),
            ];
            if (isset($chart['groupLabels']) && is_array($chart['groupLabels'])) {
                $normalized['groupLabels'] = $chart['groupLabels'];
            }
        }

        if ($normalized['kids'] === [] && $normalized['men'] === [] && $normalized['women'] === []) {
            return 0;
        }

        SizeChart::query()->withoutGlobalScopes()->updateOrCreate(
            ['club_id' => $clubId],
            ['chart' => $normalized]
        );

        return 1;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importCoaches(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'firstName' => 'first_name',
                'lastName' => 'last_name',
                'email' => 'email',
                'phone' => 'phone',
                'sport' => 'sport',
                'hireDate' => 'hire_date',
                'isActive' => 'is_active',
                'ggaCode' => 'gga_code',
                'licenseLevel' => 'license_level',
            ]);
            $attrs['user_id'] = $this->resolveUserId($row['userId'] ?? null);
            $attrs['meta'] = $this->metaRemainder($row, ['id', 'firstName', 'lastName', 'email', 'phone', 'sport', 'hireDate', 'isActive', 'ggaCode', 'licenseLevel', 'userId']);
            $this->upsert(Coach::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importStaff(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'firstName' => 'first_name',
                'lastName' => 'last_name',
                'roleTitle' => 'role_title',
                'email' => 'email',
                'phone' => 'phone',
                'isActive' => 'is_active',
            ]);
            $attrs['user_id'] = $this->resolveUserId($row['userId'] ?? null);
            $attrs['meta'] = $this->metaRemainder($row, array_keys($attrs));
            $this->upsert(StaffMember::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importClubMembers(string $clubId, array $rows): int
    {
        return $this->importSimpleList($clubId, $rows, ClubMember::class, [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'email' => 'email',
            'phone' => 'phone',
            'memberNumber' => 'member_number',
            'status' => 'status',
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importClasses(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'name' => 'name',
                'sport' => 'sport',
                'ageGroup' => 'age_group',
                'gender' => 'gender',
                'maxStudents' => 'max_students',
                'monthlyFee' => 'monthly_fee',
                'scheduleSummary' => 'schedule_summary',
            ]);
            $attrs['coach_id'] = $this->resolveMap('coaches', $row['coachId'] ?? null);
            $attrs['club_season_id'] = $this->resolveMap('clubSeasons', $row['clubSeasonId'] ?? $row['seasonId'] ?? null);
            $attrs['meta'] = $this->metaRemainder($row, ['id', 'name', 'sport', 'ageGroup', 'gender', 'maxStudents', 'monthlyFee', 'scheduleSummary', 'coachId', 'clubSeasonId', 'seasonId']);
            $this->upsert(AcademyClass::class, $clubId, $row, $attrs, 'classes');
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importStudents(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'firstName' => 'first_name',
                'lastName' => 'last_name',
                'email' => 'email',
                'phone' => 'phone',
                'birthDate' => 'birth_date',
                'status' => 'status',
                'monthlyFee' => 'monthly_fee',
                'enrolledAt' => 'enrolled_at',
                'gender' => 'gender',
                'guardianName' => 'guardian_name',
                'guardianPhone' => 'guardian_phone',
                'sport' => 'sport',
                'gdprConsent' => 'gdpr_consent',
            ]);
            if (isset($row['healthCardStatus'])) {
                $attrs['health_card'] = $row['healthCardStatus'] !== 'none';
            }
            if (isset($row['healthCardExpires'])) {
                $attrs['health_card_expires'] = $this->parseDate($row['healthCardExpires']);
            }
            if (! empty($row['amka'])) {
                $attrs = array_merge($attrs, $this->amka->storeFromPlaintext((string) $row['amka']));
            } elseif (! empty($row['amkaFp'])) {
                $attrs['amka_fingerprint'] = $row['amkaFp'];
            }
            $attrs['meta'] = $this->normalizeAthleteMeta(
                $this->metaRemainder($row, ['id', 'firstName', 'lastName', 'email', 'phone', 'birthDate', 'status', 'monthlyFee', 'enrolledAt', 'gender', 'guardianName', 'guardianPhone', 'sport', 'gdprConsent', 'amka', 'amkaFp', 'healthCardStatus', 'healthCardExpires'])
            );
            $athlete = $this->upsert(Athlete::class, $clubId, $row, $attrs, 'students');
            $this->syncAthleteClasses($athlete, $row);
            $count++;
        }

        return $count;
    }

    /**
     * Map legacy TeamSuite camelCase student fields onto Athlete::META_KEYS (snake_case).
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function normalizeAthleteMeta(array $meta): array
    {
        $normalized = $meta;

        foreach (Athlete::META_KEYS as $snake) {
            $camel = Str::camel($snake);
            if (! array_key_exists($snake, $normalized) && array_key_exists($camel, $meta)) {
                $normalized[$snake] = $meta[$camel];
            }
            if (array_key_exists($snake, $normalized) && array_key_exists($camel, $normalized)) {
                unset($normalized[$camel]);
            }
        }

        return $normalized;
    }

    /**
     * Rebuild academy_class_athlete links from student classId / classIds (legacy or UUID).
     */
    public function repairAthleteClassLinks(string $clubId): int
    {
        $this->idMaps['classes'] = AcademyClass::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->whereNotNull('legacy_id')
            ->pluck('id', 'legacy_id')
            ->all();

        $linked = 0;
        Athlete::query()
            ->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->orderBy('id')
            ->chunkById(200, function ($athletes) use (&$linked) {
                foreach ($athletes as $athlete) {
                    $meta = $athlete->meta ?? [];
                    $before = $athlete->classes()->count();
                    $this->syncAthleteClasses($athlete, [
                        'classId' => $meta['classId'] ?? $meta['primary_class_id'] ?? null,
                        'classIds' => $meta['classIds'] ?? $meta['class_ids'] ?? [],
                    ]);
                    if ($athlete->classes()->count() > 0 || $before > 0) {
                        $linked++;
                    }
                }
            });

        return $linked;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function syncAthleteClasses(Athlete $athlete, array $row): void
    {
        $legacyIds = [];
        $fromList = $row['classIds'] ?? $row['class_ids'] ?? [];
        if (is_array($fromList)) {
            foreach ($fromList as $item) {
                if (is_string($item) || is_numeric($item)) {
                    $legacyIds[] = (string) $item;
                }
            }
        }
        $primaryLegacy = $row['classId'] ?? $row['primary_class_id'] ?? null;
        if (is_string($primaryLegacy) && $primaryLegacy !== '') {
            $legacyIds[] = $primaryLegacy;
        }

        $legacyIds = array_values(array_unique(array_filter($legacyIds, fn ($id) => $id !== '')));
        $resolved = [];
        foreach ($legacyIds as $legacyId) {
            $mapped = $this->resolveMap('classes', $legacyId);
            if ($mapped) {
                $resolved[] = $mapped;
            }
        }
        $resolved = array_values(array_unique($resolved));

        $primaryId = null;
        if (is_string($primaryLegacy) && $primaryLegacy !== '') {
            $primaryId = $this->resolveMap('classes', $primaryLegacy);
        }
        $primaryId ??= $resolved[0] ?? null;

        $sync = [];
        foreach ($resolved as $classId) {
            $sync[$classId] = ['is_primary' => $primaryId !== null && $classId === $primaryId];
        }
        $athlete->classes()->sync($sync);

        $meta = $athlete->meta ?? [];
        $meta['class_ids'] = $resolved;
        $meta['primary_class_id'] = $primaryId;
        $athlete->meta = $meta;
        $athlete->save();
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importParentLinks(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $athleteId = $this->resolveMap('students', $row['athleteId'] ?? $row['studentId'] ?? null);
            $parentUserId = $this->resolveUserId($row['parentUserId'] ?? $row['userId'] ?? null);
            if (! $athleteId || ! $parentUserId) {
                continue;
            }
            $attrs = [
                'parent_user_id' => $parentUserId,
                'athlete_id' => $athleteId,
                'relation' => $row['relation'] ?? 'guardian',
                'receives_push' => (bool) ($row['receivesPush'] ?? true),
                'receives_sms' => (bool) ($row['receivesSms'] ?? true),
            ];
            $this->upsert(ParentAthleteLink::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importSchedule(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            // Legacy TeamSuite: dayOfWeek + startTime/endTime + location (not startsAt/title).
            $weekday = $row['weekday'] ?? $row['dayOfWeek'] ?? null;
            $startsAt = $this->parseTimeOnly($row['startsAt'] ?? $row['startTime'] ?? null);
            $endsAt = $this->parseTimeOnly($row['endsAt'] ?? $row['endTime'] ?? null);
            $title = $row['title'] ?? $row['location'] ?? null;

            $attrs = array_filter([
                'weekday' => is_numeric($weekday) ? (int) $weekday : null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'title' => is_string($title) ? $title : null,
            ], fn ($v) => $v !== null);

            $attrs['academy_class_id'] = $this->resolveMap('classes', $row['classId'] ?? null);
            $attrs['facility_id'] = $this->resolveMap('facilities', $row['facilityId'] ?? null);
            $attrs['meta'] = $this->metaRemainder($row, [
                'id', 'weekday', 'dayOfWeek', 'startsAt', 'endsAt', 'startTime', 'endTime',
                'title', 'location', 'classId', 'facilityId',
            ]);
            $this->upsert(ScheduleSlot::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importTrainings(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            // Legacy TeamSuite Training: date + startTime/endTime + location (+ classId).
            // Newer payloads may already use startsAt/endsAt/title/facilityId.
            $startsAt = $this->combineLegacyDateTime(
                $row['startsAt'] ?? null,
                $row['date'] ?? null,
                $row['startTime'] ?? null,
            );
            $endsAt = $this->combineLegacyDateTime(
                $row['endsAt'] ?? null,
                $row['date'] ?? null,
                $row['endTime'] ?? null,
            );

            $location = $row['location'] ?? null;
            if (! is_string($location) || $location === '') {
                $location = is_string($row['title'] ?? null) ? $row['title'] : null;
            }

            $classId = $this->resolveMap('classes', $row['classId'] ?? null);
            $attrs = [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'title' => $location,
                'status' => $row['status'] ?? 'scheduled',
                'notes' => $row['notes'] ?? null,
                'academy_class_id' => $classId,
                'coach_id' => $this->resolveMap('coaches', $row['coachId'] ?? null),
                'facility_id' => $this->resolveMap('facilities', $row['facilityId'] ?? null),
            ];

            $meta = $this->metaRemainder($row, [
                'id', 'startsAt', 'endsAt', 'date', 'startTime', 'endTime', 'title', 'location',
                'status', 'notes', 'classId', 'coachId', 'facilityId',
            ]);
            if ($location) {
                $meta['location'] = $location;
            }
            if ($classId) {
                $class = AcademyClass::query()->withoutGlobalScopes()
                    ->whereKey($classId)
                    ->first(['id', 'name', 'sport']);
                if ($class) {
                    $meta['class_name'] = $class->name;
                    if ($class->sport) {
                        $meta['sport'] = $class->sport;
                    }
                }
            }
            $attrs['meta'] = $meta;

            $this->upsert(Training::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importAttendance(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $athleteId = $this->resolveMap(
                'students',
                $row['athleteId'] ?? $row['studentId'] ?? null
            );
            if (! $athleteId) {
                continue;
            }

            $classId = $this->resolveMap('classes', $row['classId'] ?? null);
            $attendedOn = $this->parseDate($row['attendedOn'] ?? $row['date'] ?? null);
            if (! $attendedOn instanceof Carbon && is_string($attendedOn) && $attendedOn !== '') {
                try {
                    $attendedOn = Carbon::parse($attendedOn);
                } catch (\Throwable) {
                    $attendedOn = null;
                }
            }

            $trainingId = $this->resolveMap('trainings', $row['trainingId'] ?? null);
            if (! $trainingId && $classId && $attendedOn instanceof Carbon) {
                $trainingId = $this->findTrainingIdForClassDate($clubId, $classId, $attendedOn);
            }

            // Legacy rows use boolean `present` (+ optional absenceReason), not status/trainingId.
            if (isset($row['status']) && is_string($row['status']) && $row['status'] !== '') {
                $status = $row['status'];
            } elseif (array_key_exists('present', $row)) {
                $present = $row['present'];
                $isPresent = $present === true || $present === 1 || $present === '1' || $present === 'true';
                $status = $isPresent
                    ? 'present'
                    : (! empty($row['absenceReason']) ? 'excused' : 'absent');
            } else {
                $status = 'present';
            }

            $meta = $this->metaRemainder($row, [
                'id', 'status', 'present', 'attendedOn', 'date', 'trainingId',
                'athleteId', 'studentId', 'classId', 'notes', 'absenceReason',
            ]);
            if ($classId) {
                $meta['class_id'] = $classId;
            }
            if (! empty($row['notes'])) {
                $meta['notes'] = $row['notes'];
            }
            if (! empty($row['absenceReason'])) {
                $meta['absence_reason'] = $row['absenceReason'];
            }

            $attrs = [
                'status' => $status,
                'attended_on' => $attendedOn instanceof Carbon ? $attendedOn->toDateString() : $attendedOn,
                'training_id' => $trainingId,
                'athlete_id' => $athleteId,
                'meta' => $meta,
            ];

            $this->upsert(Attendance::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    private function findTrainingIdForClassDate(string $clubId, string $classId, Carbon $date): ?string
    {
        $day = $date->toDateString();
        $cacheKey = $classId.'|'.$day;
        if (array_key_exists($cacheKey, $this->idMaps['trainingByClassDate'] ?? [])) {
            return $this->idMaps['trainingByClassDate'][$cacheKey];
        }

        $id = Training::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('academy_class_id', $classId)
            ->whereDate('starts_at', $day)
            ->orderBy('starts_at')
            ->value('id');

        $this->idMaps['trainingByClassDate'][$cacheKey] = $id;

        return $id;
    }

    /**
     * Build datetime from legacy date+time or an already-combined ISO value.
     */
    private function combineLegacyDateTime(mixed $combined, mixed $date, mixed $time): ?Carbon
    {
        if ($combined !== null && $combined !== '') {
            $parsed = $this->parseDate($combined);

            return $parsed instanceof Carbon ? $parsed : null;
        }

        if ($date === null || $date === '') {
            return null;
        }

        $timePart = is_string($time) && trim($time) !== '' ? trim($time) : '00:00';
        if (preg_match('/^\d{1,2}:\d{2}$/', $timePart)) {
            $timePart .= ':00';
        }

        try {
            return Carbon::parse(trim((string) $date).' '.$timePart);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseTimeOnly(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('H:i:s');
        }

        $raw = trim((string) $value);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
        }

        try {
            return Carbon::parse($raw)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importMatches(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'startsAt' => 'starts_at',
                'opponent' => 'opponent',
                'venue' => 'venue',
                'homeAway' => 'home_away',
                'scoreFor' => 'score_for',
                'scoreAgainst' => 'score_against',
                'status' => 'status',
            ]);
            $attrs['academy_class_id'] = $this->resolveMap('classes', $row['classId'] ?? null);
            $attrs['meta'] = $this->metaRemainder($row, ['id', 'startsAt', 'opponent', 'venue', 'homeAway', 'scoreFor', 'scoreAgainst', 'status', 'classId']);
            $this->upsert(SportMatch::class, $clubId, $row, $attrs, 'matches');
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importAthleteChangeLogs(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $athleteId = $this->resolveMap(
                'students',
                $row['athleteId'] ?? $row['studentId'] ?? $row['athlete_id'] ?? null
            );
            if (! $athleteId) {
                continue;
            }

            $userId = $this->resolveUserId($row['userId'] ?? $row['byUserId'] ?? null);
            $changes = $row['changes'] ?? null;

            // Legacy TeamSuite: one log row may contain multiple field changes.
            if (is_array($changes) && $changes !== []) {
                foreach ($changes as $i => $change) {
                    if (! is_array($change)) {
                        continue;
                    }
                    $legacyId = isset($row['id']) ? ((string) $row['id']).':'.$i : null;
                    $this->upsert(AthleteChangeLog::class, $clubId, ['id' => $legacyId], [
                        'athlete_id' => $athleteId,
                        'user_id' => $userId,
                        'field' => (string) ($change['field'] ?? 'change'),
                        'old_value' => isset($change['from']) ? (string) $change['from'] : (isset($change['oldValue']) ? (string) $change['oldValue'] : null),
                        'new_value' => isset($change['to']) ? (string) $change['to'] : (isset($change['newValue']) ? (string) $change['newValue'] : null),
                    ]);
                    $count++;
                }

                continue;
            }

            $attrs = $this->mapFields($row, [
                'field' => 'field',
                'oldValue' => 'old_value',
                'newValue' => 'new_value',
            ]);
            $attrs['athlete_id'] = $athleteId;
            $attrs['user_id'] = $userId;
            if (empty($attrs['field'])) {
                $attrs['field'] = 'change';
            }
            $this->upsert(AthleteChangeLog::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importFeeTemplates(string $clubId, array $rows): int
    {
        return $this->importSimpleList($clubId, $rows, FeeChargeTemplate::class, [
            'name' => 'name',
            'amount' => 'amount',
            'appliesTo' => 'applies_to',
            'autoGenerate' => 'auto_generate',
            'isActive' => 'is_active',
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importTransactions(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'type' => 'type',
                'amount' => 'amount',
                'month' => 'month',
                'year' => 'year',
                'paymentMethod' => 'payment_method',
                'receiptNumber' => 'receipt_number',
                'receiptSeries' => 'receipt_series',
                'receiptSeq' => 'receipt_seq',
                'comments' => 'comments',
            ]);
            $attrs['athlete_id'] = $this->resolveMap('students', $row['athleteId'] ?? $row['studentId'] ?? null);
            if (! $attrs['athlete_id']) {
                continue;
            }
            $attrs['allocates_charge_id'] = $this->resolveMap('transactions', $row['allocatesChargeId'] ?? null);
            $attrs['meta'] = $this->metaRemainder($row, ['id', 'type', 'amount', 'month', 'year', 'paymentMethod', 'receiptNumber', 'receiptSeries', 'receiptSeq', 'comments', 'athleteId', 'studentId', 'allocatesChargeId', 'createdAt', 'updatedAt']);
            $this->upsert(AthleteTransaction::class, $clubId, $row, $attrs, 'transactions');
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importRevenues(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'amount' => 'amount',
                'category' => 'category',
                'occurredOn' => 'occurred_on',
                'description' => 'description',
            ]);
            $attrs['cash_account_id'] = $this->resolveMap('cashAccounts', $row['cashAccountId'] ?? null);
            $attrs['meta'] = $this->metaRemainder($row, ['id', 'amount', 'category', 'occurredOn', 'description', 'cashAccountId']);
            $this->upsert(Revenue::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importExpenses(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'amount' => 'amount',
                'category' => 'category',
                'occurredOn' => 'occurred_on',
                'description' => 'description',
            ]);
            $attrs['cash_account_id'] = $this->resolveMap('cashAccounts', $row['cashAccountId'] ?? null);
            $attrs['meta'] = $this->metaRemainder($row, ['id', 'amount', 'category', 'occurredOn', 'description', 'cashAccountId']);
            $this->upsert(Expense::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importBudgets(string $clubId, array $rows): int
    {
        return $this->importSimpleList($clubId, $rows, BudgetLine::class, [
            'label' => 'label',
            'year' => 'year',
            'amount' => 'amount',
        ]);
    }

    /**
     * Legacy TeamSuite ReceiptNumberRange uses from/to (not rangeFrom/rangeTo).
     * Next counter lives in appData.receiptNextBySeries[series].
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $nextBySeries
     */
    private function importReceiptSeries(string $clubId, array $rows, array $nextBySeries = []): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $series = trim((string) ($row['series'] ?? ''));
            $rangeFrom = (int) ($row['rangeFrom'] ?? $row['from'] ?? 1);
            if ($rangeFrom < 1) {
                $rangeFrom = 1;
            }

            $rangeToRaw = $row['rangeTo'] ?? $row['to'] ?? null;
            $rangeTo = ($rangeToRaw === null || $rangeToRaw === '') ? null : (int) $rangeToRaw;

            $nextNumber = $row['nextNumber'] ?? $row['next_number'] ?? null;
            if ($nextNumber === null && $series !== '') {
                $nextNumber = $this->receiptNextForSeries($series, $nextBySeries);
            }
            if ($nextNumber === null) {
                $nextNumber = $rangeFrom;
            }

            $attrs = [
                'series' => $series !== '' ? $series : 'Α',
                'range_from' => $rangeFrom,
                'range_to' => $rangeTo,
                'next_number' => (int) $nextNumber,
                'is_active' => (bool) ($row['isActive'] ?? $row['is_active'] ?? true),
            ];
            $this->upsert(ReceiptSeries::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /**
     * Legacy TeamSuite ReceiptIssueRecord uses voidedAt (not cancelledAt) and has no status.
     * Snapshot fields (amount, receivedFrom, emailedAt, …) are stored in meta.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importReceiptIssues(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $number = (int) ($row['number'] ?? 0);
            if ($number < 1) {
                continue;
            }

            $voidedAt = $row['voidedAt'] ?? $row['cancelledAt'] ?? null;
            $status = $row['status'] ?? null;
            if (! is_string($status) || $status === '') {
                $status = filled($voidedAt) ? 'cancelled' : 'issued';
            }

            $attrs = $this->mapFields($row, [
                'series' => 'series',
                'number' => 'number',
                'issuedAt' => 'issued_at',
            ]);
            $attrs['number'] = $number;
            $attrs['status'] = $status === 'cancelled' || $status === 'voided' ? 'cancelled' : 'issued';
            $attrs['cancelled_at'] = $this->parseDate($voidedAt);

            $attrs['receipt_series_id'] = $this->resolveMap('receiptNumberRanges', $row['receiptSeriesId'] ?? null)
                ?? $this->resolveReceiptSeriesByName($clubId, $attrs['series'] ?? $row['series'] ?? null, $number);
            $attrs['athlete_transaction_id'] = $this->resolveMap('transactions', $row['transactionId'] ?? null);

            $meta = $this->metaRemainder($row, [
                'id', 'series', 'number', 'status', 'issuedAt', 'cancelledAt', 'voidedAt',
                'receiptSeriesId', 'transactionId',
            ]);
            // Prefer camelCase snapshot keys used by legacy UI / future registry.
            foreach (['amount', 'receivedFrom', 'reason', 'kind', 'emailedAt', 'athleteId', 'voidReason'] as $key) {
                if (array_key_exists($key, $row)) {
                    $meta[$key] = $row[$key];
                }
            }
            $attrs['meta'] = $meta;

            $this->upsert(ReceiptIssue::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  array<string, mixed>  $nextBySeries */
    private function receiptNextForSeries(string $series, array $nextBySeries): ?int
    {
        foreach ([$series, mb_strtoupper($series, 'UTF-8'), mb_strtolower($series, 'UTF-8')] as $key) {
            if (array_key_exists($key, $nextBySeries) && is_numeric($nextBySeries[$key])) {
                return (int) $nextBySeries[$key];
            }
        }

        return null;
    }

    private function resolveReceiptSeriesByName(string $clubId, mixed $series, ?int $number = null): ?string
    {
        $series = trim((string) $series);
        if ($series === '') {
            return null;
        }

        $query = ReceiptSeries::query()->withoutGlobalScopes()
            ->where('club_id', $clubId)
            ->where('series', $series)
            ->orderBy('range_from');

        if ($number !== null && $number > 0) {
            $match = (clone $query)
                ->where('range_from', '<=', $number)
                ->where(function ($q) use ($number): void {
                    $q->whereNull('range_to')->orWhere('range_to', '>=', $number);
                })
                ->first();
            if ($match) {
                return $match->id;
            }
        }

        return $query->first()?->id;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importOnlineCheckouts(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'provider' => 'provider',
                'orderCode' => 'order_code',
                'amount' => 'amount',
                'status' => 'status',
                'idempotencyKey' => 'idempotency_key',
            ]);
            $attrs['athlete_id'] = $this->resolveMap('students', $row['athleteId'] ?? null);
            $attrs['payload'] = $row['payload'] ?? $this->metaRemainder($row, ['id', 'provider', 'orderCode', 'amount', 'status', 'idempotencyKey', 'athleteId']);
            $this->upsert(OnlineCheckout::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importDiscountReasons(string $clubId, array $rows): int
    {
        return $this->importSimpleList($clubId, $rows, DiscountReason::class, [
            'name' => 'name',
            'sport' => 'sport',
            'defaultAmount' => 'default_amount',
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importProducts(string $clubId, array $rows): int
    {
        return $this->importSimpleList($clubId, $rows, WarehouseProduct::class, [
            'name' => 'name',
            'sku' => 'sku',
            'stockQty' => 'stock_qty',
            'lowStockThreshold' => 'low_stock_threshold',
            'unitPrice' => 'unit_price',
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importStockMovements(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'quantity' => 'quantity',
                'reason' => 'reason',
            ]);
            $attrs['warehouse_product_id'] = $this->resolveMap('products', $row['productId'] ?? null);
            if (! $attrs['warehouse_product_id']) {
                continue;
            }
            $this->upsert(StockMovement::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importClothingPackages(string $clubId, array $rows): int
    {
        return $this->importSimpleList($clubId, $rows, ClothingPackage::class, [
            'name' => 'name',
            'price' => 'price',
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importRentalBookings(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'customerName' => 'customer_name',
                'customerEmail' => 'customer_email',
                'customerPhone' => 'customer_phone',
                'startsAt' => 'starts_at',
                'endsAt' => 'ends_at',
                'amount' => 'amount',
                'status' => 'status',
            ]);

            // Legacy TeamSuite often uses date + startTime/endTime instead of startsAt/endsAt
            if (empty($attrs['starts_at']) && ! empty($row['date'])) {
                $startTime = $row['startTime'] ?? $row['startsAtTime'] ?? '00:00';
                $endTime = $row['endTime'] ?? $row['endsAtTime'] ?? $startTime;
                $attrs['starts_at'] = $this->parseDate(trim($row['date'].' '.$startTime));
                $attrs['ends_at'] = $this->parseDate(trim($row['date'].' '.$endTime));
            }

            if (empty($attrs['customer_name'])) {
                $attrs['customer_name'] = trim(($row['createdByName'] ?? '').'') ?: 'Πελάτης';
            }
            if (empty($attrs['starts_at'])) {
                continue;
            }

            $attrs['facility_id'] = $this->resolveMap('facilities', $row['facilityId'] ?? null);
            $attrs['meta'] = $this->metaRemainder($row, [
                'id', 'customerName', 'customerEmail', 'customerPhone', 'startsAt', 'endsAt',
                'amount', 'status', 'facilityId', 'date', 'startTime', 'endTime',
            ]);
            $this->upsert(RentalBooking::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importAnnouncements(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $body = trim((string) ($row['body'] ?? $row['message'] ?? ''));
            $status = strtolower(trim((string) ($row['status'] ?? 'published')));
            $publishedAt = $this->parseDate($row['publishedAt'] ?? null);
            if (! $publishedAt instanceof Carbon && $status !== 'draft') {
                $publishedAt = $this->parseDate($row['visibleFrom'] ?? $row['createdAt'] ?? null);
            }
            if ($status === 'draft') {
                $publishedAt = null;
            }

            $createdByRaw = trim((string) ($row['createdBy'] ?? ''));
            $createdById = $this->resolveUserId($row['createdBy'] ?? null);

            $usedKeys = [
                'id', 'title', 'body', 'message', 'audience', 'audienceRoles', 'classIds',
                'recipientIds', 'publishedAt', 'createdBy', 'status', 'visibleFrom',
                'visibleUntil', 'imageUrl', 'emailStats', 'createdAt', 'updatedAt',
                'targetType', 'targetId', 'highPriority', 'priority', 'showTo',
                'sportCategories', 'teamsLabel', 'customChargeFilter', 'clothingPackageId',
            ];
            $meta = $this->metaRemainder($row, $usedKeys);

            if ($createdByRaw !== '' && $createdById === null) {
                $meta['created_by_name'] = $createdByRaw;
            }
            if (! empty($row['imageUrl']) && is_string($row['imageUrl'])) {
                $meta['imageUrl'] = $row['imageUrl'];
            }
            if (! empty($row['emailStats']) && is_array($row['emailStats'])) {
                $meta['email_stats'] = $this->normalizeLegacyEmailStats($row['emailStats']);
            }
            foreach (['priority', 'highPriority', 'visibleFrom', 'visibleUntil', 'sportCategories', 'teamsLabel', 'customChargeFilter', 'clothingPackageId', 'showTo', 'targetType', 'targetId'] as $legacyKey) {
                if (array_key_exists($legacyKey, $row) && $row[$legacyKey] !== null && $row[$legacyKey] !== '') {
                    $meta[$legacyKey] = $row[$legacyKey];
                }
            }

            $attrs = [
                'title' => trim((string) ($row['title'] ?? '')) ?: 'Χωρίς τίτλο',
                'body' => $body,
                'audience' => $this->mapLegacyAnnouncementAudience($row),
                'published_at' => $publishedAt,
                'created_by' => $createdById,
                'meta' => $meta,
            ];
            $this->upsert(Announcement::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     roles: list<string>,
     *     class_ids: list<string>,
     *     sport: string,
     *     parent_ids: list<string>,
     *     coach_ids: list<string>,
     *     athlete_ids: list<string>,
     *     staff_ids: list<string>
     * }
     */
    private function mapLegacyAnnouncementAudience(array $row): array
    {
        if (isset($row['audience']) && is_array($row['audience']) && $row['audience'] !== []) {
            $existing = $row['audience'];

            return [
                'roles' => array_values(array_map('strval', $existing['roles'] ?? [])),
                'class_ids' => array_values(array_filter(array_map(
                    fn ($id) => $this->resolveMap('classes', $id),
                    $existing['class_ids'] ?? $existing['classIds'] ?? []
                ))),
                'sport' => trim((string) ($existing['sport'] ?? $row['sportCategories'] ?? '')),
                'parent_ids' => array_values(array_map('strval', $existing['parent_ids'] ?? [])),
                'coach_ids' => array_values(array_map('strval', $existing['coach_ids'] ?? [])),
                'athlete_ids' => array_values(array_map('strval', $existing['athlete_ids'] ?? [])),
                'staff_ids' => array_values(array_map('strval', $existing['staff_ids'] ?? [])),
            ];
        }

        $roleMap = [
            'parents' => 'parent',
            'parent' => 'parent',
            'athletes' => 'athlete',
            'athlete' => 'athlete',
            'coaches' => 'coach',
            'coach' => 'coach',
            'staff' => 'staff',
            'members' => 'member',
            'member' => 'member',
        ];
        $roles = [];
        foreach ($row['audienceRoles'] ?? [] as $role) {
            $key = strtolower(trim((string) $role));
            if (isset($roleMap[$key])) {
                $roles[] = $roleMap[$key];
            }
        }
        $roles = array_values(array_unique($roles));

        $classIds = [];
        foreach ($row['classIds'] ?? [] as $classId) {
            $resolved = $this->resolveMap('classes', $classId);
            if ($resolved) {
                $classIds[] = $resolved;
            }
        }

        $parentIds = [];
        $coachIds = [];
        $athleteIds = [];
        $staffIds = [];
        foreach ($row['recipientIds'] ?? [] as $recipient) {
            if (! is_array($recipient)) {
                continue;
            }
            $kind = strtolower(trim((string) ($recipient['kind'] ?? '')));
            $legacyId = $recipient['id'] ?? null;
            if ($legacyId === null || $legacyId === '') {
                continue;
            }

            if ($kind === 'parent') {
                $userId = $this->resolveUserId($legacyId);
                if ($userId !== null) {
                    $parentIds[] = (string) $userId;
                } elseif (is_string($legacyId) && Str::isUuid($legacyId)) {
                    $parentIds[] = $legacyId;
                }
            } elseif ($kind === 'coach') {
                $id = $this->resolveMap('coaches', $legacyId);
                if ($id) {
                    $coachIds[] = $id;
                }
            } elseif ($kind === 'athlete') {
                $id = $this->resolveMap('students', $legacyId);
                if ($id) {
                    $athleteIds[] = $id;
                }
            } elseif ($kind === 'staff') {
                $id = $this->resolveMap('staff', $legacyId) ?? $this->resolveMap('StaffMember', $legacyId);
                if ($id) {
                    $staffIds[] = $id;
                }
            }
        }

        return [
            'roles' => $roles,
            'class_ids' => array_values(array_unique($classIds)),
            'sport' => trim((string) ($row['sportCategories'] ?? '')),
            'parent_ids' => array_values(array_unique($parentIds)),
            'coach_ids' => array_values(array_unique($coachIds)),
            'athlete_ids' => array_values(array_unique($athleteIds)),
            'staff_ids' => array_values(array_unique($staffIds)),
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array{sentCount: int, failedCount: int, recipients: list<array<string, mixed>>, queued_at?: string}
     */
    private function normalizeLegacyEmailStats(array $stats): array
    {
        $recipients = [];
        if (isset($stats['recipients']) && is_array($stats['recipients'])) {
            $recipients = array_values(array_filter($stats['recipients'], 'is_array'));
        }

        return [
            'sentCount' => (int) ($stats['sentCount'] ?? $stats['sent_count'] ?? 0),
            'failedCount' => (int) ($stats['failedCount'] ?? $stats['failed_count'] ?? 0),
            'recipients' => $recipients,
            'queued_at' => isset($stats['queued_at']) && is_string($stats['queued_at'])
                ? $stats['queued_at']
                : (isset($stats['queuedAt']) && is_string($stats['queuedAt']) ? $stats['queuedAt'] : null),
        ];
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importPhotos(string $clubId, array $rows): int
    {
        return $this->importSimpleList($clubId, $rows, GalleryPhoto::class, [
            'title' => 'title',
            'path' => 'path',
            'gdprCleared' => 'gdpr_cleared',
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importPartnerBusinesses(string $clubId, array $rows): int
    {
        return $this->importSimpleList($clubId, $rows, PartnerBusiness::class, [
            'name' => 'name',
            'contact' => 'contact',
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importPartnerOffers(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'title' => 'title',
                'description' => 'description',
            ]);
            $attrs['partner_business_id'] = $this->resolveMap('partnerBusinesses', $row['partnerBusinessId'] ?? null);
            if (! $attrs['partner_business_id']) {
                continue;
            }
            $this->upsert(PartnerOffer::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importProgressReports(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'reportDate' => 'report_date',
                'body' => 'body',
            ]);
            $attrs['athlete_id'] = $this->resolveMap('students', $row['athleteId'] ?? null);
            if (! $attrs['athlete_id']) {
                continue;
            }
            $attrs['coach_id'] = $this->resolveMap('coaches', $row['coachId'] ?? null);
            $attrs['meta'] = $this->metaRemainder($row, ['id', 'reportDate', 'body', 'athleteId', 'coachId']);
            $this->upsert(ProgressReport::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importRegistrationApplications(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'type' => 'type',
                'status' => 'status',
                'firstName' => 'first_name',
                'lastName' => 'last_name',
                'email' => 'email',
                'phone' => 'phone',
                'formSnapshot' => 'form_snapshot',
                'gdprAccepted' => 'gdpr_accepted',
                'signaturePath' => 'signature_path',
            ]);
            $this->upsert(RegistrationApplication::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importProtocolEntries(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = $this->mapFields($row, [
                'direction' => 'direction',
                'protocolNumber' => 'protocol_number',
                'subject' => 'subject',
                'documentDate' => 'document_date',
            ]);
            $dir = strtolower((string) ($attrs['direction'] ?? $row['direction'] ?? 'in'));
            $attrs['direction'] = match ($dir) {
                'incoming', 'in', 'εισερχόμενο', 'εισερχομενο' => 'in',
                'outgoing', 'out', 'εξερχόμενο', 'εξερχομενο' => 'out',
                default => in_array($dir, ['in', 'out'], true) ? $dir : 'in',
            };
            if (empty($attrs['subject'])) {
                $attrs['subject'] = (string) ($row['title'] ?? $row['id'] ?? 'Πρωτόκολλο');
            }
            $this->upsert(DocumentProtocolEntry::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importAmkaLogs(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $athleteId = $this->resolveMap('students', $row['athleteId'] ?? $row['studentId'] ?? null);
            if (! $athleteId) {
                continue;
            }
            $attrs = [
                'action' => $row['action'] ?? 'view',
                'athlete_id' => $athleteId,
                'user_id' => $this->resolveUserId($row['userId'] ?? $row['byUserId'] ?? null),
            ];
            $this->upsert(AmkaAccessLog::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importGdprLogs(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attrs = [
                'action' => $row['action'] ?? 'unknown',
                'user_id' => $this->resolveUserId($row['userId'] ?? null),
                'meta' => $row['meta'] ?? $this->metaRemainder($row, ['id', 'action', 'userId']),
            ];
            $this->upsert(GdprAuditLog::class, $clubId, $row, $attrs);
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importEmailUnsubscribes(string $clubId, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $email = $row['email'] ?? null;
            if (! $email) {
                continue;
            }
            EmailUnsubscribe::query()->withoutGlobalScopes()->updateOrCreate(
                ['club_id' => $clubId, 'email' => $email],
                []
            );
            $count++;
        }

        return $count;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importSuppressedKeys(string $clubId, array $rows): int
    {
        $count = 0;
        $seen = [];
        foreach ($rows as $row) {
            $key = is_string($row) ? $row : ($row['suppressKey'] ?? $row['key'] ?? null);
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            SuppressedFeeChargeKey::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'club_id' => $clubId,
                    'suppress_key' => $key,
                ],
                []
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(string $modelClass, string $clubId, array $row, array $attributes, ?string $mapKey = null): Model
    {
        /** @var class-string<Model> $modelClass */
        $legacyId = isset($row['id']) ? (string) $row['id'] : null;
        $mapKey ??= $this->defaultMapKey($modelClass);

        // Backup restore must ignore ClubScope — source club ≠ active session club.
        $softDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
        $query = $modelClass::query()->withoutGlobalScopes()->where('club_id', $clubId);
        if ($softDeletes) {
            $query->withTrashed();
        }

        $existing = null;
        if ($legacyId) {
            $existing = (clone $query)->where('legacy_id', $legacyId)->first();
            if (! $existing && Str::isUuid($legacyId)) {
                $existing = (clone $query)->where('id', $legacyId)->first();
            }
        }

        $id = $existing?->id ?? (Str::isUuid((string) $legacyId) ? $legacyId : (string) Str::uuid());

        $payload = array_merge(
            ['club_id' => $clubId, 'legacy_id' => $legacyId],
            array_filter($attributes, fn ($v) => $v !== null)
        );
        $payload = $this->ensureRequiredFields($modelClass, $payload, $legacyId);
        $legacyTimestamps = $this->legacyTimestampsFromRow($row);

        if ($existing) {
            if ($softDeletes && method_exists($existing, 'trashed') && $existing->trashed()) {
                $existing->restore();
            }
            $existing->fill($payload);
            if ($legacyTimestamps !== []) {
                $existing->timestamps = false;
                foreach ($legacyTimestamps as $column => $value) {
                    $existing->setAttribute($column, $value);
                }
            }
            $existing->save();
            $model = $existing;
        } else {
            $payload['id'] = $id;
            /** @var Model $model */
            $model = $modelClass::query()->withoutGlobalScopes()->make($payload);
            $model->id = $id;
            if ($legacyTimestamps !== []) {
                $model->timestamps = false;
                foreach ($legacyTimestamps as $column => $value) {
                    $model->setAttribute($column, $value);
                }
            }
            $model->save();
        }

        if ($legacyId) {
            $this->idMaps[$mapKey][$legacyId] = $model->id;
        }

        return $model;
    }

    private function defaultMapKey(string $modelClass): string
    {
        return match ($modelClass) {
            Athlete::class => 'students',
            AcademyClass::class => 'classes',
            SportMatch::class => 'matches',
            WarehouseProduct::class => 'products',
            ReceiptSeries::class => 'receiptNumberRanges',
            PartnerBusiness::class => 'partnerBusinesses',
            CashAccount::class => 'cashAccounts',
            Facility::class => 'facilities',
            Coach::class => 'coaches',
            StaffMember::class => 'staff',
            ClubSeason::class => 'clubSeasons',
            Training::class => 'trainings',
            AthleteTransaction::class => 'transactions',
            ScheduleSlot::class => 'schedule',
            Attendance::class => 'attendance',
            default => class_basename($modelClass),
        };
    }

    private function resolveMap(string $bucket, mixed $legacyRef): ?string
    {
        if ($legacyRef === null || $legacyRef === '') {
            return null;
        }

        $key = (string) $legacyRef;

        if (isset($this->idMaps[$bucket][$key])) {
            return $this->idMaps[$bucket][$key];
        }

        if (Str::isUuid($key)) {
            return $key;
        }

        // Fallback: already-imported rows (merge / prior batch) by legacy_id
        $modelClass = match ($bucket) {
            'students' => Athlete::class,
            'classes' => AcademyClass::class,
            'coaches' => Coach::class,
            'staff', 'StaffMember' => StaffMember::class,
            'trainings' => Training::class,
            'transactions' => AthleteTransaction::class,
            'facilities' => Facility::class,
            'products' => WarehouseProduct::class,
            'partnerBusinesses' => PartnerBusiness::class,
            'cashAccounts' => CashAccount::class,
            'clubSeasons' => ClubSeason::class,
            'matches' => SportMatch::class,
            default => null,
        };

        if ($modelClass) {
            $found = $modelClass::query()->withoutGlobalScopes()
                ->where('legacy_id', $key)
                ->value('id');
            if ($found) {
                return $this->idMaps[$bucket][$key] = $found;
            }
        }

        return null;
    }

    private function resolveUserId(mixed $legacyUserId): ?int
    {
        if ($legacyUserId === null || $legacyUserId === '') {
            return null;
        }

        $key = (string) $legacyUserId;

        return isset($this->idMaps['users'][$key])
            ? (int) $this->idMaps['users'][$key]
            : (is_numeric($key) ? (int) $key : null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $fieldMap
     * @return array<string, mixed>
     */
    private function mapFields(array $row, array $fieldMap): array
    {
        $out = [];
        foreach ($fieldMap as $legacy => $column) {
            if (! array_key_exists($legacy, $row)) {
                continue;
            }
            $value = $row[$legacy];
            if (str_ends_with($column, '_at') || str_ends_with($column, '_on') || str_contains($column, 'date')) {
                $value = $this->parseDate($value);
            }
            $out[$column] = $value;
        }

        return $out;
    }

    /**
     * @param  list<string>  $usedKeys
     * @return array<string, mixed>
     */
    private function metaRemainder(array $row, array $usedKeys): array
    {
        return Arr::except($row, array_merge($usedKeys, ['id']));
    }

    private function parseDate(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{created_at?: Carbon, updated_at?: Carbon}
     */
    private function legacyTimestampsFromRow(array $row): array
    {
        $createdAt = $this->parseDate($row['createdAt'] ?? $row['created_at'] ?? null);
        $updatedAt = $this->parseDate($row['updatedAt'] ?? $row['updated_at'] ?? null);

        $payload = [];
        if ($createdAt instanceof Carbon) {
            $payload['created_at'] = $createdAt;
        }
        if ($updatedAt instanceof Carbon) {
            $payload['updated_at'] = $updatedAt;
        } elseif ($createdAt instanceof Carbon) {
            $payload['updated_at'] = $createdAt;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function ensureRequiredFields(string $modelClass, array $payload, ?string $legacyId): array
    {
        $label = $legacyId ? 'Legacy '.$legacyId : 'Legacy';

        return match ($modelClass) {
            Coach::class, StaffMember::class, ClubMember::class, Athlete::class => array_merge([
                'first_name' => $label,
                'last_name' => 'Record',
            ], $payload),
            Sport::class, Association::class, Facility::class, CashAccount::class,
            AcademyClass::class, WarehouseProduct::class, FeeChargeTemplate::class,
            ClothingPackage::class, PartnerBusiness::class, DiscountReason::class => array_merge([
                'name' => $label,
            ], $payload),
            BudgetLine::class => array_merge(['label' => $label, 'year' => (int) date('Y'), 'amount' => 0], $payload),
            Announcement::class => array_merge(['title' => $label, 'body' => ''], $payload),
            GalleryPhoto::class => array_merge(['title' => $label, 'path' => ''], $payload),
            RegistrationApplication::class => array_merge([
                'type' => 'athlete',
                'status' => 'pending',
                'first_name' => $label,
                'last_name' => 'Applicant',
                'email' => 'legacy-'.($legacyId ?? Str::random(8)).'@import.local',
            ], $payload),
            AthleteTransaction::class => array_merge([
                'type' => 'charge',
                'amount' => 0,
            ], $payload),
            Revenue::class, Expense::class => array_merge([
                'amount' => 0,
                'occurred_on' => now()->toDateString(),
            ], $payload),
            OnlineCheckout::class => array_merge([
                'provider' => 'viva',
                'order_code' => $legacyId ?? Str::random(12),
                'amount' => 0,
            ], $payload),
            ScheduleSlot::class => array_merge(['weekday' => 1, 'title' => $label], $payload),
            Training::class => array_merge([
                'starts_at' => now(),
                'ends_at' => now()->addHour(),
                'title' => $label,
            ], $payload),
            SportMatch::class => array_merge([
                'starts_at' => now(),
                'opponent' => $label,
            ], $payload),
            RentalBooking::class => array_merge([
                'customer_name' => $label,
                'starts_at' => now(),
                'status' => 'pending',
            ], $payload),
            DocumentProtocolEntry::class => array_merge([
                'direction' => 'in',
                'protocol_number' => $legacyId ?? '0',
                'subject' => $label,
            ], $payload),
            default => $payload,
        };
    }
}
