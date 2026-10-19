<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ModulePlaceholderController extends Controller
{
    /** @var array<string, array{title: string, phase: string}> */
    private const MODULES = [
        'athletes' => ['title' => 'Αθλητές', 'phase' => 'Φάση 2'],
        'parents' => ['title' => 'Γονείς', 'phase' => 'Φάση 2'],
        'coaches' => ['title' => 'Προπονητές', 'phase' => 'Φάση 2'],
        'staff' => ['title' => 'Προσωπικό', 'phase' => 'Φάση 2'],
        'members' => ['title' => 'Μέλη', 'phase' => 'Φάση 2'],
        'classes' => ['title' => 'Τμήματα', 'phase' => 'Φάση 2'],
        'calendar' => ['title' => 'Ημερολόγιο', 'phase' => 'Φάση 3'],
        'trainings' => ['title' => 'Προπονήσεις', 'phase' => 'Φάση 3'],
        'matches' => ['title' => 'Αγώνες', 'phase' => 'Φάση 3'],
        'schedule' => ['title' => 'Πρόγραμμα', 'phase' => 'Φάση 3'],
        'attendance' => ['title' => 'Παρουσίες', 'phase' => 'Φάση 3'],
        'fees' => ['title' => 'Συνδρομές / Πληρωμές', 'phase' => 'Φάση 4'],
        'transactions' => ['title' => 'Συναλλαγές', 'phase' => 'Φάση 4'],
        'finance' => ['title' => 'Οικονομικά', 'phase' => 'Φάση 4'],
        'warehouse' => ['title' => 'Αποθήκη', 'phase' => 'Φάση 7'],
        'rentals' => ['title' => 'Ενοικίαση', 'phase' => 'Φάση 6'],
        'announcements' => ['title' => 'Ανακοινώσεις', 'phase' => 'Φάση 5'],
        'photos' => ['title' => 'Φωτογραφίες', 'phase' => 'Φάση 7'],
        'prints' => ['title' => 'Εκτυπώσεις', 'phase' => 'Φάση 7'],
        'partners' => ['title' => 'Συμβεβλημένες επιχειρήσεις', 'phase' => 'Φάση 7'],
        'protocol' => ['title' => 'Πρωτόκολλο', 'phase' => 'Φάση 7'],
        'settings' => ['title' => 'Ρυθμίσεις', 'phase' => 'Φάση 1 / 9'],
    ];

    public function __invoke(\Illuminate\Http\Request $request, ?string $module = null): Response
    {
        $module ??= $request->route('module')
            ?? $request->route()?->defaults['module']
            ?? 'unknown';

        $meta = self::MODULES[$module] ?? ['title' => $module, 'phase' => 'επόμενες φάσεις'];

        return Inertia::render('Admin/ModulePlaceholder', [
            'title' => $meta['title'],
            'module' => $module,
            'phaseHint' => $meta['phase'],
        ]);
    }
}
