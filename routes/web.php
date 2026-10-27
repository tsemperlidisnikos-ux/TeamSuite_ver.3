<?php

use App\Http\Controllers\Admin\AcademyClassController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AssociationController;
use App\Http\Controllers\Admin\AthleteController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\BackupRestoreController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\ClubMemberController;
use App\Http\Controllers\Admin\CoachController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeeController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\MatchController;
use App\Http\Controllers\Admin\ParentLinkController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\PhotoController;
use App\Http\Controllers\Admin\PrintController;
use App\Http\Controllers\Admin\ProtocolController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RentalController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SportController;
use App\Http\Controllers\Admin\StaffMemberController;
use App\Http\Controllers\Admin\TrainingController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Platform\ClubActivityLogController;
use App\Http\Controllers\Platform\ClubController as PlatformClubController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\LicensePackageController;
use App\Http\Controllers\Platform\LoginActivityController;
use App\Http\Controllers\Platform\PlatformBackupController;
use App\Http\Controllers\Platform\PlatformSettingsController;
use App\Http\Controllers\Platform\PlatformUserController;
use App\Http\Controllers\Platform\WaitlistController;
use App\Http\Controllers\Portal\AthletePortalController;
use App\Http\Controllers\Portal\CoachPortalController;
use App\Http\Controllers\Portal\OnlineCheckoutController;
use App\Http\Controllers\Portal\ParentPortalController;
use App\Http\Controllers\Portal\PushSubscriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\AthleteIdCardController;
use App\Http\Controllers\Site\JoinController;
use App\Http\Controllers\Site\LegalController;
use App\Http\Controllers\Site\RentController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()?->is_platform_admin
        ? redirect()->route('platform.dashboard')
        : redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified', 'club'])->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

    // Athletes — full profile + import/export
    Route::get('/admin/athletes', [AthleteController::class, 'index'])->name('admin.athletes.index');
    Route::post('/admin/athletes', [AthleteController::class, 'store'])->name('admin.athletes.store');
    Route::post('/admin/athletes/bulk', [AthleteController::class, 'bulkUpdate'])->name('admin.athletes.bulk');
    Route::get('/admin/athletes/export', [AthleteController::class, 'export'])->name('admin.athletes.export');
    Route::post('/admin/athletes/import', [AthleteController::class, 'import'])->name('admin.athletes.import');
    Route::patch('/admin/athletes/applications/{id}', [AthleteController::class, 'updateApplication'])->name('admin.athletes.applications.update');
    Route::post('/admin/athletes/applications/{id}/approve', [AthleteController::class, 'approveApplication'])->name('admin.athletes.applications.approve');
    Route::post('/admin/athletes/applications/{id}/reject', [AthleteController::class, 'rejectApplication'])->name('admin.athletes.applications.reject');
    Route::post('/admin/athletes/health-reminders', [AthleteController::class, 'remindHealthCards'])->name('admin.athletes.health-reminders');
    Route::get('/admin/athletes/{id}', [AthleteController::class, 'show'])->name('admin.athletes.show');
    Route::patch('/admin/athletes/{id}', [AthleteController::class, 'update'])->name('admin.athletes.update');
    Route::delete('/admin/athletes/{id}', [AthleteController::class, 'destroy'])->name('admin.athletes.destroy');
    Route::post('/admin/athletes/{id}/progress', [AthleteController::class, 'storeProgress'])->name('admin.athletes.progress.store');

    // Attendance grid
    Route::get('/admin/attendance', [AttendanceController::class, 'index'])->name('admin.attendance.index');
    Route::post('/admin/attendance', [AttendanceController::class, 'upsert'])->name('admin.attendance.upsert');
    Route::post('/admin/attendance/mark-all', [AttendanceController::class, 'markAll'])->name('admin.attendance.mark-all');

    // Transactions per athlete
    Route::get('/admin/transactions', [TransactionController::class, 'index'])->name('admin.transactions.index');
    Route::post('/admin/transactions', [TransactionController::class, 'store'])->name('admin.transactions.store');
    Route::patch('/admin/transactions/{id}', [TransactionController::class, 'update'])->name('admin.transactions.update');
    Route::delete('/admin/transactions/{id}', [TransactionController::class, 'destroy'])->name('admin.transactions.destroy');

    // Classes + profile
    Route::get('/admin/classes', [AcademyClassController::class, 'index'])->name('admin.classes.index');
    Route::post('/admin/classes', [AcademyClassController::class, 'store'])->name('admin.classes.store');
    Route::get('/admin/classes/{id}', [AcademyClassController::class, 'show'])->name('admin.classes.show');
    Route::patch('/admin/classes/{id}', [AcademyClassController::class, 'update'])->name('admin.classes.update');
    Route::delete('/admin/classes/{id}', [AcademyClassController::class, 'destroy'])->name('admin.classes.destroy');
    Route::post('/admin/classes/{id}/athletes', [AcademyClassController::class, 'attachAthlete'])->name('admin.classes.athletes.attach');
    Route::delete('/admin/classes/{id}/athletes/{athleteId}', [AcademyClassController::class, 'detachAthlete'])->name('admin.classes.athletes.detach');

    // Fees — debts + bulk charges
    Route::get('/admin/fees', [FeeController::class, 'index'])->name('admin.fees.index');
    Route::post('/admin/fees/templates', [FeeController::class, 'storeTemplate'])->name('admin.fees.templates.store');
    Route::patch('/admin/fees/templates/{id}', [FeeController::class, 'updateTemplate'])->name('admin.fees.templates.update');
    Route::delete('/admin/fees/templates/{id}', [FeeController::class, 'destroyTemplate'])->name('admin.fees.templates.destroy');
    Route::post('/admin/fees/bulk-charge', [FeeController::class, 'bulkCharge'])->name('admin.fees.bulk-charge');
    Route::post('/admin/fees/generate-from-templates', [FeeController::class, 'generateFromTemplates'])->name('admin.fees.generate-from-templates');
    Route::post('/admin/fees/remind-debts', [FeeController::class, 'remindDebts'])->name('admin.fees.remind-debts');

    // Announcements with audience
    Route::get('/admin/announcements', [AnnouncementController::class, 'index'])->name('admin.announcements.index');
    Route::post('/admin/announcements', [AnnouncementController::class, 'store'])->name('admin.announcements.store');
    Route::patch('/admin/announcements/{id}', [AnnouncementController::class, 'update'])->name('admin.announcements.update');
    Route::post('/admin/announcements/{id}/resend-failed', [AnnouncementController::class, 'resendFailed'])->name('admin.announcements.resend-failed');
    Route::delete('/admin/announcements/{id}', [AnnouncementController::class, 'destroy'])->name('admin.announcements.destroy');

    $resources = [
        'coaches' => CoachController::class,
        'staff' => StaffMemberController::class,
        'members' => ClubMemberController::class,
        'trainings' => TrainingController::class,
        'matches' => MatchController::class,
        'schedule' => ScheduleController::class,
        'warehouse' => WarehouseController::class,
        'rentals' => RentalController::class,
        'photos' => PhotoController::class,
        'partners' => PartnerController::class,
        'protocol' => ProtocolController::class,
        'associations' => AssociationController::class,
    ];

    // Άθλημα is managed under Ρυθμίσεις (?tab=sports), not a standalone sidebar page.
    Route::redirect('/admin/sports', '/admin/settings?tab=sports')->name('admin.sports.index');
    Route::post('/admin/sports', [SportController::class, 'store'])->name('admin.sports.store');
    Route::patch('/admin/sports/{id}', [SportController::class, 'update'])->name('admin.sports.update');
    Route::delete('/admin/sports/{id}', [SportController::class, 'destroy'])->name('admin.sports.destroy');

    Route::post('/admin/trainings/remind-tomorrow', [TrainingController::class, 'remindTomorrow'])
        ->name('admin.trainings.remind-tomorrow');
    Route::post('/admin/trainings/bulk', [TrainingController::class, 'bulkUpdate'])
        ->name('admin.trainings.bulk');
    Route::post('/admin/trainings/recurring', [TrainingController::class, 'createRecurring'])
        ->name('admin.trainings.recurring');
    Route::post('/admin/schedule/generate-trainings', [ScheduleController::class, 'generateTrainings'])
        ->name('admin.schedule.generate-trainings');
    Route::post('/admin/coaches/bulk', [CoachController::class, 'bulkUpdate'])
        ->name('admin.coaches.bulk');
    Route::post('/admin/staff/bulk', [StaffMemberController::class, 'bulkUpdate'])
        ->name('admin.staff.bulk');
    Route::post('/admin/rentals/availability', [RentalController::class, 'saveAvailability'])
        ->name('admin.rentals.save-availability');
    Route::post('/admin/rentals/book', [RentalController::class, 'book'])
        ->name('admin.rentals.book');
    Route::post('/admin/rentals/{id}/collect', [RentalController::class, 'collect'])
        ->name('admin.rentals.collect');
    Route::post('/admin/rentals/{id}/cancel', [RentalController::class, 'cancel'])
        ->name('admin.rentals.cancel');

    foreach ($resources as $name => $controller) {
        Route::get("/admin/{$name}", [$controller, 'index'])->name("admin.{$name}.index");
        Route::post("/admin/{$name}", [$controller, 'store'])->name("admin.{$name}.store");
        Route::patch("/admin/{$name}/{id}", [$controller, 'update'])->name("admin.{$name}.update");
        Route::delete("/admin/{$name}/{id}", [$controller, 'destroy'])->name("admin.{$name}.destroy");
    }

    Route::get('/admin/parents', [ParentLinkController::class, 'index'])->name('admin.parents.index');
    Route::post('/admin/parents', [ParentLinkController::class, 'store'])->name('admin.parents.store');
    Route::patch('/admin/parents/{id}', [ParentLinkController::class, 'update'])->name('admin.parents.update');
    Route::delete('/admin/parents/{id}', [ParentLinkController::class, 'destroy'])->name('admin.parents.destroy');

    Route::get('/admin/calendar', [CalendarController::class, 'index'])->name('admin.calendar.index');

    Route::get('/admin/finance', [FinanceController::class, 'index'])->name('admin.finance.index');
    Route::post('/admin/finance/revenues', [FinanceController::class, 'storeRevenue'])->name('admin.finance.revenues.store');
    Route::post('/admin/finance/expenses', [FinanceController::class, 'storeExpense'])->name('admin.finance.expenses.store');
    Route::post('/admin/finance/accounts', [FinanceController::class, 'storeCashAccount'])->name('admin.finance.accounts.store');
    Route::post('/admin/finance/close-month', [FinanceController::class, 'closeMonth'])->name('admin.finance.close-month');
    Route::post('/admin/finance/budget', [FinanceController::class, 'storeBudgetLine'])->name('admin.finance.budget.store');
    Route::patch('/admin/finance/budget/{id}', [FinanceController::class, 'updateBudgetLine'])->name('admin.finance.budget.update');
    Route::delete('/admin/finance/budget/{id}', [FinanceController::class, 'destroyBudgetLine'])->name('admin.finance.budget.destroy');
    Route::get('/admin/finance/reports/export', [FinanceController::class, 'exportReports'])->name('admin.finance.reports.export');
    Route::get('/admin/finance/balance/export', [FinanceController::class, 'exportBalance'])->name('admin.finance.balance.export');

    Route::get('/admin/prints', [PrintController::class, 'index'])->name('admin.prints.index');

    Route::get('/admin/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
    Route::post('/admin/settings/club', [SettingsController::class, 'updateClub'])->name('admin.settings.club.update');
    Route::post('/admin/settings/email', [SettingsController::class, 'updateEmail'])->name('admin.settings.email.update');
    Route::post('/admin/settings/sms', [SettingsController::class, 'updateSms'])->name('admin.settings.sms.update');
    Route::post('/admin/settings/payments', [SettingsController::class, 'updatePayments'])->name('admin.settings.payments.update');
    Route::post('/admin/settings/public-registration', [SettingsController::class, 'updatePublicRegistration'])->name('admin.settings.public.update');
    Route::post('/admin/settings/terms', [SettingsController::class, 'updateTerms'])->name('admin.settings.terms.update');
    Route::post('/admin/settings/gdpr', [SettingsController::class, 'updateGdpr'])->name('admin.settings.gdpr.update');

    Route::post('/admin/settings/users', [SettingsController::class, 'storeUser'])->name('admin.settings.users.store');
    Route::patch('/admin/settings/users/{id}', [SettingsController::class, 'updateUser'])->name('admin.settings.users.update');
    Route::delete('/admin/settings/users/{id}', [SettingsController::class, 'destroyUser'])->name('admin.settings.users.destroy');

    Route::post('/admin/settings/sports', [SettingsController::class, 'storeSport'])->name('admin.settings.sports.store');
    Route::delete('/admin/settings/sports/{id}', [SettingsController::class, 'destroySport'])->name('admin.settings.sports.destroy');
    Route::post('/admin/settings/facilities', [SettingsController::class, 'storeFacility'])->name('admin.settings.facilities.store');
    Route::delete('/admin/settings/facilities/{id}', [SettingsController::class, 'destroyFacility'])->name('admin.settings.facilities.destroy');
    Route::post('/admin/settings/seasons', [SettingsController::class, 'storeSeason'])->name('admin.settings.seasons.store');
    Route::patch('/admin/settings/seasons/{id}', [SettingsController::class, 'updateSeason'])->name('admin.settings.seasons.update');
    Route::delete('/admin/settings/seasons/{id}', [SettingsController::class, 'destroySeason'])->name('admin.settings.seasons.destroy');

    Route::post('/admin/settings/associations', [SettingsController::class, 'storeAssociation'])->name('admin.settings.associations.store');
    Route::delete('/admin/settings/associations/{id}', [SettingsController::class, 'destroyAssociation'])->name('admin.settings.associations.destroy');
    Route::post('/admin/settings/sizes', [SettingsController::class, 'updateSizeChart'])->name('admin.settings.sizes.update');
    Route::post('/admin/settings/clothing', [SettingsController::class, 'storeClothing'])->name('admin.settings.clothing.store');
    Route::delete('/admin/settings/clothing/{id}', [SettingsController::class, 'destroyClothing'])->name('admin.settings.clothing.destroy');
    Route::post('/admin/settings/discounts', [SettingsController::class, 'storeDiscount'])->name('admin.settings.discounts.store');
    Route::delete('/admin/settings/discounts/{id}', [SettingsController::class, 'destroyDiscount'])->name('admin.settings.discounts.destroy');
    Route::post('/admin/settings/receipts', [SettingsController::class, 'storeReceiptSeries'])->name('admin.settings.receipts.store');
    Route::delete('/admin/settings/receipts/{id}', [SettingsController::class, 'destroyReceiptSeries'])->name('admin.settings.receipts.destroy');

    Route::get('/admin/receipts', [ReceiptController::class, 'index'])->name('admin.receipts.index');
    Route::get('/admin/receipts/export', [ReceiptController::class, 'exportCsv'])->name('admin.receipts.export');
    Route::post('/admin/receipts/{id}/cancel', [ReceiptController::class, 'cancel'])->name('admin.receipts.cancel');

    Route::get('/admin/settings/backup', [BackupRestoreController::class, 'index'])->name('admin.settings.backup.index');
    Route::get('/admin/settings/backup/download', [BackupRestoreController::class, 'download'])->name('admin.settings.backup.download');
    Route::post('/admin/settings/backup/preview', [BackupRestoreController::class, 'preview'])->name('admin.settings.backup.preview');
    Route::post('/admin/settings/backup/restore', [BackupRestoreController::class, 'restore'])->name('admin.settings.backup.restore');
    Route::post('/admin/settings/backup/confirm', [BackupRestoreController::class, 'restore'])->name('admin.settings.backup.confirm');

    /*
    | TeamSuite path aliases (1:1 URLs from legacy app).
    | Registered after named /admin/* routes so redirects resolve safely.
    */
    $legacy = [
        'athletes' => '/admin/athletes',
        'students' => '/admin/athletes',
        'calendar' => '/admin/calendar',
        'staff' => '/admin/staff',
        'coaches' => '/admin/coaches',
        'classes' => '/admin/classes',
        'parents' => '/admin/parents',
        'members' => '/admin/members',
        'trainings' => '/admin/trainings',
        'matches' => '/admin/matches',
        'schedule' => '/admin/schedule',
        'attendance' => '/admin/attendance',
        'associations' => '/admin/associations',
        'announcements' => '/admin/announcements',
        'prints' => '/admin/prints',
        'photos' => '/admin/photos',
        'warehouse' => '/admin/warehouse',
        'fees' => '/admin/fees',
        'transactions' => '/admin/transactions',
        'partner-businesses' => '/admin/partners',
        'document-protocol' => '/admin/protocol',
        'rental' => '/admin/rentals',
        'finance' => '/admin/finance',
        'settings' => '/admin/settings',
    ];

    foreach ($legacy as $path => $target) {
        Route::redirect('/'.$path, $target);
    }

    Route::redirect('/sports', '/admin/settings?tab=sports');

    Route::get('/athletes/{id}', fn (string $id) => redirect('/admin/athletes/'.$id));
    Route::get('/classes/{id}', fn (string $id) => redirect('/admin/classes/'.$id));
});

