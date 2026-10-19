<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubBackup extends Model
{
    use HasUuids;

    protected $fillable = [
        'club_id',
        'scope',
        'source',
        'original_filename',
        'storage_path',
        'exported_at',
        'source_club_id',
        'dry_run_counts',
        'status',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'exported_at' => 'datetime',
            'dry_run_counts' => 'array',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
