<?php

/**
 * Generates Admin resource controllers + Inertia Index pages + route fragments.
 */

$modules = [
    'athletes' => [
        'model' => 'Athlete',
        'title' => 'Αθλητές',
        'controller' => 'AthleteController',
        'page' => 'Athletes',
        'fields' => [
            ['name' => 'first_name', 'label' => 'Όνομα', 'required' => true],
            ['name' => 'last_name', 'label' => 'Επώνυμο', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Τηλέφωνο'],
            ['name' => 'status', 'label' => 'Κατάσταση', 'type' => 'select', 'options' => [
                ['value' => 'active', 'label' => 'Ενεργός'],
                ['value' => 'inactive', 'label' => 'Ανενεργός'],
                ['value' => 'trial', 'label' => 'Δοκιμαστικός'],
            ], 'default' => 'active'],
            ['name' => 'monthly_fee', 'label' => 'Μηνιαία (€)', 'type' => 'number', 'default' => '0'],
        ],
        'columns' => [
            ['key' => 'last_name', 'label' => 'Επώνυμο'],
            ['key' => 'first_name', 'label' => 'Όνομα'],
            ['key' => 'status', 'label' => 'Κατάσταση'],
            ['key' => 'phone', 'label' => 'Τηλέφωνο'],
            ['key' => 'monthly_fee', 'label' => 'Συνδρομή'],
        ],
        'rules' => [
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:40',
            'status' => 'required|in:active,inactive,trial',
            'monthly_fee' => 'nullable|numeric|min:0',
        ],
        'order' => ['last_name', 'first_name'],
    ],
    'coaches' => [
        'model' => 'Coach',
        'title' => 'Προπονητές',
        'controller' => 'CoachController',
        'page' => 'Coaches',
        'fields' => [
            ['name' => 'first_name', 'label' => 'Όνομα', 'required' => true],
            ['name' => 'last_name', 'label' => 'Επώνυμο', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Τηλέφωνο'],
            ['name' => 'sport', 'label' => 'Άθλημα'],
            ['name' => 'gga_code', 'label' => 'Κωδικός ΓΓΑ'],
        ],
        'columns' => [
            ['key' => 'last_name', 'label' => 'Επώνυμο'],
            ['key' => 'first_name', 'label' => 'Όνομα'],
            ['key' => 'sport', 'label' => 'Άθλημα'],
            ['key' => 'phone', 'label' => 'Τηλέφωνο'],
        ],
        'rules' => [
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:40',
            'sport' => 'nullable|string|max:120',
            'gga_code' => 'nullable|string|max:64',
        ],
        'defaults' => ['is_active' => true],
        'order' => ['last_name', 'first_name'],
    ],
    'classes' => [
        'model' => 'AcademyClass',
        'title' => 'Τμήματα',
        'controller' => 'AcademyClassController',
        'page' => 'Classes',
        'routeName' => 'classes',
        'fields' => [
            ['name' => 'name', 'label' => 'Όνομα', 'required' => true],
            ['name' => 'sport', 'label' => 'Άθλημα'],
            ['name' => 'age_group', 'label' => 'Κατηγορία'],
            ['name' => 'monthly_fee', 'label' => 'Μηνιαία (€)', 'type' => 'number', 'default' => '0'],
            ['name' => 'max_students', 'label' => 'Μέγ. αθλητές', 'type' => 'number', 'default' => '30'],
        ],
        'columns' => [
            ['key' => 'name', 'label' => 'Όνομα'],
            ['key' => 'sport', 'label' => 'Άθλημα'],
            ['key' => 'age_group', 'label' => 'Κατηγορία'],
            ['key' => 'monthly_fee', 'label' => 'Συνδρομή'],
        ],
        'rules' => [
            'name' => 'required|string|max:160',
            'sport' => 'nullable|string|max:120',
            'age_group' => 'nullable|string|max:120',
            'monthly_fee' => 'nullable|numeric|min:0',
            'max_students' => 'nullable|integer|min:1',
        ],
        'order' => ['name'],
    ],
    'staff' => [
        'model' => 'StaffMember',
        'title' => 'Προσωπικό',
        'controller' => 'StaffMemberController',
        'page' => 'Staff',
        'fields' => [
            ['name' => 'first_name', 'label' => 'Όνομα', 'required' => true],
            ['name' => 'last_name', 'label' => 'Επώνυμο', 'required' => true],
            ['name' => 'role_title', 'label' => 'Ρόλος'],
            ['name' => 'phone', 'label' => 'Τηλέφωνο'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
        ],
        'columns' => [
            ['key' => 'last_name', 'label' => 'Επώνυμο'],
            ['key' => 'first_name', 'label' => 'Όνομα'],
            ['key' => 'role_title', 'label' => 'Ρόλος'],
            ['key' => 'phone', 'label' => 'Τηλέφωνο'],
        ],
        'rules' => [
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'role_title' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email',
        ],
        'defaults' => ['is_active' => true],
        'order' => ['last_name'],
    ],
    'members' => [
        'model' => 'ClubMember',
        'title' => 'Μέλη',
        'controller' => 'ClubMemberController',
        'page' => 'Members',
        'fields' => [
            ['name' => 'first_name', 'label' => 'Όνομα', 'required' => true],
            ['name' => 'last_name', 'label' => 'Επώνυμο', 'required' => true],
            ['name' => 'member_number', 'label' => 'Αρ. μητρώου'],
            ['name' => 'phone', 'label' => 'Τηλέφωνο'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
        ],
        'columns' => [
            ['key' => 'last_name', 'label' => 'Επώνυμο'],
            ['key' => 'first_name', 'label' => 'Όνομα'],
            ['key' => 'member_number', 'label' => 'Μητρώο'],
            ['key' => 'status', 'label' => 'Κατάσταση'],
        ],
        'rules' => [
            'first_name' => 'required|string|max:120',
            'last_name' => 'required|string|max:120',
            'member_number' => 'nullable|string|max:64',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email',
        ],
        'defaults' => ['status' => 'active'],
        'order' => ['last_name'],
    ],
    'trainings' => [
        'model' => 'Training',
        'title' => 'Προπονήσεις',
        'controller' => 'TrainingController',
        'page' => 'Trainings',
        'fields' => [
            ['name' => 'title', 'label' => 'Τίτλος'],
            ['name' => 'starts_at', 'label' => 'Έναρξη', 'type' => 'datetime-local', 'required' => true],
            ['name' => 'ends_at', 'label' => 'Λήξη', 'type' => 'datetime-local'],
            ['name' => 'status', 'label' => 'Κατάσταση', 'default' => 'scheduled'],
        ],
        'columns' => [
            ['key' => 'title', 'label' => 'Τίτλος'],
            ['key' => 'starts_at', 'label' => 'Έναρξη'],
            ['key' => 'status', 'label' => 'Κατάσταση'],
        ],
        'rules' => [
            'title' => 'nullable|string|max:200',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date',
            'status' => 'nullable|string|max:32',
        ],
        'order' => ['starts_at'],
        'orderDir' => 'desc',
    ],
    'attendance' => [
        'model' => 'Attendance',
        'title' => 'Παρουσίες',
        'controller' => 'AttendanceController',
        'page' => 'Attendance',
        'fields' => [
            ['name' => 'athlete_id', 'label' => 'ID αθλητή', 'required' => true],
            ['name' => 'status', 'label' => 'Κατάσταση', 'default' => 'present'],
            ['name' => 'attended_on', 'label' => 'Ημ/νία', 'type' => 'date'],
        ],
        'columns' => [
            ['key' => 'athlete_id', 'label' => 'Αθλητής'],
            ['key' => 'status', 'label' => 'Κατάσταση'],
            ['key' => 'attended_on', 'label' => 'Ημ/νία'],
        ],
        'rules' => [
            'athlete_id' => 'required|uuid',
            'status' => 'required|string|max:32',
            'attended_on' => 'nullable|date',
        ],
        'order' => ['created_at'],
        'orderDir' => 'desc',
    ],
    'matches' => [
        'model' => 'SportMatch',
        'title' => 'Αγώνες',
        'controller' => 'MatchController',
        'page' => 'Matches',
        'fields' => [
            ['name' => 'opponent', 'label' => 'Αντίπαλος', 'required' => true],
            ['name' => 'starts_at', 'label' => 'Ημ/νία', 'type' => 'datetime-local', 'required' => true],
            ['name' => 'venue', 'label' => 'Έδρα'],
            ['name' => 'home_away', 'label' => 'Εντός/Εκτός'],
        ],
        'columns' => [
            ['key' => 'opponent', 'label' => 'Αντίπαλος'],
            ['key' => 'starts_at', 'label' => 'Ημ/νία'],
            ['key' => 'venue', 'label' => 'Τόπος'],
            ['key' => 'status', 'label' => 'Κατάσταση'],
        ],
        'rules' => [
            'opponent' => 'required|string|max:200',
            'starts_at' => 'required|date',
            'venue' => 'nullable|string|max:200',
            'home_away' => 'nullable|string|max:16',
        ],
        'defaults' => ['status' => 'scheduled'],
        'order' => ['starts_at'],
        'orderDir' => 'desc',
    ],
    'schedule' => [
        'model' => 'ScheduleSlot',
        'title' => 'Πρόγραμμα',
        'controller' => 'ScheduleController',
        'page' => 'Schedule',
        'fields' => [
            ['name' => 'title', 'label' => 'Τίτλος'],
            ['name' => 'weekday', 'label' => 'Ημέρα (0-6)', 'type' => 'number'],
            ['name' => 'starts_at', 'label' => 'Από', 'type' => 'time'],
            ['name' => 'ends_at', 'label' => 'Έως', 'type' => 'time'],
        ],
        'columns' => [
            ['key' => 'title', 'label' => 'Τίτλος'],
            ['key' => 'weekday', 'label' => 'Ημέρα'],
            ['key' => 'starts_at', 'label' => 'Από'],
            ['key' => 'ends_at', 'label' => 'Έως'],
        ],
        'rules' => [
            'title' => 'nullable|string|max:200',
            'weekday' => 'nullable|integer|min:0|max:6',
            'starts_at' => 'nullable',
            'ends_at' => 'nullable',
        ],
        'order' => ['weekday'],
    ],
    'announcements' => [
        'model' => 'Announcement',
        'title' => 'Ανακοινώσεις',
        'controller' => 'AnnouncementController',
        'page' => 'Announcements',
        'fields' => [
            ['name' => 'title', 'label' => 'Τίτλος', 'required' => true],
            ['name' => 'body', 'label' => 'Κείμενο'],
        ],
        'columns' => [
            ['key' => 'title', 'label' => 'Τίτλος'],
            ['key' => 'published_at', 'label' => 'Δημοσίευση'],
        ],
        'rules' => [
            'title' => 'required|string|max:200',
            'body' => 'nullable|string',
        ],
        'order' => ['created_at'],
        'orderDir' => 'desc',
    ],
    'fees' => [
        'model' => 'FeeChargeTemplate',
        'title' => 'Συνδρομές / Πρότυπα',
        'controller' => 'FeeController',
        'page' => 'Fees',
        'fields' => [
            ['name' => 'name', 'label' => 'Όνομα', 'required' => true],
            ['name' => 'amount', 'label' => 'Ποσό', 'type' => 'number', 'required' => true],
        ],
        'columns' => [
            ['key' => 'name', 'label' => 'Όνομα'],
            ['key' => 'amount', 'label' => 'Ποσό'],
            ['key' => 'is_active', 'label' => 'Ενεργό'],
        ],
        'rules' => [
            'name' => 'required|string|max:200',
            'amount' => 'required|numeric|min:0',
        ],
        'defaults' => ['auto_generate' => true, 'is_active' => true],
        'order' => ['name'],
    ],
    'transactions' => [
        'model' => 'AthleteTransaction',
        'title' => 'Συναλλαγές',
        'controller' => 'TransactionController',
        'page' => 'Transactions',
        'fields' => [
            ['name' => 'athlete_id', 'label' => 'ID αθλητή', 'required' => true],
            ['name' => 'type', 'label' => 'Τύπος', 'type' => 'select', 'options' => [
                ['value' => 'charge', 'label' => 'Χρέωση'],
                ['value' => 'payment', 'label' => 'Πληρωμή'],
            ], 'default' => 'charge'],
            ['name' => 'amount', 'label' => 'Ποσό', 'type' => 'number', 'required' => true],
            ['name' => 'month', 'label' => 'Μήνας', 'type' => 'number'],
            ['name' => 'year', 'label' => 'Έτος', 'type' => 'number'],
            ['name' => 'payment_method', 'label' => 'Τρόπος'],
        ],
        'columns' => [
            ['key' => 'type', 'label' => 'Τύπος'],
            ['key' => 'amount', 'label' => 'Ποσό'],
            ['key' => 'month', 'label' => 'Μήνας'],
            ['key' => 'year', 'label' => 'Έτος'],
            ['key' => 'payment_method', 'label' => 'Τρόπος'],
        ],
        'rules' => [
            'athlete_id' => 'required|uuid',
            'type' => 'required|in:charge,payment',
            'amount' => 'required|numeric',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'payment_method' => 'nullable|string|max:32',
        ],
        'order' => ['created_at'],
        'orderDir' => 'desc',
    ],
    'warehouse' => [
        'model' => 'WarehouseProduct',
        'title' => 'Αποθήκη',
        'controller' => 'WarehouseController',
        'page' => 'Warehouse',
        'fields' => [
            ['name' => 'name', 'label' => 'Προϊόν', 'required' => true],
            ['name' => 'sku', 'label' => 'SKU'],
            ['name' => 'stock_qty', 'label' => 'Απόθεμα', 'type' => 'number', 'default' => '0'],
            ['name' => 'unit_price', 'label' => 'Τιμή', 'type' => 'number', 'default' => '0'],
        ],
        'columns' => [
            ['key' => 'name', 'label' => 'Προϊόν'],
            ['key' => 'sku', 'label' => 'SKU'],
            ['key' => 'stock_qty', 'label' => 'Απόθεμα'],
            ['key' => 'unit_price', 'label' => 'Τιμή'],
        ],
        'rules' => [
            'name' => 'required|string|max:200',
            'sku' => 'nullable|string|max:64',
            'stock_qty' => 'nullable|integer',
            'unit_price' => 'nullable|numeric|min:0',
        ],
        'order' => ['name'],
    ],
    'rentals' => [
        'model' => 'RentalBooking',
        'title' => 'Ενοικίαση',
        'controller' => 'RentalController',
        'page' => 'Rentals',
        'fields' => [
            ['name' => 'customer_name', 'label' => 'Πελάτης', 'required' => true],
            ['name' => 'customer_phone', 'label' => 'Τηλέφωνο'],
            ['name' => 'starts_at', 'label' => 'Έναρξη', 'type' => 'datetime-local', 'required' => true],
            ['name' => 'amount', 'label' => 'Ποσό', 'type' => 'number', 'default' => '0'],
        ],
        'columns' => [
            ['key' => 'customer_name', 'label' => 'Πελάτης'],
            ['key' => 'starts_at', 'label' => 'Έναρξη'],
            ['key' => 'amount', 'label' => 'Ποσό'],
            ['key' => 'status', 'label' => 'Κατάσταση'],
        ],
        'rules' => [
            'customer_name' => 'required|string|max:200',
            'customer_phone' => 'nullable|string|max:40',
            'starts_at' => 'required|date',
            'amount' => 'nullable|numeric|min:0',
        ],
        'defaults' => ['status' => 'pending'],
        'order' => ['starts_at'],
        'orderDir' => 'desc',
    ],
    'protocol' => [
        'model' => 'DocumentProtocolEntry',
        'title' => 'Πρωτόκολλο',
        'controller' => 'ProtocolController',
        'page' => 'Protocol',
        'fields' => [
            ['name' => 'direction', 'label' => 'Κατεύθυνση', 'type' => 'select', 'options' => [
                ['value' => 'in', 'label' => 'Εισερχόμενο'],
                ['value' => 'out', 'label' => 'Εξερχόμενο'],
            ], 'default' => 'in'],
            ['name' => 'protocol_number', 'label' => 'Αριθμός', 'type' => 'number', 'required' => true],
            ['name' => 'subject', 'label' => 'Θέμα', 'required' => true],
            ['name' => 'document_date', 'label' => 'Ημ/νία', 'type' => 'date'],
        ],
        'columns' => [
            ['key' => 'protocol_number', 'label' => 'Αρ.'],
            ['key' => 'direction', 'label' => 'Κατ.'],
            ['key' => 'subject', 'label' => 'Θέμα'],
            ['key' => 'document_date', 'label' => 'Ημ/νία'],
        ],
        'rules' => [
            'direction' => 'required|in:in,out',
            'protocol_number' => 'required|integer|min:1',
            'subject' => 'required|string|max:255',
            'document_date' => 'nullable|date',
        ],
        'order' => ['protocol_number'],
        'orderDir' => 'desc',
    ],
    'partners' => [
        'model' => 'PartnerBusiness',
        'title' => 'Συμβεβλημένες επιχειρήσεις',
        'controller' => 'PartnerController',
        'page' => 'Partners',
        'fields' => [
            ['name' => 'name', 'label' => 'Επωνυμία', 'required' => true],
            ['name' => 'contact', 'label' => 'Επαφή'],
        ],
        'columns' => [
            ['key' => 'name', 'label' => 'Επωνυμία'],
            ['key' => 'contact', 'label' => 'Επαφή'],
        ],
        'rules' => [
            'name' => 'required|string|max:200',
            'contact' => 'nullable|string|max:200',
        ],
        'order' => ['name'],
    ],
    'photos' => [
        'model' => 'GalleryPhoto',
        'title' => 'Φωτογραφίες',
        'controller' => 'PhotoController',
        'page' => 'Photos',
        'fields' => [
            ['name' => 'title', 'label' => 'Τίτλος'],
            ['name' => 'path', 'label' => 'Διαδρομή/URL', 'required' => true],
        ],
        'columns' => [
            ['key' => 'title', 'label' => 'Τίτλος'],
            ['key' => 'path', 'label' => 'Αρχείο'],
            ['key' => 'gdpr_cleared', 'label' => 'GDPR'],
        ],
        'rules' => [
            'title' => 'nullable|string|max:200',
            'path' => 'required|string|max:500',
        ],
        'defaults' => ['gdpr_cleared' => false],
        'order' => ['created_at'],
        'orderDir' => 'desc',
    ],
];

$ctrlDir = __DIR__ . '/../app/Http/Controllers/Admin';
$pagesDir = __DIR__ . '/../resources/js/Pages/Admin';

foreach ($modules as $routeKey => $cfg) {
    $controller = $cfg['controller'];
    $model = $cfg['model'];
    $page = $cfg['page'];
    $title = $cfg['title'];
    $routeName = $cfg['routeName'] ?? $routeKey;
    $rulesExport = var_export($cfg['rules'], true);
    $defaultsExport = var_export($cfg['defaults'] ?? [], true);
    $order = $cfg['order'] ?? ['created_at'];
    $orderDir = $cfg['orderDir'] ?? 'asc';
    $orderCalls = '';
    foreach ($order as $col) {
        $orderCalls .= "            ->orderBy('{$col}', '{$orderDir}')\n";
    }

    $ctrl = <<<PHP
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\\{$model};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class {$controller} extends Controller
{
    use InteractsWithClubResource;

    public function index(Request \$request): Response
    {
        \$rows = \$this->clubQuery({$model}::class, \$request)
{$orderCalls}            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/{$page}/Index', [
            'rows' => \$rows,
        ]);
    }

    public function store(Request \$request): RedirectResponse
    {
        \$data = \$request->validate({$rulesExport});
        \$data = array_merge({$defaultsExport}, \$data);
        \$data['club_id'] = \$this->clubId(\$request);

        {$model}::query()->withoutGlobalScopes()->create(\$data);

        return back()->with('success', 'Αποθηκεύτηκε.');
    }

    public function destroy(Request \$request, string \$id): RedirectResponse
    {
        \$row = \$this->clubQuery({$model}::class, \$request)->whereKey(\$id)->firstOrFail();
        \$row->delete();

        return back()->with('success', 'Διαγράφηκε.');
    }
}

PHP;
    file_put_contents("{$ctrlDir}/{$controller}.php", $ctrl);

    $pageDir = "{$pagesDir}/{$page}";
    if (! is_dir($pageDir)) {
        mkdir($pageDir, 0777, true);
    }

    $fieldsJson = json_encode($cfg['fields'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $columnsJson = json_encode($cfg['columns'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $jsx = <<<JSX
import AdminLayout from '@/Layouts/AdminLayout';
import ResourceTable from '@/Components/ResourceTable';
import { Head } from '@inertiajs/react';

const fields = {$fieldsJson};

const columns = {$columnsJson};

export default function Index({ rows }) {
    return (
        <AdminLayout title="{$title}">
            <Head title="{$title}" />
            <ResourceTable
                title="{$title}"
                columns={columns}
                rows={rows.data || []}
                storeRoute="admin.{$routeName}.store"
                destroyRoutePrefix="admin.{$routeName}.destroy"
                fields={fields}
            />
        </AdminLayout>
    );
}

JSX;
    file_put_contents("{$pageDir}/Index.jsx", $jsx);
    echo "Generated {$controller} + {$page}/Index\n";
}

echo "DONE\n";
