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

        $raw = $membership->pivot->permissions ?? [];
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        if (is_array($raw) && $raw !== []) {
            return array_values(array_unique(array_filter($raw, 'is_string')));
        }

        $role = (string) ($membership->pivot->role ?? '');
        $defaults = app(PlatformConfigService::class)->get()['clubRolePermissions'][$role] ?? [];
        if (! is_array($defaults)) {
            $defaults = [];
        }

        return array_values(array_unique(array_filter($defaults, 'is_string')));
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

        $allowed = array_values(array_intersect($clubModules, $this->permissionsInClub($club)));

        if ($allowed !== [] && ! in_array('dashboard', $allowed, true)) {
            array_unshift($allowed, 'dashboard');
        }

        return $allowed;
    }
}
