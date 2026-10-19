<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClub;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Association extends Model
{
    use BelongsToClub, HasUuids, SoftDeletes;

    protected $fillable = [
        'club_id',
        'name',
        'legacy_id',
        'meta',
    ];

    protected $appends = [
        'city',
        'phone',
        'email',
        'address',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    protected function city(): Attribute
    {
        return Attribute::get(fn () => (string) ($this->meta['city'] ?? ''));
    }

    protected function phone(): Attribute
    {
        return Attribute::get(fn () => (string) ($this->meta['phone'] ?? ''));
    }

    protected function email(): Attribute
    {
        return Attribute::get(fn () => (string) ($this->meta['email'] ?? ''));
    }

    protected function address(): Attribute
    {
        return Attribute::get(fn () => (string) ($this->meta['address'] ?? ''));
    }

    protected function active(): Attribute
    {
        return Attribute::get(fn () => (bool) ($this->meta['active'] ?? true));
    }
}
