<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Club extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'license_package_id',
        'enabled_modules',
        'logo_path',
        'primary_color',
        'accent_color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'enabled_modules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function licensePackage(): BelongsTo
    {
        return $this->belongsTo(LicensePackage::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(ClubSetting::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'permissions', 'finance_own_entries_only', 'is_active'])
            ->withTimestamps();
    }

    public function backups(): HasMany
    {
        return $this->hasMany(ClubBackup::class);
    }

    public function athletes(): HasMany
    {
        return $this->hasMany(Athlete::class);
    }
}
