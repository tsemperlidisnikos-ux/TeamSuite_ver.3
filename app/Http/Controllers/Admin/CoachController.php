<?php

namespace App\Http\Controllers\Admin;

use App\Models\Coach;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CoachController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'coaches';
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'uuid',
            'is_active' => 'required|in:0,1,true,false',
        ]);

        $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        $coaches = $this->clubQuery(Coach::class, $request)
            ->whereIn('id', $data['ids'])
            ->get();

        foreach ($coaches as $coach) {
            $coach->update(['is_active' => $isActive]);
        }

        return back()->with(
            'success',
            'Μαζική αλλαγή: ενημερώθηκαν '.$coaches->count().' προπονητές.'
        );
    }
}
