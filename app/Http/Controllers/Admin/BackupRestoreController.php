<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\AcademyClass;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Attendance;
use App\Models\Club;
use App\Models\ClubBackup;
use App\Models\Coach;
use App\Models\Facility;
use App\Models\Sport;
use App\Models\SportMatch;
use App\Models\Training;
use App\Services\Backup\TeamSuiteBackupParser;
use App\Services\Backup\TeamSuiteBackupRestoreService;
use App\Services\Backup\TeamSuiteClubExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupRestoreController extends Controller
{
    use InteractsWithClubResource;

    public function index(Request $request): Response
    {
        $clubId = null;
        try {
            $clubId = $this->clubId($request);
        } catch (\Throwable) {
            // platform without club context
        }

        $verify = null;
        if ($request->session()->pull('backup_just_restored')) {
            $restoredClubId = $request->session()->pull('backup_restored_club_id') ?: $clubId;
            if ($restoredClubId) {
                $verify = $this->clubCounts($restoredClubId);
            }
        }

        return Inertia::render('Admin/Settings/Backup', [
            'lastDryRun' => $request->session()->get('backup_dry_run'),
            'verify' => $verify,
            'currentCounts' => $clubId ? $this->clubCounts($clubId) : null,
        ]);
    }

    /**
     * Full club JSON backup (legacy TeamSuite Club envelope — importable via restore).
     */
    public function download(Request $request, TeamSuiteClubExporter $exporter): StreamedResponse
    {
        $clubId = $this->clubId($request);
        $club = Club::query()->findOrFail($clubId);
        $payload = $exporter->export($club);
        $filename = 'teamsuite-'.$club->slug.'-'.now()->format('Y-m-d_H-i').'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function preview(Request $request, TeamSuiteBackupRestoreService $restore): RedirectResponse
    {
        $restore->prepareLongRunningRestore();

        $request->validate([
            'backup_file' => ['required', 'file', 'max:51200'],
        ]);

        $uploaded = $request->file('backup_file');
        $json = file_get_contents($uploaded->getRealPath());
        abort_unless(is_string($json) && $json !== '', 422, 'Άδειο αρχείο backup.');

        $result = $restore->dryRun($json, $request->user());

        // Keep large JSON on disk only — never in session (club backups can be 10–20MB+).
        $path = 'backup-pending/'.($request->user()?->id ?? 'anon').'-'.now()->timestamp.'.json';
        Storage::disk('local')->put($path, $json);

        $request->session()->forget('backup_pending_json');
        $request->session()->put('backup_pending_path', $path);
        $parsed = $result['parsed'] ?? [];
        $request->session()->flash('backup_dry_run', [
            'counts' => $result['counts'],
            'clubExists' => $result['clubExists'],
            'backupId' => $result['backup']->id,
            'sourceClubId' => $result['backup']->source_club_id,
            'scope' => $result['backup']->scope,
            'clubName' => $parsed['clubs'][0]['name'] ?? null,
            'fileBytes' => strlen($json),
        ]);

        return redirect()->route('admin.settings.backup.index');
    }

    public function restore(Request $request, TeamSuiteBackupRestoreService $restore): RedirectResponse
    {
        $restore->prepareLongRunningRestore();

        $data = $request->validate([
            'backup_id' => ['required', 'string'],
            'mode' => ['required', 'in:merge,replace'],
        ]);

        $path = $request->session()->get('backup_pending_path');
        abort_unless(
            is_string($path) && Storage::disk('local')->exists($path),
            422,
            'Λείπει το αρχείο προεπισκόπησης. Ανεβάστε ξανά το JSON.'
        );

        $json = Storage::disk('local')->get($path);
        abort_unless(is_string($json) && $json !== '', 422, 'Άδειο αρχείο προεπισκόπησης.');

        try {
            $parsed = app(TeamSuiteBackupParser::class)->parse($json);
            $backup = ClubBackup::query()->findOrFail($data['backup_id']);
            $counts = $restore->restoreClub($parsed, $data['mode'], $backup);
            $restoredClub = Club::query()->findOrFail($backup->fresh()->club_id);
            $restore->activateClubForUser($request->user(), $restoredClub);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.settings.backup.index')
                ->with('error', 'Η επαναφορά απέτυχε: '.$e->getMessage());
        }

        Storage::disk('local')->delete($path);
        $request->session()->forget(['backup_pending_json', 'backup_pending_path', 'backup_dry_run']);
        $request->session()->put('backup_just_restored', true);
        $request->session()->put('backup_restored_club_id', $restoredClub->id);

        $activeInDb = (int) ($counts['activeAthletes'] ?? 0);
        $activeInBackup = (int) ($counts['activeAthletesInBackup'] ?? 0);
        $skipped = (int) ($counts['studentsSkipped'] ?? 0);
        $success = 'Η επαναφορά ολοκληρώθηκε: '.$restoredClub->name
            .' · Ενεργοί αθλητές '.$activeInDb
            .($activeInBackup > 0 ? ' / '.$activeInBackup.' στο backup' : '');
        if ($skipped > 0) {
            $success .= ' · Παραλείφθηκαν '.$skipped.' εγγραφές αθλητών';
        }
        if ($activeInBackup > 0 && $activeInDb !== $activeInBackup) {
            $success .= ' · Προσοχή: διαφορά ενεργών με το backup';
        }

        return redirect()
            ->route('admin.settings.backup.index')
            ->with('success', $success)
            ->with('backup_dry_run', [
                'counts' => $counts,
                'clubExists' => true,
                'backupId' => $backup->id,
                'restored' => true,
                'mode' => $data['mode'],
                'clubName' => $restoredClub->name,
                'sourceClubId' => $restoredClub->id,
            ]);
    }

    /**
     * @return array<string, int>
     */
    private function clubCounts(string $clubId): array
    {
        return [
            'athletes' => Athlete::query()->withoutGlobalScopes()->where('club_id', $clubId)->count(),
            'classes' => AcademyClass::query()->withoutGlobalScopes()->where('club_id', $clubId)->count(),
            'coaches' => Coach::query()->withoutGlobalScopes()->where('club_id', $clubId)->count(),
            'transactions' => AthleteTransaction::query()->withoutGlobalScopes()->where('club_id', $clubId)->count(),
            'attendance' => Attendance::query()->withoutGlobalScopes()->where('club_id', $clubId)->count(),
            'trainings' => Training::query()->withoutGlobalScopes()->where('club_id', $clubId)->count(),
            'matches' => SportMatch::query()->withoutGlobalScopes()->where('club_id', $clubId)->count(),
            'announcements' => Announcement::query()->withoutGlobalScopes()->where('club_id', $clubId)->count(),
            'sports' => Sport::query()->withoutGlobalScopes()->where('club_id', $clubId)->count(),
            'facilities' => Facility::query()->withoutGlobalScopes()->where('club_id', $clubId)->count(),
        ];
    }
}
