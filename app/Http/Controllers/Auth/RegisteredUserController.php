<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PlatformWaitlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Academy waitlist registration (legacy /register).
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'sports' => [
                'Ποδόσφαιρο',
                'Μπάσκετ',
                'Βόλεϊ',
                'Χάντμπολ',
                'Κολύμβηση',
                'Τένις',
                'Άλλο',
            ],
            'levels' => [
                ['id' => 'academies', 'label' => 'Ακαδημίες'],
                ['id' => 'pre', 'label' => 'Προαγωνιστικό'],
                ['id' => 'comp', 'label' => 'Αγωνιστικό'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'club_name' => 'required|string|max:200',
            'contact_name' => 'required|string|max:200',
            'email' => 'required|email|max:190',
            'phone' => 'required|string|max:40',
            'sport' => 'required|string|max:80',
            'levels' => 'required|array|min:1',
            'levels.*' => 'string|max:40',
            'accept_terms' => 'accepted',
        ]);

        PlatformWaitlist::query()->create([
            'club_name' => $data['club_name'],
            'contact_name' => $data['contact_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => 'pending',
            'notes' => json_encode([
                'sport' => $data['sport'],
                'levels' => $data['levels'],
                'accept_terms' => true,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return redirect()
            ->route('register')
            ->with('success', 'Είσαι στη λίστα!');
    }
}
