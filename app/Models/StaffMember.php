<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class StaffMember extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;

    protected $table = 'staff_members';

    protected $fillable = [
        'club_id',
        'user_id',
        'first_name',
        'last_name',
        'role_title',
        'email',
        'phone',
        'is_active',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }
}
