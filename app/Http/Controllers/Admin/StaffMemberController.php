<?php

namespace App\Http\Controllers\Admin;

use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffMemberController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'staff';
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'uuid',
            'is_active' => 'required|in:0,1,true,false',
        ]);

        $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        $members = $this->clubQuery(StaffMember::class, $request)
            ->whereIn('id', $data['ids'])
            ->get();

        foreach ($members as $member) {
            $member->update(['is_active' => $isActive]);
        }

        return back()->with(
            'success',
            'Μαζική αλλαγή: ενημερώθηκαν '.$members->count().' μέλη προσωπικού.'
        );
    }
}
