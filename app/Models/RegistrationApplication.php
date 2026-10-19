<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class RegistrationApplication extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;


    protected $fillable = [
        'club_id',
        'type',
        'status',
        'first_name',
        'last_name',
        'email',
        'phone',
        'form_snapshot',
        'gdpr_accepted',
        'signature_path',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'form_snapshot' => 'array',
            'gdpr_accepted' => 'boolean',
        ];
    }
}
