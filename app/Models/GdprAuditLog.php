<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Concerns\BelongsToClub;

class GdprAuditLog extends Model
{
    use HasUuids, BelongsToClub;


    protected $fillable = [
        'club_id',
        'user_id',
        'action',
        'meta',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
