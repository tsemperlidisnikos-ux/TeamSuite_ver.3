<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClub;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AthleteTransaction extends Model
{
    use BelongsToClub, HasUuids, SoftDeletes;

    protected $fillable = [
        'club_id',
        'athlete_id',
        'club_member_id',
        'type',
        'amount',
        'month',
        'year',
        'payment_method',
        'receipt_number',
        'receipt_series',
        'receipt_seq',
        'allocates_charge_id',
        'comments',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }
}
