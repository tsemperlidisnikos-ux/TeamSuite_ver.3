<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformWaitlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WaitlistController extends Controller
{
    public function index(): Response
    {
        $rows = PlatformWaitlist::query()
            ->latest()
            ->paginate(50)
            ->through(fn (PlatformWaitlist $row) => [
                'id' => $row->id,
                'created_at' => optional($row->created_at)?->toIso8601String(),
                'club_name' => $row->club_name,
                'contact_name' => $row->contact_name,
                'email' => $row->email,
                'phone' => $row->phone,
                'status' => $row->status,
                'notes' => $row->notes,
            ]);

        return Inertia::render('Platform/Waitlist', [
            'rows' => $rows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'club_name' => 'nullable|string|max:200',
            'contact_name' => 'nullable|string|max:200',
            'phone' => 'nullable|string|max:40',
            'notes' => 'nullable|string',
        ]);

        PlatformWaitlist::query()->create([
            'id' => (string) Str::uuid(),
            ...$data,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Καταχωρήθηκε στη λίστα αναμονής.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $row = PlatformWaitlist::query()->findOrFail($id);
        $row->update(['status' => $data['status']]);

        return back()->with('success', 'Η κατάσταση ενημερώθηκε.');
    }

    public function destroy(string $id): RedirectResponse
    {
        PlatformWaitlist::query()->findOrFail($id)->delete();

        return back()->with('success', 'Η αίτηση διαγράφηκε.');
    }
}
