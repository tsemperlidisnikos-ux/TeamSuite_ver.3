<?php

namespace App\Support;

final class Navigation
{
    /**
     * Flat academy modules in TeamSuite sidebar order (Greek labels).
     * Matches legacy AppLayout academyItems + provisions order.
     *
     * @return list<array{id: string, label: string, route: string|null}>
     */
    public static function academyModules(): array
    {
        return [
            ['id' => 'dashboard', 'label' => 'Προεπισκόπηση', 'route' => 'dashboard'],
            ['id' => 'calendar', 'label' => 'Ημερολόγιο', 'route' => 'admin.calendar.index'],
            ['id' => 'athletes', 'label' => 'Αθλητές', 'route' => 'admin.athletes.index'],
            ['id' => 'staff', 'label' => 'Προσωπικό', 'route' => 'admin.staff.index'],
            ['id' => 'coaches', 'label' => 'Προπονητές', 'route' => 'admin.coaches.index'],
            ['id' => 'classes', 'label' => 'Τμήματα', 'route' => 'admin.classes.index'],
            ['id' => 'parents', 'label' => 'Γονείς', 'route' => 'admin.parents.index'],
            ['id' => 'members', 'label' => 'Μέλη', 'route' => 'admin.members.index'],
            ['id' => 'trainings', 'label' => 'Προπονήσεις', 'route' => 'admin.trainings.index'],
            ['id' => 'matches', 'label' => 'Αγώνες', 'route' => 'admin.matches.index'],
            ['id' => 'schedule', 'label' => 'Πρόγραμμα', 'route' => 'admin.schedule.index'],
            ['id' => 'attendance', 'label' => 'Παρουσίες', 'route' => 'admin.attendance.index'],
            ['id' => 'announcements', 'label' => 'Ανακοινώσεις', 'route' => 'admin.announcements.index'],
            ['id' => 'prints', 'label' => 'Εκτυπώσεις', 'route' => 'admin.prints.index'],
            ['id' => 'photos', 'label' => 'Φωτογραφίες', 'route' => 'admin.photos.index'],
            ['id' => 'warehouse', 'label' => 'Αποθήκη', 'route' => 'admin.warehouse.index'],
            ['id' => 'fees', 'label' => 'Συνδρομές / Πληρωμές', 'route' => 'admin.fees.index'],
            ['id' => 'transactions', 'label' => 'Συναλλαγές', 'route' => 'admin.transactions.index'],
            ['id' => 'partnerBusinesses', 'label' => 'Συμβεβλημένες Επιχειρήσεις', 'route' => 'admin.partners.index'],
            ['id' => 'documentProtocol', 'label' => 'Πρωτόκολλο Εγγράφων', 'route' => 'admin.protocol.index'],
            ['id' => 'rental', 'label' => 'Ενοικίαση', 'route' => 'admin.rentals.index'],
            ['id' => 'finance', 'label' => 'Οικονομικά', 'route' => 'admin.finance.index'],
            ['id' => 'associations', 'label' => 'Σωματείο', 'route' => 'admin.associations.index'],
            // Άθλημα lives under Ρυθμίσεις (?tab=sports), not as a top-level sidebar item.
            ['id' => 'settings', 'label' => 'Ρυθμίσεις', 'route' => 'admin.settings.index'],
        ];
    }

    /**
     * Flat sidebar items filtered by enabled modules.
     *
     * @param  list<string>|null  $enabledModuleIds
     * @return list<array{id: string, label: string, route: string}>
     */
    public static function adminItems(?array $enabledModuleIds = null): array
    {
        $enabled = $enabledModuleIds === null ? null : array_flip($enabledModuleIds);

        return array_values(array_filter(
            array_map(function (array $item) use ($enabled) {
                if ($item['route'] === null) {
                    return null;
                }
                if ($enabled !== null && $item['id'] !== 'dashboard' && ! isset($enabled[$item['id']])) {
                    return null;
                }

                return [
                    'id' => $item['id'],
                    'label' => $item['label'],
                    'route' => $item['route'],
                ];
            }, self::academyModules()),
        ));
    }

