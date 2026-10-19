<?php

$map = [
    'AthleteController' => 'athletes',
    'CoachController' => 'coaches',
    'AcademyClassController' => 'classes',
    'StaffMemberController' => 'staff',
    'ClubMemberController' => 'members',
    'TrainingController' => 'trainings',
    'MatchController' => 'matches',
    'ScheduleController' => 'schedule',
    'AttendanceController' => 'attendance',
    'FeeController' => 'fees',
    'TransactionController' => 'transactions',
    'WarehouseController' => 'warehouse',
    'RentalController' => 'rentals',
    'AnnouncementController' => 'announcements',
    'PhotoController' => 'photos',
    'PartnerController' => 'partners',
    'ProtocolController' => 'protocol',
];

$dir = __DIR__.'/../app/Http/Controllers/Admin';
$pagesRoot = __DIR__.'/../resources/js/Pages';

foreach ($map as $class => $key) {
    $php = <<<PHP
<?php

namespace App\Http\Controllers\Admin;

class {$class} extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return '{$key}';
    }
}

PHP;
    file_put_contents("{$dir}/{$class}.php", $php);
    echo "Controller {$class}\n";
}

// Point Index pages at shared ClubResourcePage
$pageDirs = [
    'Admin/Athletes', 'Admin/Coaches', 'Admin/Classes', 'Admin/Staff', 'Admin/Members',
    'Admin/Trainings', 'Admin/Matches', 'Admin/Schedule', 'Admin/Attendance', 'Admin/Fees',
    'Admin/Transactions', 'Admin/Warehouse', 'Admin/Rentals', 'Admin/Announcements',
    'Admin/Photos', 'Admin/Partners', 'Admin/Protocol',
];

$jsx = <<<'JSX'
import ClubResourcePage from '@/Pages/Admin/ClubResourcePage';

export default function Index(props) {
    return <ClubResourcePage {...props} />;
}

JSX;

foreach ($pageDirs as $rel) {
    $path = "{$pagesRoot}/{$rel}/Index.jsx";
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $jsx);
    echo "Page {$rel}/Index.jsx\n";
}

echo "DONE\n";
