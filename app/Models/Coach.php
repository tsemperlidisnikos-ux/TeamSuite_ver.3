<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClub;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coach extends Model
{
    use BelongsToClub, HasUuids, SoftDeletes;

    protected $fillable = [
        'club_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'sport',
        'hire_date',
        'is_active',
        'gga_code',
        'license_level',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    protected $appends = ['photo_url', 'first_aid'];

    public function getPhotoUrlAttribute(): ?string
    {
        $url = $this->meta['photo_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function getFirstAidAttribute(): ?string
    {
        $value = $this->meta['first_aid'] ?? $this->meta['first_aid_expires'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
