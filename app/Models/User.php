<?php

namespace App\Models;

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

#[Fillable(['name', 'email', 'password', 'is_platform_admin', 'active_club_id'])]
#[Hidden(['password', 'remember_token'])]
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
}
