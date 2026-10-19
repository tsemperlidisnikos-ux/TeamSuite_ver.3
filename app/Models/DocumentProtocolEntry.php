<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class DocumentProtocolEntry extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;


    protected $fillable = [
        'club_id',
        'direction',
        'protocol_number',
        'subject',
        'document_date',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'meta' => 'array',
        ];
    }
}
