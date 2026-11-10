<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Jobs\ClubBackupJob;
use App\Models\Athlete;
use App\Models\Club;
use App\Models\ClubBackup;
use App\Models\User;
use App\Services\Backup\TeamSuitePlatformExporter;
use App\Services\Platform\PlatformConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformBackupController extends Controller
{
    /**
     * Full platform JSON backup (all clubs + platformConfig). Platform admins only.
     */
    public function download(Request $request, TeamSuitePlatformExporter $exporter): StreamedResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $exporter->prepareLongRunningExport();
        $payload = $exporter->export();
        $filename = 'teamsuite-platform-'.now()->format('Y-m-d_H-i').'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function updateSchedule(Request $request, PlatformConfigService $config): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $data = $request->validate([
            'backupSchedules' => ['required', 'array'],
            'backupSchedules.fullApp' => ['sometimes', 'array'],
            'backupSchedules.perClub' => ['sometimes', 'array'],
            'backupSchedules.clubIds' => ['sometimes', 'array'],
            'backupSchedules.clubIds.*' => ['string'],
        ]);

        $config->update(['backupSchedules' => $data['backupSchedules']]);

        return back()->with('success', 'Το πρόγραμμα backup αποθηκεύτηκε.');
    }

    public function updateGoogleDrive(Request $request, PlatformConfigService $config): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'connected' => ['sometimes', 'boolean'],
            'rootFolderId' => ['sometimes', 'nullable', 'string', 'max:255'],
            'excludeClubIds' => ['sometimes', 'array'],
            'excludeClubIds.*' => ['string'],
        ]);

        $config->update(['googleDriveBackup' => $data]);

        return back()->with('success', 'Οι ρυθμίσεις Google Drive αποθηκεύτηκαν.');
    }

    public function runScheduled(Request $request, PlatformConfigService $config): RedirectResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        ClubBackupJob::dispatch();

        $schedules = $config->get()['backupSchedules'] ?? $config->defaultBackupSchedules();
        $now = now()->toIso8601String();
        foreach (['fullApp', 'perClub'] as $key) {
            if (isset($schedules[$key]) && is_array($schedules[$key])) {
                $schedules[$key]['lastRunAt'] = $now;
            }
        }
        $config->update(['backupSchedules' => $schedules]);

        return back()->with('success', 'Η εργασία backup συλλόγων τέθηκε στην ουρά.');
    }

    public function diagnostics(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        return response()->json([
            'ok' => true,
            'ranAt' => now()->toIso8601String(),
            'counts' => $this->diagnosticCounts(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function diagnosticCounts(): array
    {
        return [
            'clubs' => Club::query()->count(),
            'activeClubs' => Club::query()->where('is_active', true)->count(),
            'users' => User::query()->count(),
            'platformAdmins' => User::query()->where('is_platform_admin', true)->count(),
            'athletes' => Athlete::query()->withoutGlobalScopes()->count(),
            'backups' => ClubBackup::query()->count(),
            'exportedBackups' => ClubBackup::query()->where('status', 'exported')->count(),
        ];
    }

    public function googleDriveStatus(Request $request, PlatformConfigService $config): JsonResponse
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $drive = $config->get()['googleDriveBackup'] ?? $config->defaultGoogleDriveBackup();
        $oauthConfigured = filled(config('services.google_drive.client_id'))
            && filled(config('services.google_drive.client_secret'));

        return response()->json([
            ...$drive,
            'oauthConfigured' => $oauthConfigured,
            'redirectUri' => url('/platform/backup/google-drive/callback'),
        ]);
    }
}
