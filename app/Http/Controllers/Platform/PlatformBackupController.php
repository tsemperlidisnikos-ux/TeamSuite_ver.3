<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Backup\TeamSuitePlatformExporter;
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
}
