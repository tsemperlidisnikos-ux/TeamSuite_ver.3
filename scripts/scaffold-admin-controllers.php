<?php

$map = [
    'AthleteController' => 'athletes',
    'ParentLinkController' => 'parents',
    'CoachController' => 'coaches',
    'StaffMemberController' => 'staff',
    'ClubMemberController' => 'members',
    'AcademyClassController' => 'classes',
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

$base = dirname(__DIR__) . '/app/Http/Controllers/Admin';

foreach ($map as $class => $key) {
    $path = "$base/$class.php";
    $content = <<<PHP
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
    file_put_contents($path, $content);
    echo "Wrote $class\n";
}

$pageMap = [
    'Athletes' => 'athletes',
    'Parents' => 'parents',
    'Coaches' => 'coaches',
    'Staff' => 'staff',
    'Members' => 'members',
    'Classes' => 'classes',
    'Trainings' => 'trainings',
    'Matches' => 'matches',
    'Schedule' => 'schedule',
    'Attendance' => 'attendance',
    'Fees' => 'fees',
    'Transactions' => 'transactions',
    'Warehouse' => 'warehouse',
    'Rentals' => 'rentals',
    'Announcements' => 'announcements',
    'Photos' => 'photos',
    'Partners' => 'partners',
    'Protocol' => 'protocol',
];

$pagesBase = dirname(__DIR__) . '/resources/js/Pages/Admin';

foreach ($pageMap as $folder => $key) {
    $dir = "$pagesBase/$folder";
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $content = <<<JSX
import ClubResourcePage from '@/Pages/Admin/ClubResourcePage';

export default function Index(props) {
    return <ClubResourcePage {...props} />;
}

JSX;
    file_put_contents("$dir/Index.jsx", $content);
    echo "Wrote page $folder/Index.jsx\n";
}
