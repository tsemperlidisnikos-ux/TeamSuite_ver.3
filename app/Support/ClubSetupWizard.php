<?php

namespace App\Support;

use App\Models\AcademyClass;
use App\Models\Athlete;
use App\Models\Club;
use App\Models\ClubSetting;
use App\Models\Scopes\ClubScope;

final class ClubSetupWizard
{
    public const STEP_PROFILE = 'profile';

    public const STEP_CLASSES = 'classes';

    public const STEP_PUBLIC_JOIN = 'publicJoin';

    public const STEP_PAYMENTS = 'payments';

    public const STEP_SECRETARIAT = 'secretariat';

    /** @var list<string> */
    public const STEP_IDS = [
        self::STEP_PROFILE,
        self::STEP_CLASSES,
        self::STEP_PUBLIC_JOIN,
        self::STEP_PAYMENTS,
        self::STEP_SECRETARIAT,
    ];

    /**
     * @return list<array{id: string, label: string, hint: string}>
     */
    public static function steps(): array
    {
        return [
            [
                'id' => self::STEP_PROFILE,
                'label' => 'Προφίλ συλλόγου',
                'hint' => 'Όνομα, έδρα και στοιχεία επικοινωνίας',
            ],
            [
                'id' => self::STEP_CLASSES,
                'label' => 'Πρώτο τμήμα',
                'hint' => 'Δημιουργήστε τουλάχιστον ένα τμήμα',
            ],
            [
                'id' => self::STEP_PUBLIC_JOIN,
                'label' => 'Δημόσια φόρμα',
                'hint' => 'Ενεργοποιήστε την εγγραφή από το κινητό',
            ],
            [
                'id' => self::STEP_PAYMENTS,
                'label' => 'Πληρωμές',
                'hint' => 'Online πάροχος ή μόνο μετρητά',
            ],
            [
                'id' => self::STEP_SECRETARIAT,
                'label' => 'Γραμματεία',
                'hint' => 'Λογαριασμός για καθημερινή λειτουργία',
            ],
        ];
    }

    /**
     * @return array{dismissed: bool, skipped: array<string, bool>}
     */
    public static function state(ClubSetting $settings): array
    {
        $profile = is_array($settings->profile) ? $settings->profile : [];
        $raw = is_array($profile['setup_wizard'] ?? null) ? $profile['setup_wizard'] : [];
        $skipped = is_array($raw['skipped'] ?? null) ? $raw['skipped'] : [];

        return [
            'dismissed' => (bool) ($raw['dismissed'] ?? false),
            'skipped' => collect(self::STEP_IDS)
                ->mapWithKeys(fn (string $id) => [$id => (bool) ($skipped[$id] ?? false)])
                ->all(),
        ];
    }

    /**
     * @param  array{dismissed?: bool, skipped?: array<string, bool>}  $patch
     */
    public static function writeState(ClubSetting $settings, array $patch): void
    {
        $profile = is_array($settings->profile) ? $settings->profile : [];
        $current = self::state($settings);

        if (array_key_exists('dismissed', $patch)) {
            $current['dismissed'] = (bool) $patch['dismissed'];
        }

        if (isset($patch['skipped']) && is_array($patch['skipped'])) {
            foreach ($patch['skipped'] as $step => $value) {
                if (in_array($step, self::STEP_IDS, true)) {
                    $current['skipped'][$step] = (bool) $value;
                }
            }
        }

        $profile['setup_wizard'] = $current;
        $settings->profile = $profile;
        $settings->save();
    }

    public static function isProfileReady(Club $club, ClubSetting $settings): bool
    {
        $profile = is_array($settings->profile) ? $settings->profile : [];
        $nameOk = mb_strlen(trim((string) $club->name)) >= 2;
        $contactOk = filled(trim((string) ($profile['phone'] ?? '')))
            || filled(trim((string) ($profile['email'] ?? '')))
            || filled(trim((string) ($profile['address'] ?? ($profile['city'] ?? ''))));

        return $nameOk && $contactOk;
    }

