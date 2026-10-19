<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Concerns\BelongsToClub;

class SizeChart extends Model
{
    use HasUuids, BelongsToClub;


    protected $fillable = [
        'club_id',
        'chart',
    ];

    protected function casts(): array
    {
        return [
            'chart' => 'array',
        ];
    }
}