Route::middleware(['auth', 'verified', 'platform'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/', PlatformDashboardController::class)->name('dashboard');
    Route::get('/backup/download', [PlatformBackupController::class, 'download'])->name('backup.download');
    Route::post('/settings', [PlatformSettingsController::class, 'update'])->name('settings.update');
    Route::get('/users', [PlatformUserController::class, 'index'])->name('users.index');
    Route::post('/users', [PlatformUserController::class, 'store'])->name('users.store');
    Route::post('/users/enter', [PlatformUserController::class, 'enter'])->name('users.enter');
    Route::delete('/users/{id}', [PlatformUserController::class, 'destroy'])->name('users.destroy');
    Route::get('/clubs', [PlatformClubController::class, 'index'])->name('clubs.index');
    Route::post('/clubs/{id}/activate', [PlatformClubController::class, 'activate'])->name('clubs.activate');
    Route::patch('/clubs/{id}/license', [PlatformClubController::class, 'updateLicense'])->name('clubs.license');
    Route::delete('/clubs/{id}', [PlatformClubController::class, 'destroy'])->name('clubs.destroy');
    Route::get('/waitlist', [WaitlistController::class, 'index'])->name('waitlist.index');
    Route::post('/waitlist', [WaitlistController::class, 'store'])->name('waitlist.store');
    Route::patch('/waitlist/{id}', [WaitlistController::class, 'update'])->name('waitlist.update');
    Route::delete('/waitlist/{id}', [WaitlistController::class, 'destroy'])->name('waitlist.destroy');
    Route::get('/logins', [LoginActivityController::class, 'index'])->name('logins.index');
    Route::get('/activity', [ClubActivityLogController::class, 'index'])->name('activity.index');
    Route::get('/license-packages', [LicensePackageController::class, 'index'])->name('license-packages.index');
    Route::post('/license-packages', [LicensePackageController::class, 'store'])->name('license-packages.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/platform/users/leave', [PlatformUserController::class, 'leave'])->name('platform.users.leave');

    Route::get('/app/parent', [ParentPortalController::class, 'index'])->name('portal.parent');
    Route::post('/app/parent/absence', [ParentPortalController::class, 'submitAbsence'])->name('portal.parent.absence');
    Route::post('/app/parent/checkout', [OnlineCheckoutController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('portal.parent.checkout');
    Route::get('/push/vapid', [PushSubscriptionController::class, 'vapid'])
        ->name('push.vapid');
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe'])
        ->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])
        ->name('push.unsubscribe');
    Route::get('/app/coach', [CoachPortalController::class, 'index'])->name('portal.coach');
    Route::post('/app/coach/attendance', [CoachPortalController::class, 'saveAttendance'])->name('portal.coach.attendance');
    Route::get('/app/athlete', AthletePortalController::class)->name('portal.athlete');
});

Route::get('/legal/{doc?}', LegalController::class)->name('public.legal.show');

Route::get('/id/card', AthleteIdCardController::class)
    ->middleware('throttle:60,1')
    ->name('public.athlete-id-card');

Route::middleware('throttle:20,1')->group(function () {
    Route::get('/join/{slug}', [JoinController::class, 'show'])->name('public.join.show');
    Route::post('/join/{slug}', [JoinController::class, 'store'])->name('public.join.store');
    Route::get('/rent/{slug}', [RentController::class, 'show'])->name('public.rent.show');
    Route::post('/rent/{slug}', [RentController::class, 'store'])->name('public.rent.store');
});

Route::post('/webhooks/viva', [PaymentWebhookController::class, 'viva'])->name('webhooks.viva');
Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'stripe'])->name('webhooks.stripe');
Route::post('/webhooks/eurobank', [PaymentWebhookController::class, 'eurobank'])->name('webhooks.eurobank');

require __DIR__.'/auth.php';
