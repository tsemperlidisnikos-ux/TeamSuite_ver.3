<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Concerns\BelongsToClub;

class RentalSetting extends Model
{
    use HasUuids, BelongsToClub;


    protected $fillable = [
        'club_id',
        'public_enabled',
        'rules',
    ];

    protected function casts(): array
    {
        return [
            'public_enabled' => 'boolean',
            'rules' => 'array',
        ];
    }
}
