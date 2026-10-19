<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClub;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReceiptIssue extends Model
{
    use BelongsToClub, HasUuids;

    protected $fillable = [
        'club_id',
        'receipt_series_id',
        'athlete_transaction_id',
        'series',
        'number',
        'status',
        'issued_at',
        'cancelled_at',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
