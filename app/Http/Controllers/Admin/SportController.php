<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SportController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'sports';
    }

    public function store(Request $request): RedirectResponse
    {
        parent::store($request);

        return redirect('/admin/settings?tab=sports')
            ->with('success', 'Η εγγραφή δημιουργήθηκε.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        parent::update($request, $id);

        return redirect('/admin/settings?tab=sports')
            ->with('success', 'Η εγγραφή ενημερώθηκε.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        parent::destroy($request, $id);

        return redirect('/admin/settings?tab=sports')
            ->with('success', 'Η εγγραφή διαγράφηκε.');
    }
}
