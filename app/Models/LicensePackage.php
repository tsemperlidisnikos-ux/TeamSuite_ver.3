<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicensePackage extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'athlete_cap',
        'modules',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'is_active' => 'boolean',
            'athlete_cap' => 'integer',
        ];
    }

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }
}
