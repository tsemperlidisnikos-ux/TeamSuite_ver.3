<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\InteractsWithClubResource;
use App\Http\Controllers\Controller;
use App\Models\AcademyClass;
use App\Models\Athlete;
use App\Models\ParentAthleteLink;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ParentLinkController extends Controller
{
    use InteractsWithClubResource;

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $push = (string) $request->query('push', '');
        $classId = (string) $request->query('class_id', '');

        $links = $this->clubQuery(ParentAthleteLink::class, $request)
            ->with([
                'athlete:id,first_name,last_name,status',
                'athlete.classes:id,name,age_group',
                'parentUser:id,name,email,email_verified_at',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                $query->where(function ($w) use ($like, $op) {
                    $w->where('relation', $op, $like)
                        ->orWhereHas('athlete', function ($a) use ($like, $op) {
                            $a->where('first_name', $op, $like)
                                ->orWhere('last_name', $op, $like);
                        })
                        ->orWhereHas('parentUser', function ($u) use ($like, $op) {
                            $u->where('name', $op, $like)
                                ->orWhere('email', $op, $like);
                        });
                });
            })
            ->when($classId !== '', function ($query) use ($classId) {
                $query->whereHas('athlete.classes', fn ($c) => $c->where('academy_classes.id', $classId));
            })
            ->when($push === 'on', fn ($query) => $query->where('receives_push', true))
            ->when($push === 'off', fn ($query) => $query->where(fn ($w) => $w->where('receives_push', false)->orWhereNull('receives_push')))
            ->latest()
            ->get();

        // Directory: one row per parent (legacy ParentsPage shape)
        $directory = $links
            ->groupBy(fn (ParentAthleteLink $link) => $link->parent_user_id ?: $link->id)
            ->map(function ($group) {
                /** @var ParentAthleteLink $first */
                $first = $group->first();
                $parent = $first->parentUser;
                $hasPush = $group->contains(fn (ParentAthleteLink $l) => (bool) $l->receives_push);
                $inviteStatus = $parent?->email_verified_at ? 'active' : 'pending';
                if (! $parent) {
                    $inviteStatus = 'not_invited';
                }

                return [
                    'id' => $first->id,
                    'parent_user_id' => $first->parent_user_id,
                    'has_account' => (bool) $first->parent_user_id,
                    'full_name' => $parent?->name ?: '—',
                    'email' => $parent?->email ?: '',
                    'status' => $inviteStatus,
                    'receives_push' => $hasPush,
                    'athletes' => $group->map(function (ParentAthleteLink $l) {
                        $athlete = $l->athlete;
                        $name = $athlete
                            ? trim($athlete->first_name.' '.$athlete->last_name)
                            : (string) $l->athlete_id;
                        $category = $athlete?->classes
                            ? $athlete->classes
                                ->map(fn ($c) => $c->age_group ?: $c->name)
                                ->filter()
                                ->unique()
                                ->implode(', ')
                            : '';
                        $label = $category !== '' ? "{$name} ({$category})" : $name;

                        return [
                            'id' => $l->athlete_id,
                            'label' => $label,
                            'link_id' => $l->id,
                            'relation' => $l->relation,
                        ];
                    })->values()->all(),
                ];
            })
            ->values();

        if (in_array($status, ['active', 'pending', 'not_invited'], true)) {
            $directory = $directory->where('status', $status)->values();
        }

        $athletes = $this->clubQuery(Athlete::class, $request)
            ->where('status', '!=', 'inactive')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(500)
            ->get(['id', 'first_name', 'last_name']);

        $classes = $this->clubQuery(AcademyClass::class, $request)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Parents/Index', [
            'directory' => $directory,
            'athletes' => $athletes,
            'classes' => $classes,
            'parentAppUrl' => url('/login?app=parent'),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'push' => $push,
                'class_id' => $classId,
            ],
            'routes' => [
                'index' => 'admin.parents.index',
                'store' => 'admin.parents.store',
                'update' => 'admin.parents.update',
                'destroy' => 'admin.parents.destroy',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'parent_email' => 'required|email',
            'parent_name' => 'nullable|string|max:160',
            'parent_password' => 'nullable|string|min:6|max:120',
            'athlete_id' => 'required|uuid',
            'relation' => 'nullable|string|max:64',
        ]);

        $clubId = $this->clubId($request);

        $parent = User::query()->firstOrCreate(
            ['email' => $data['parent_email']],
            [
                'name' => $data['parent_name'] ?: 'Γονέας',
                'password' => Hash::make($data['parent_password'] ?? str()->random(10)),
                'email_verified_at' => now(),
            ]
        );

        if (! empty($data['parent_name']) && $parent->name !== $data['parent_name']) {
            $parent->update(['name' => $data['parent_name']]);
        }

        if (! empty($data['parent_password'])) {
            $parent->update(['password' => Hash::make($data['parent_password'])]);
        }

        $parent->clubs()->syncWithoutDetaching([
            $clubId => [
                'role' => Roles::PARENT,
                'is_active' => true,
            ],
        ]);

        ParentAthleteLink::query()->withoutGlobalScopes()->firstOrCreate(
            [
                'parent_user_id' => $parent->id,
                'athlete_id' => $data['athlete_id'],
            ],
            [
                'club_id' => $clubId,
                'relation' => $data['relation'] ?? 'γονέας',
                'receives_push' => true,
            ]
        );

        return back()->with('success', 'Η πρόσκληση γονέα αποθηκεύτηκε.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'relation' => 'nullable|string|max:64',
            'receives_push' => 'boolean',
            'receives_sms' => 'boolean',
        ]);

        $row = $this->clubQuery(ParentAthleteLink::class, $request)->whereKey($id)->firstOrFail();
        $row->fill([
            'relation' => $data['relation'] ?? $row->relation,
            'receives_push' => filter_var($data['receives_push'] ?? $row->receives_push, FILTER_VALIDATE_BOOLEAN),
            'receives_sms' => filter_var($data['receives_sms'] ?? $row->receives_sms, FILTER_VALIDATE_BOOLEAN),
        ])->save();

        return back()->with('success', 'Η σύνδεση ενημερώθηκε.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $row = $this->clubQuery(ParentAthleteLink::class, $request)->whereKey($id)->firstOrFail();
        $row->delete();

        return back()->with('success', 'Αποσύνδεση ολοκληρώθηκε.');
    }
}
