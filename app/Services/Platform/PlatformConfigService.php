<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use App\Support\Navigation;

class PlatformConfigService
{
    public const CONFIG_KEY = 'platform_config';

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $modules = array_column(Navigation::academyModules(), 'id');

        return [
            'appName' => 'TeamSuite',
            'appLogoUrl' => null,
            'appearanceTheme' => 'ocean-slate',
            'appearanceThemeByClub' => [],
            'clubHeaderTitles' => [],
            'clubAppLogos' => [],
            'financeTabs' => array_column($this->financeTabsCatalog(), 'id'),
            'academyModulesByClub' => [],
            'defaultAcademyModules' => $modules,
            'clubRolePermissions' => $this->defaultRolePermissions(),
            'incomeCategories' => $this->defaultIncomeCategories(),
            'expenseCategories' => $this->defaultExpenseCategories(),
            'incomeDescriptions' => $this->defaultIncomeDescriptions(),
            'expenseDescriptions' => $this->defaultExpenseDescriptions(),
            'seasons' => ['2025-2026', '2026-2027'],
            'healthCardTemplatesBySport' => [],
            'hostingBilling' => [
                'provider' => 'hetzner',
                'monthly_estimate_eur' => 7.5,
                'prepaid_balance_eur' => null,
                'low_balance_threshold_eur' => 15,
                'notify_email' => null,
                'hetzner_api_token' => '',
                'notes' => 'CX23 ≈ €6–8/μήνα + domain. Δεν χρειάζεται επιπλέον υπηρεσία για να τρέχει η εφαρμογή.',
                'last_topup_at' => null,
                'last_notified_at' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $row = PlatformSetting::query()->where('key', self::CONFIG_KEY)->first();
        $stored = is_array($row?->value) ? $row->value : [];
        $config = array_replace_recursive($this->defaults(), $stored);
        $config['appearanceTheme'] = $this->normalizeAppearanceTheme(
            is_string($config['appearanceTheme'] ?? null) ? $config['appearanceTheme'] : null
        );

        if (is_array($config['appearanceThemeByClub'] ?? null)) {
            $normalizedByClub = [];
            foreach ($config['appearanceThemeByClub'] as $clubId => $theme) {
                if (! is_string($clubId) || $clubId === '' || ! is_string($theme) || $theme === '') {
                    continue;
                }
                $normalizedByClub[$clubId] = $this->normalizeAppearanceTheme($theme);
            }
            $config['appearanceThemeByClub'] = $normalizedByClub;
        }

        // List merges must replace whole arrays — array_replace_recursive keeps leftover indexes
        // (e.g. unchecking "settings" for coaches would otherwise leave the default entry).
        $config['clubRolePermissions'] = $this->mergeRolePermissions(
            $this->defaultRolePermissions(),
            is_array($stored['clubRolePermissions'] ?? null) ? $stored['clubRolePermissions'] : [],
        );

        // Συμπλήρωση κατηγοριών/υποκατηγοριών που υπάρχουν στο TeamSuite αλλά λείπουν από παλιό config.
        $config['incomeCategories'] = $this->mergeUniqueStrings(
            $this->defaultIncomeCategories(),
            is_array($config['incomeCategories'] ?? null) ? $config['incomeCategories'] : [],
        );
        $config['expenseCategories'] = $this->mergeUniqueStrings(
            $this->defaultExpenseCategories(),
            is_array($config['expenseCategories'] ?? null) ? $config['expenseCategories'] : [],
        );
        $config['incomeDescriptions'] = $this->mergeDescriptionMaps(
            $this->defaultIncomeDescriptions(),
            is_array($config['incomeDescriptions'] ?? null) ? $config['incomeDescriptions'] : [],
        );
        $config['expenseDescriptions'] = $this->mergeDescriptionMaps(
            $this->defaultExpenseDescriptions(),
            is_array($config['expenseDescriptions'] ?? null) ? $config['expenseDescriptions'] : [],
        );

        return $config;
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function update(array $patch): array
    {
        $current = $this->get();
        $next = array_replace_recursive($current, $patch);

        if (isset($patch['financeTabs']) && is_array($patch['financeTabs'])) {
            $allowed = array_column($this->financeTabsCatalog(), 'id');
            $next['financeTabs'] = array_values(array_intersect($patch['financeTabs'], $allowed)) ?: $allowed;
        }

        if (isset($patch['defaultAcademyModules']) && is_array($patch['defaultAcademyModules'])) {
            $allowed = array_column(Navigation::academyModules(), 'id');
            $next['defaultAcademyModules'] = array_values(array_intersect($patch['defaultAcademyModules'], $allowed)) ?: $allowed;
        }

        if (isset($patch['academyModulesByClub']) && is_array($patch['academyModulesByClub'])) {
            $allowed = array_column(Navigation::academyModules(), 'id');
            $byClub = [];
            foreach ($patch['academyModulesByClub'] as $clubId => $modules) {
                if (! is_string($clubId) || $clubId === '' || ! is_array($modules)) {
                    continue;
                }
                $byClub[$clubId] = array_values(array_intersect($modules, $allowed)) ?: $allowed;
            }
            $next['academyModulesByClub'] = $byClub;
        }

        if (isset($patch['clubRolePermissions']) && is_array($patch['clubRolePermissions'])) {
            $next['clubRolePermissions'] = $this->mergeRolePermissions(
                is_array($current['clubRolePermissions'] ?? null)
                    ? $current['clubRolePermissions']
                    : $this->defaultRolePermissions(),
                $patch['clubRolePermissions'],
            );
        }

        if (isset($patch['seasons']) && is_array($patch['seasons'])) {
            $next['seasons'] = array_values(array_filter(array_map(
                fn ($s) => is_string($s) ? trim($s) : '',
                $patch['seasons'],
            )));
        }

        if (array_key_exists('clubHeaderTitles', $patch) && is_array($patch['clubHeaderTitles'])) {
            $titles = [];
            foreach ($patch['clubHeaderTitles'] as $clubId => $title) {
                if (! is_string($clubId) || $clubId === '') {
                    continue;
                }
                $trimmed = is_string($title) ? trim($title) : '';
                if ($trimmed !== '') {
                    $titles[$clubId] = $trimmed;
                }
            }
            $next['clubHeaderTitles'] = $titles;
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::CONFIG_KEY],
            ['value' => $next],
        );

        return $next;
    }

    public function headerTitleForClub(?string $clubId, ?string $fallbackName = null): string
    {
        $config = $this->get();
        $custom = '';
        if ($clubId && is_array($config['clubHeaderTitles'] ?? null)) {
            $custom = trim((string) ($config['clubHeaderTitles'][$clubId] ?? ''));
        }

        if ($custom !== '') {
            return $custom;
        }

        $fromClub = trim((string) ($fallbackName ?? ''));
        if ($fromClub !== '') {
            return $fromClub;
        }

        $appName = trim((string) ($config['appName'] ?? 'TeamSuite'));
        $stripped = trim((string) preg_replace('/\s*Suite$/iu', '', $appName));

        return $stripped !== '' ? $stripped : 'TeamSuite';
    }

    /**
     * @return list<string>
     */
    public function modulesForClub(?string $clubId): array
    {
        $config = $this->get();
        if ($clubId && isset($config['academyModulesByClub'][$clubId]) && is_array($config['academyModulesByClub'][$clubId])) {
            return $config['academyModulesByClub'][$clubId];
        }

        return $config['defaultAcademyModules'] ?? array_column(Navigation::academyModules(), 'id');
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function financeTabsCatalog(): array
    {
        return [
            ['id' => 'analysis', 'label' => 'Ανάλυση'],
            ['id' => 'revenues', 'label' => 'Έσοδα'],
            ['id' => 'expenses', 'label' => 'Έξοδα'],
            ['id' => 'accounts', 'label' => 'Ταμεία'],
            ['id' => 'balance', 'label' => 'Ισοζύγιο'],
            ['id' => 'budget', 'label' => 'Προϋπολογισμός'],
            ['id' => 'reports', 'label' => 'Αναφορές'],
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function appearanceThemes(): array
    {
        return [
            ['id' => 'ocean-slate', 'label' => 'Ocean Slate'],
            ['id' => 'aegean-light', 'label' => 'Aegean Light'],
            ['id' => 'harbor-navy', 'label' => 'Harbor Navy'],
            ['id' => 'night-pitch', 'label' => 'Night Pitch'],
        ];
    }

    /**
     * @return list<string>
     */
    public function appearanceThemeIds(): array
    {
        return array_column($this->appearanceThemes(), 'id');
    }

    public function resolveAppearanceTheme(?string $clubId = null): string
    {
        $config = $this->get();
        $byClub = is_string($clubId)
            ? ($config['appearanceThemeByClub'][$clubId] ?? null)
            : null;

        if (is_string($byClub) && $byClub !== '') {
            return $this->normalizeAppearanceTheme($byClub);
        }

        return $this->normalizeAppearanceTheme(
            is_string($config['appearanceTheme'] ?? null) ? $config['appearanceTheme'] : null
        );
    }

    public function normalizeAppearanceTheme(?string $theme): string
    {
        $legacy = [
            'ivory-club' => 'ocean-slate',
            'light-blue' => 'ocean-slate',
            'light-cyan' => 'ocean-slate',
            'aegean-navy' => 'harbor-navy',
            'graphite-ember' => 'harbor-navy',
            'light-orange' => 'harbor-navy',
            'light-gold' => 'harbor-navy',
            'dark-orange' => 'night-pitch',
            'dark-blue' => 'night-pitch',
            'dark-cyan' => 'night-pitch',
            'dark-gold' => 'night-pitch',
        ];

        $normalized = $legacy[$theme] ?? ($theme ?: 'ocean-slate');

        return in_array($normalized, $this->appearanceThemeIds(), true)
            ? $normalized
            : 'ocean-slate';
    }

    /**
     * @return array<string, list<string>>
     */
    public function defaultRolePermissions(): array
    {
        $all = array_column(Navigation::academyModules(), 'id');

        return [
            'admin' => $all,
            'doctor' => ['dashboard', 'athletes'],
            'secretariat' => array_values(array_diff($all, [])),
            'coach' => [
                'dashboard', 'calendar', 'athletes', 'classes', 'trainings',
                'matches', 'schedule', 'attendance', 'announcements', 'settings',
            ],
            'staff' => ['dashboard', 'calendar'],
            'athlete' => ['dashboard', 'schedule', 'attendance', 'fees', 'announcements', 'settings'],
            // Γονείς: μόνο προβολή ανακοινώσεων στο portal — χωρίς module αποστολής.
            'parent' => ['dashboard', 'fees', 'settings'],
        ];
    }

    /**
     * Replace whole permission lists per role (do not recursively merge numeric indexes).
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, list<string>>
     */
    public function mergeRolePermissions(array $base, array $override): array
    {
        $allowed = array_column(Navigation::academyModules(), 'id');
        $merged = [];

        foreach ($base as $role => $modules) {
            if (! is_string($role) || $role === '') {
                continue;
            }
            $merged[$role] = $this->sanitizeModuleList($modules, $allowed);
        }

        foreach ($override as $role => $modules) {
            if (! is_string($role) || $role === '' || ! is_array($modules)) {
                continue;
            }
            $merged[$role] = $this->sanitizeModuleList($modules, $allowed);
        }

        return $merged;
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function sanitizeModuleList(mixed $modules, array $allowed): array
    {
        if (! is_array($modules)) {
            return [];
        }

        return array_values(array_unique(array_intersect(
            array_values(array_filter($modules, 'is_string')),
            $allowed,
        )));
    }

    /**
     * @return list<string>
     */
    public function defaultIncomeCategories(): array
    {
        // Ίδιο με TeamSuite `INCOME_SUBCATEGORIES` (χωρίς ΣΥΝΔΡΟΜΕΣ — αυτές είναι συστημικές).
        return [
            'ΕΙΣΙΤΗΡΙΑ ΑΓΩΝΩΝ',
            'ΕΙΣΙΤΗΡΙΑ ΕΚΔΗΛΩΣΕΩΝ',
            'ΕΚΔΗΛΩΣΕΙΣ',
            'ΧΟΡΗΓΙΕΣ',
            'ΕΠΙΧΟΡΗΓΗΣΕΙΣ',
            'ΔΩΡΕΕΣ',
            'ΠΑΡΟΧΕΣ',
            'ΕΝΟΙΚΙΑΣΗ ΓΗΠΕΔΟΥ',
            'ΚΑΝΤΙΝΑ / ΚΥΛΙΚΕΙΟ',
            'ΠΩΛΗΣΕΙΣ ΕΙΔΩΝ',
            'ΔΙΑΦΗΜΙΣΕΙΣ / ΠΙΝΑΚΙΔΕΣ',
            'ΛΟΙΠΑ ΕΣΟΔΑ',
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultExpenseCategories(): array
    {
        // Ίδιο με TeamSuite `EXPENSE_SUBCATEGORIES`.
        return [
            'ΑΓΩΝΕΣ',
            'ΕΓΚΑΤΑΣΤΑΣΗ',
            'ΑΘΛΗΤΕΣ',
            'ΠΡΟΠΟΝΗΤΕΣ / ΓΥΜΝΑΣΤΕΣ',
            'ΠΡΟΣΩΠΙΚΟ',
            'ΙΑΤΡΙΚΑ',
            'ΕΞΟΠΛΙΣΜΟΣ / ΥΛΙΚΑ',
            'ΜΕΤΑΚΙΝΗΣΕΙΣ',
            'ΑΣΦΑΛΙΣΤΡΑ / ΤΕΛΗ ΕΓΓΡΑΦΩΝ',
            'ΔΙΟΙΚΗΤΙΚΑ',
            'ΕΚΔΗΛΩΣΕΙΣ',
            'MARKETING',
            'ΛΟΙΠΑ ΕΞΟΔΑ',
        ];
    }

    /**
     * Υποκατηγορίες εσόδων — parity με TeamSuite `DEFAULT_INCOME_DESCRIPTIONS`.
     *
     * @return array<string, list<string>>
     */
    public function defaultIncomeDescriptions(): array
    {
        return [
            'ΕΙΣΙΤΗΡΙΑ ΑΓΩΝΩΝ' => [
                'ΕΙΣΙΤΗΡΙΟ ΕΝΗΛΙΚΩΝ',
                'ΕΙΣΙΤΗΡΙΟ ΠΑΙΔΙΩΝ',
                'ΕΠΟΧΙΑΚΗ ΚΑΡΤΑ',
                'ΕΙΣΙΤΗΡΙΟ ΔΙΑΡΚΕΙΑΣ',
            ],
            'ΕΙΣΙΤΗΡΙΑ ΕΚΔΗΛΩΣΕΩΝ' => ['ΕΙΣΙΤΗΡΙΟ', 'VIP', 'ΠΡΟΣΚΛΗΣΗ ΜΕ ΑΝΤΙΤΙΜΟ'],
            'ΕΚΔΗΛΩΣΕΙΣ' => ['ΔΕΙΠΝΟ', 'ΜΠΑΡΜΠΕΚΙΟΥ', 'ΛΟΤΑΡΙΑ', 'ΑΦΙΕΡΩΜΑ'],
            'ΧΟΡΗΓΙΕΣ' => ['ΧΡΗΜΑΤΙΚΗ', 'ΣΕ ΕΙΔΟΣ', 'ΤΙΤΛΟΣ ΧΟΡΗΓΟΥ'],
            'ΕΠΙΧΟΡΗΓΗΣΕΙΣ' => ['ΔΗΜΟΣ', 'ΓΓΑ', 'ΕΝΩΣΗ / ΟΜΟΣΠΟΝΔΙΑ', 'ΑΛΛΗ ΕΠΙΧΟΡΗΓΗΣΗ'],
            'ΔΩΡΕΕΣ' => ['ΧΡΗΜΑΤΙΚΗ ΔΩΡΕΑ', 'ΔΩΡΕΑ ΣΕ ΕΙΔΟΣ'],
            'ΠΑΡΟΧΕΣ' => [
                'ΠΑΡΟΧΗ ΥΠΗΡΕΣΙΑΣ',
                'ΕΚΜΙΣΘΩΣΗ ΧΩΡΟΥ',
                'ΕΚΠΑΙΔΕΥΤΙΚΟ ΠΡΟΓΡΑΜΜΑ',
                'ΑΤΟΜΙΚΕΣ ΠΡΟΠΟΝΗΣΕΙΣ',
                'ΠΡΟΓΡΑΜΜΑ ΕΝΔΥΝΑΜΩΣΗΣ',
                'CAMPS / CLINICS',
                'ΣΕΜΙΝΑΡΙΑ',
                'ΑΛΛΗ ΠΑΡΟΧΗ',
            ],
            'ΕΝΟΙΚΙΑΣΗ ΓΗΠΕΔΟΥ' => ['ΕΝΟΙΚΙΑΣΗ ΓΗΠΕΔΟΥ', 'ΕΚΜΙΣΘΩΣΗ ΧΩΡΟΥ'],
            'ΚΑΝΤΙΝΑ / ΚΥΛΙΚΕΙΟ' => ['ΠΩΛΗΣΕΙΣ ΗΜΕΡΑΣ', 'ΕΚΔΗΛΩΣΗ'],
            'ΠΩΛΗΣΕΙΣ ΕΙΔΩΝ' => ['ΣΤΟΛΕΣ', 'ΜΠΑΛΕΣ', 'ΑΞΕΣΟΥΑΡ', 'ΑΛΛΑ ΕΙΔΗ'],
            'ΔΙΑΦΗΜΙΣΕΙΣ / ΠΙΝΑΚΙΔΕΣ' => ['ΠΙΝΑΚΙΔΑ ΓΗΠΕΔΟΥ', 'ΦΑΝΕΛΑ', 'ΕΝΤΥΠΟ / SITE'],
            'ΛΟΙΠΑ ΕΣΟΔΑ' => ['ΕΠΙΣΤΡΟΦΗ ΧΡΗΜΑΤΩΝ', 'ΤΟΚΟΙ', 'ΑΛΛΟ'],
            // Συστημική κατηγορία συνδρομών (όπως TeamSuite ATHLETE_INCOME_DESCRIPTIONS).
            'ΣΥΝΔΡΟΜΕΣ ΑΘΛΗΤΩΝ' => [
                'ΕΓΓΡΑΦΗ',
                'ΜΗΝΙΑΙΑ ΣΥΝΔΡΟΜΗ',
                'ΕΤΗΣΙΑ ΣΥΝΔΡΟΜΗ',
                'ΚΑΡΤΑ ΔΙΑΡΚΕΙΑΣ',
            ],
        ];
    }

    /**
     * Υποκατηγορίες εξόδων — parity με TeamSuite `DEFAULT_EXPENSE_DESCRIPTIONS`.
     *
     * @return array<string, list<string>>
     */
    public function defaultExpenseDescriptions(): array
    {
        return [
            'ΑΓΩΝΕΣ' => [
                'ΔΙΑΙΤΗΣΙΑ',
                'ΚΡΙΤΕΣ',
                'ΟΔΟΙΠΟΡΙΚΑ',
                'ΜΕΤΑΚΙΝΗΣΗ',
                'ΔΙΑΜΟΝΗ',
                'ΔΙΑΤΡΟΦΗ',
                'ΙΑΤΡΟΣ',
                'ΚΟΜΙΣΑΡΙΟΣ',
                'ΠΑΡΑΤΗΡΗΤΗΣ',
                'VIDEO OBSERVER',
                'ΑΛΛΟ ΕΞΟΔΟ ΑΓΩΝΑ',
            ],
            'ΕΓΚΑΤΑΣΤΑΣΗ' => [
                'ΕΝΟΙΚΙΟ ΓΗΠΕΔΟΥ',
                'ΗΛΕΚΤΡΙΚΟ ΡΕΥΜΑ',
                'ΝΕΡΟ',
                'ΣΥΝΤΗΡΗΣΗ',
                'ΘΕΡΜΑΝΣΗ',
                'ΤΗΛΕΦΩΝΙΑ',
            ],
            'ΑΘΛΗΤΕΣ' => [
                "AGENT'S FEE",
                'ΑΣΦΑΛΙΣΤΙΚΕΣ ΕΙΣΦΟΡΕΣ',
                'ΕΝΟΙΚΙΟ ΑΥΤΟΚΙΝΗΤΟΥ',
                'ΕΝΟΙΚΙΟ ΣΠΙΤΙΟΥ',
                'ΜΗΝΙΑΙΟΣ ΜΙΣΘΟΣ',
                'ΔΙΑΤΡΟΦΗ',
            ],
            'ΠΡΟΠΟΝΗΤΕΣ / ΓΥΜΝΑΣΤΕΣ' => [
                'ΜΗΝΙΑΙΟΣ ΜΙΣΘΟΣ',
                'ΗΜΕΡΟΜΙΣΘΙΟ',
                'ΑΣΦΑΛΙΣΤΙΚΕΣ ΕΙΣΦΟΡΕΣ',
                'ΜΕΤΑΚΙΝΗΣΗ',
                'ΕΝΟΙΚΙΟ ΑΥΤΟΚΙΝΗΤΟΥ',
                'ΕΝΟΙΚΙΟ ΣΠΙΤΙΟΥ',
                'ΔΙΑΤΡΟΦΗ',
            ],
            'ΠΡΟΣΩΠΙΚΟ' => ['ΜΗΝΙΑΙΟΣ ΜΙΣΘΟΣ', 'ΗΜΕΡΟΜΙΣΘΙΟ', 'ΑΣΦΑΛΙΣΤΙΚΕΣ ΕΙΣΦΟΡΕΣ', 'ΜΕΤΑΚΙΝΗΣΗ'],
            'ΙΑΤΡΙΚΑ' => [
                'ΚΑΡΔΙΟΛΟΓΙΚΟΣ',
                'ΟΡΘΟΠΕΔΙΚΟΣ',
                'ΦΥΣΙΚΟΘΕΡΑΠΕΙΑ',
                'ΜΑΓΝΗΤΙΚΗ',
                'ΑΚΤΙΝΟΓΡΑΦΙΑ',
                'ΦΑΡΜΑΚΑ',
                'ΑΣΦΑΛΙΣΗ ΑΘΛΗΤΗ',
                'ΝΟΣΗΛΕΙΑ',
            ],
            'ΕΞΟΠΛΙΣΜΟΣ / ΥΛΙΚΑ' => [
                'ΣΤΟΛΕΣ',
                'ΜΠΑΛΕΣ',
                'ΠΑΠΟΥΤΣΙΑ',
                'ΙΜΑΤΙΣΜΟΣ',
                'ΑΝΑΛΩΣΙΜΑ',
                'ΙΑΤΡΙΚΟΣ ΕΞΟΠΛΙΣΜΟΣ',
                'ΤΕΧΝΟΛΟΓΙΚΟΣ ΕΞΟΠΛΙΣΜΟΣ',
                'ΑΛΛΟΣ ΕΞΟΠΛΙΣΜΟΣ',
            ],
            'ΜΕΤΑΚΙΝΗΣΕΙΣ' => ['ΛΕΩΦΟΡΕΙΟ', 'ΚΑΥΣΙΜΑ', 'ΔΙΟΔΙΑ', 'ΑΕΡΟΠΟΡΙΚΑ', 'ΔΙΑΜΟΝΗ ΕΚΤΟΣ ΑΓΩΝΑ'],
            'ΑΣΦΑΛΙΣΤΡΑ / ΤΕΛΗ ΕΓΓΡΑΦΩΝ' => [
                'ΑΣΦΑΛΙΣΤΡΟ ΟΜΑΔΑΣ',
                'ΠΑΡΑΣΤΗΜΑ ΕΝΩΣΗΣ',
                'ΚΑΡΤΑ ΥΓΕΙΑΣ',
                'ΤΕΛΟΣ ΕΓΓΡΑΦΗΣ',
            ],
            'ΔΙΟΙΚΗΤΙΚΑ' => ['ΛΟΓΙΣΤΗΣ', 'ΔΙΚΗΓΟΡΟΣ', 'ΓΡΑΦΙΚΗ ΥΛΗ', 'ΤΡΑΠΕΖΙΚΑ ΕΞΟΔΑ', 'ΛΟΙΠΑ ΔΙΟΙΚΗΤΙΚΑ'],
            'ΕΚΔΗΛΩΣΕΙΣ' => ['ΔΙΟΡΓΑΝΩΣΗ', 'ΦΑΓΗΤΟ / ΚΕΤΕΡΙΝΓΚ', 'ΗΧΟΣ / ΦΩΤΙΣΜΟΣ', 'ΔΙΑΚΟΣΜΗΣΗ'],
            'MARKETING' => [
                'ΔΙΑΦΗΜΙΣΗ ONLINE',
                'ΕΝΤΥΠΑ / ΦΥΛΛΑΔΙΑ',
                'SOCIAL MEDIA',
                'BANNER / ΠΙΝΑΚΙΔΕΣ',
                'ΠΡΟΩΘΗΤΙΚΑ ΕΙΔΗ',
                'ΒΙΝΤΕΟ',
                'ΦΩΤΟΓΡΑΦΙΕΣ',
                'ΑΛΛΟ MARKETING',
            ],
            'ΛΟΙΠΑ ΕΞΟΔΑ' => ['ΑΠΡΟΣΒΛΕΠΤΑ', 'ΕΠΙΣΤΡΟΦΗ ΧΡΗΜΑΤΩΝ', 'ΜΕΤΑΓΡΑΦΗ ΑΘΛΗΤΗ / ΡΙΑΣ', 'ΑΛΛΟ'],
        ];
    }

    /**
     * @param  list<string>  $defaults
     * @param  list<mixed>  $stored
     * @return list<string>
     */
    private function mergeUniqueStrings(array $defaults, array $stored): array
    {
        $out = [];
        $seen = [];
        foreach ([...$defaults, ...$stored] as $item) {
            if (! is_string($item)) {
                continue;
            }
            $value = trim($item);
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $out[] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, list<string>>  $defaults
     * @param  array<string, mixed>  $stored
     * @return array<string, list<string>>
     */
    private function mergeDescriptionMaps(array $defaults, array $stored): array
    {
        $out = $defaults;
        foreach ($stored as $category => $values) {
            if (! is_string($category) || $category === '' || ! is_array($values)) {
                continue;
            }
            $merged = $this->mergeUniqueStrings(
                is_array($defaults[$category] ?? null) ? $defaults[$category] : [],
                $values,
            );
            if ($merged !== []) {
                $out[$category] = $merged;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public function roleLabels(): array
    {
        return [
            'admin' => 'Διαχειριστής συλλόγου',
            'doctor' => 'Ιατρός',
            'secretariat' => 'Γραμματεία',
            'coach' => 'Προπονητής',
            'staff' => 'Προσωπικό',
            'athlete' => 'Αθλητής',
            'parent' => 'Γονέας',
        ];
    }
}
