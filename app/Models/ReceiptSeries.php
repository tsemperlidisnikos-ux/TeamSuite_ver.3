<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class ReceiptSeries extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;

    protected $table = 'receipt_series';

    protected $fillable = [
        'club_id',
        'series',
        'range_from',
        'range_to',
        'next_number',
        'is_active',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
