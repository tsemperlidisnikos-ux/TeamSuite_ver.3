<?php

namespace App\Models;

use App\Services\Platform\PlatformConfigService;
use App\Support\Navigation;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'password_reveal', 'is_platform_admin', 'active_club_id'])]
#[Hidden(['password', 'password_reveal', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class)
            ->withPivot(['role', 'permissions', 'finance_own_entries_only', 'is_active'])
            ->withTimestamps();
    }

    public function activeClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'active_club_id');
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
    }

    public function roleInClub(?Club $club = null): ?string
    {
        $club ??= $this->activeClub;

        if (! $club) {
            return $this->is_platform_admin ? 'platform_admin' : null;
        }

        $membership = $this->clubs()->where('clubs.id', $club->id)->first();

        return $membership?->pivot?->role;
    }

    /**
     * Module ids granted to this user in the club (pivot permissions, else role defaults).
     *
     * @return list<string>
     */
    public function permissionsInClub(?Club $club = null): array
    {
        if ($this->is_platform_admin) {
            return array_column(Navigation::academyModules(), 'id');
        }

        $club ??= $this->activeClub;
        if (! $club) {
            return [];
        }

        $membership = $this->clubs()->where('clubs.id', $club->id)->first();
        if (! $membership) {
            return [];
        }

        $role = (string) ($membership->pivot->role ?? '');

        $raw = $membership->pivot->permissions ?? [];
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        if (is_array($raw) && $raw !== []) {
            $permissions = array_values(array_unique(array_filter($raw, 'is_string')));

            return $this->applyParentModulePolicy($role, $permissions);
        }

        $defaults = app(PlatformConfigService::class)->get()['clubRolePermissions'][$role] ?? [];
        if (! is_array($defaults)) {
            $defaults = [];
        }

        $permissions = array_values(array_unique(array_filter($defaults, 'is_string')));

        return $this->applyParentModulePolicy($role, $permissions);
    }

    /**
     * Modules visible in the club sidebar for this user.
     *
     * @param  list<string>  $clubModules
     * @return list<string>
     */
    public function allowedModulesInClub(array $clubModules, ?Club $club = null): array
    {
        if ($this->is_platform_admin) {
            return array_values($clubModules);
        }

        return array_values(array_unique(array_intersect($clubModules, $this->permissionsInClub($club))));
    }

    public function seesOnlyOwnFinanceEntries(?Club $club = null): bool
    {
        if ($this->is_platform_admin) {
            return false;
        }

        $club ??= $this->activeClub;
        if (! $club) {
            return false;
        }

        $role = $this->roleInClub($club);
        if (in_array($role, ['admin', 'doctor'], true)) {
            return false;
        }

        $membership = $this->clubs()->where('clubs.id', $club->id)->first();

        return (bool) ($membership?->pivot?->finance_own_entries_only);
    }

    /**
     * Parents may view announcements (send is blocked in controllers/UI).
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function applyParentModulePolicy(string $role, array $permissions): array
    {
        if ($role !== 'parent') {
            return $permissions;
        }

        if (! in_array('announcements', $permissions, true)) {
            $permissions[] = 'announcements';
        }

        return array_values(array_unique($permissions));
    }
}
