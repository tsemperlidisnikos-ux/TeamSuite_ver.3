<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\AthleteTransaction;
use App\Models\Attendance;
use App\Models\Scopes\ClubScope;
use App\Models\Training;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AthletePortalController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $clubId = $user->active_club_id;

        $athlete = null;
        if ($clubId) {
            $athlete = Athlete::query()->withoutGlobalScopes()
                ->where('club_id', $clubId)
                ->where(function ($q) use ($user) {
                    $q->where('email', $user->email);
                    if ($user->id) {
                        $q->orWhere('meta->user_id', $user->id);
                    }
                })
                ->first();
        }

        $balance = 0.0;
        $trainings = collect();
        $attendanceStats = ['present' => 0, 'absent' => 0, 'rate' => 0];

        if ($athlete) {
            $balance = (float) AthleteTransaction::query()->withoutGlobalScopes()
                ->where('athlete_id', $athlete->id)
                ->get(['type', 'amount'])
                ->reduce(function (float $sum, AthleteTransaction $row) {
                    return $sum + ($row->type === 'charge' ? (float) $row->amount : -(float) $row->amount);
                }, 0.0);

            $trainings = Training::query()->withoutGlobalScopes()
                ->where('club_id', $athlete->club_id)
                ->where('starts_at', '>=', now()->subDay())
                ->orderBy('starts_at')
                ->limit(10)
                ->get(['id', 'title', 'starts_at']);

            $records = Attendance::query()->withoutGlobalScopes()
                ->where('athlete_id', $athlete->id)
                ->limit(200)
                ->get(['status']);
            $present = $records->where('status', 'present')->count();
            $absent = $records->where('status', 'absent')->count();
            $total = $present + $absent;
            $attendanceStats = [
                'present' => $present,
                'absent' => $absent,
                'rate' => $total > 0 ? (int) round(($present / $total) * 100) : 0,
            ];
        }

        $announcements = $clubId
            ? Announcement::query()
                ->withoutGlobalScope(ClubScope::class)
                ->where('club_id', $clubId)
                ->latest('published_at')
                ->limit(20)
                ->get(['id', 'title', 'body', 'published_at', 'meta'])
                ->map(fn (Announcement $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'published_at' => optional($a->published_at)?->toIso8601String(),
                    'image_url' => $a->imageUrl(),
                ])
                ->values()
            : collect();

        return Inertia::render('Portals/AthleteHome', [
            'athlete' => $athlete ? [
                'id' => $athlete->id,
                'first_name' => $athlete->first_name,
                'last_name' => $athlete->last_name,
                'health_card' => (bool) $athlete->health_card,
                'health_card_expires' => optional($athlete->health_card_expires)->format('Y-m-d'),
                'class_name' => null,
            ] : null,
            'balance' => round($balance, 2),
            'trainings' => $trainings,
            'announcements' => $announcements,
            'attendanceStats' => $attendanceStats,
        ]);
    }
}
