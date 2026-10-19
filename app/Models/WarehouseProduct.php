<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class WarehouseProduct extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;


    protected $fillable = [
        'club_id',
        'name',
        'sku',
        'stock_qty',
        'low_stock_threshold',
        'unit_price',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'meta' => 'array',
        ];
    }
}
