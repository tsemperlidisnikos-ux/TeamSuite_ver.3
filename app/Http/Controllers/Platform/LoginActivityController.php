<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginActivityController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        $rows = LoginActivity::query()
            ->with('user:id,name,email')
            ->latest('logged_in_at')
            ->limit(200)
            ->get()
            ->map(fn (LoginActivity $row) => [
                'id' => $row->id,
                'user' => $row->user?->name,
                'email' => $row->user?->email,
                'club_id' => $row->club_id,
                'ip_address' => $row->ip_address,
                'logged_in_at' => optional($row->logged_in_at)?->toIso8601String(),
            ]);

        return Inertia::render('Platform/LoginHistory', [
            'rows' => $rows,
        ]);
    }
}
