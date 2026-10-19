<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class GalleryPhoto extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;


    protected $fillable = [
        'club_id',
        'title',
        'path',
        'gdpr_cleared',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'gdpr_cleared' => 'boolean',
            'meta' => 'array',
        ];
    }
}