    /**
     * Grouped admin sidebar (Greek labels) — same items as TeamSuite, grouped for UX.
     *
     * @param  list<string>|null  $enabledModuleIds
     * @return list<array{key: string, label: string, items: list<array{label: string, route: string, module: string}>}>
     */
    public static function adminGroups(?array $enabledModuleIds = null): array
    {
        $enabled = $enabledModuleIds === null ? null : array_flip($enabledModuleIds);

        $groups = [
            [
                'key' => 'registry',
                'label' => 'ΜΗΤΡΩΟ',
                'items' => [
                    ['label' => 'Αθλητές', 'route' => 'admin.athletes.index', 'module' => 'athletes'],
                    ['label' => 'Προσωπικό', 'route' => 'admin.staff.index', 'module' => 'staff'],
                    ['label' => 'Προπονητές', 'route' => 'admin.coaches.index', 'module' => 'coaches'],
                    ['label' => 'Τμήματα', 'route' => 'admin.classes.index', 'module' => 'classes'],
                    ['label' => 'Γονείς', 'route' => 'admin.parents.index', 'module' => 'parents'],
                    ['label' => 'Μέλη', 'route' => 'admin.members.index', 'module' => 'members'],
                    ['label' => 'Σωματείο', 'route' => 'admin.associations.index', 'module' => 'associations'],
                ],
            ],
            [
                'key' => 'schedule',
                'label' => 'ΠΡΟΓΡΑΜΜΑ',
                'items' => [
                    ['label' => 'Ημερολόγιο', 'route' => 'admin.calendar.index', 'module' => 'calendar'],
                    ['label' => 'Προπονήσεις', 'route' => 'admin.trainings.index', 'module' => 'trainings'],
                    ['label' => 'Αγώνες', 'route' => 'admin.matches.index', 'module' => 'matches'],
                    ['label' => 'Πρόγραμμα', 'route' => 'admin.schedule.index', 'module' => 'schedule'],
                    ['label' => 'Παρουσίες', 'route' => 'admin.attendance.index', 'module' => 'attendance'],
                ],
            ],
            [
                'key' => 'finance',
                'label' => 'ΟΙΚΟΝΟΜΙΚΑ',
                'items' => [
                    ['label' => 'Συνδρομές / Πληρωμές', 'route' => 'admin.fees.index', 'module' => 'fees'],
                    ['label' => 'Συναλλαγές', 'route' => 'admin.transactions.index', 'module' => 'transactions'],
                    ['label' => 'Οικονομικά', 'route' => 'admin.finance.index', 'module' => 'finance'],
                    ['label' => 'Αποθήκη', 'route' => 'admin.warehouse.index', 'module' => 'warehouse'],
                    ['label' => 'Ενοικίαση', 'route' => 'admin.rentals.index', 'module' => 'rental'],
                ],
            ],
            [
                'key' => 'comms',
                'label' => 'ΕΠΙΚΟΙΝΩΝΙΑ',
                'items' => [
                    ['label' => 'Ανακοινώσεις', 'route' => 'admin.announcements.index', 'module' => 'announcements'],
                    ['label' => 'Εκτυπώσεις', 'route' => 'admin.prints.index', 'module' => 'prints'],
                    ['label' => 'Φωτογραφίες', 'route' => 'admin.photos.index', 'module' => 'photos'],
                    ['label' => 'Συμβεβλημένες Επιχειρήσεις', 'route' => 'admin.partners.index', 'module' => 'partnerBusinesses'],
                    ['label' => 'Πρωτόκολλο Εγγράφων', 'route' => 'admin.protocol.index', 'module' => 'documentProtocol'],
                ],
            ],
        ];

        if ($enabled === null) {
            return $groups;
        }

        return array_values(array_filter(array_map(function (array $group) use ($enabled) {
            $items = array_values(array_filter(
                $group['items'],
                fn (array $item) => isset($enabled[$item['module']]),
            ));
            if ($items === []) {
                return null;
            }
            $group['items'] = $items;

            return $group;
        }, $groups)));
    }
}