    public static function hasOnlinePaymentsConfigured(ClubSetting $settings): bool
    {
        $viva = is_array($settings->viva) ? $settings->viva : [];
        if (
            filter_var($viva['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && filled(trim((string) ($viva['clientId'] ?? '')))
        ) {
            return true;
        }

        $stripe = is_array($settings->stripe) ? $settings->stripe : [];
        if (
            filter_var($stripe['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && filled(trim((string) ($stripe['publishableKey'] ?? '')))
        ) {
            return true;
        }

        $eurobank = is_array($settings->eurobank) ? $settings->eurobank : [];
        if (
            filter_var($eurobank['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && filled(trim((string) ($eurobank['merchantId'] ?? '')))
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return array{
     *     done: array<string, bool>,
     *     doneCount: int,
     *     total: int,
     *     complete: bool,
     *     nextStep: ?string,
     *     dismissed: bool,
     *     offer: bool,
     *     steps: list<array{id: string, label: string, hint: string}>
     * }
     */
    public static function progress(Club $club, ClubSetting $settings): array
    {
        $state = self::state($settings);
        $skipped = $state['skipped'];

        $classCount = AcademyClass::query()
            ->withoutGlobalScope(ClubScope::class)
            ->where('club_id', $club->id)
            ->count();

        $hasSecretariat = $club->users()
            ->wherePivot('role', Roles::SECRETARIAT)
            ->wherePivot('is_active', true)
            ->exists();

        $publicEnabled = (bool) $settings->public_join_enabled
            || (bool) (($settings->public_registration['enabled'] ?? false));

        $done = [
            self::STEP_PROFILE => self::isProfileReady($club, $settings),
            self::STEP_CLASSES => $classCount > 0 || ($skipped[self::STEP_CLASSES] ?? false),
            self::STEP_PUBLIC_JOIN => $publicEnabled || ($skipped[self::STEP_PUBLIC_JOIN] ?? false),
            self::STEP_PAYMENTS => self::hasOnlinePaymentsConfigured($settings)
                || ($skipped[self::STEP_PAYMENTS] ?? false),
            self::STEP_SECRETARIAT => $hasSecretariat || ($skipped[self::STEP_SECRETARIAT] ?? false),
        ];

        $doneCount = count(array_filter($done));
        $total = count(self::STEP_IDS);
        $complete = $doneCount === $total;
        $nextStep = null;
        foreach (self::STEP_IDS as $id) {
            if (! $done[$id]) {
                $nextStep = $id;
                break;
            }
        }

        $athleteCount = Athlete::query()
            ->withoutGlobalScope(ClubScope::class)
            ->where('club_id', $club->id)
            ->count();

        $offer = ! $state['dismissed'] && ! $complete;

        // Prefer offering for newer / sparse clubs (parity with TeamSuite heuristic).
        $ageDays = $club->created_at
            ? $club->created_at->diffInDays(now())
            : 999;
        $sparse = $classCount === 0 || $athleteCount < 3 || $doneCount <= 2;
        if ($ageDays > 45 && ! $sparse) {
            $offer = false;
        }

        return [
            'done' => $done,
            'doneCount' => $doneCount,
            'total' => $total,
            'complete' => $complete,
            'nextStep' => $nextStep,
            'dismissed' => $state['dismissed'],
            'offer' => $offer,
            'steps' => self::steps(),
            'classCount' => $classCount,
            'athleteCount' => $athleteCount,
        ];
    }

    /**
     * Banner/reopen payload for dashboard & settings (null when not relevant).
     *
     * @return array{doneCount: int, total: int, nextLabel: ?string, dismissed: bool, href: string}|null
     */
    public static function bannerPayload(Club $club, ClubSetting $settings, bool $canManage): ?array
    {
        if (! $canManage) {
            return null;
        }

        $progress = self::progress($club, $settings);
        if ($progress['complete']) {
            return null;
        }

        if (! $progress['offer'] && ! $progress['dismissed']) {
            return null;
        }

        $next = collect(self::steps())->firstWhere('id', $progress['nextStep']);

        return [
            'doneCount' => $progress['doneCount'],
            'total' => $progress['total'],
            'nextLabel' => $next['label'] ?? null,
            'dismissed' => $progress['dismissed'],
            'href' => route('admin.setup.wizard'),
        ];
    }
}
