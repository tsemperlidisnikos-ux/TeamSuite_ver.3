<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class DiscountReason extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;


    protected $fillable = [
        'club_id',
        'name',
        'sport',
        'default_amount',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
        ];
    }
}
