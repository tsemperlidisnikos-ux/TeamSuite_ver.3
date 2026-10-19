<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Concerns\BelongsToClub;

class PaymentSettlement extends Model
{
    use HasUuids, BelongsToClub;


    protected $fillable = [
        'club_id',
        'online_checkout_id',
        'provider',
        'external_id',
        'idempotency_key',
        'amount',
        'status',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload' => 'array',
        ];
    }
}
