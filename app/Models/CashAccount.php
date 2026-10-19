<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClub;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashAccount extends Model
{
    use BelongsToClub, HasUuids, SoftDeletes;

    protected $fillable = [
        'club_id',
        'name',
        'opening_balance',
        'is_active',
        'legacy_id',
        'meta',
    ];

    protected $appends = [
        'kind',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    protected function kind(): Attribute
    {
        return Attribute::get(fn () => (string) ($this->meta['kind'] ?? 'cash'));
    }
}
