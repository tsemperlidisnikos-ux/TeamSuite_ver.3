<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PlatformWaitlist extends Model
{
    use HasUuids;

    protected $table = 'platform_waitlist';

    protected $fillable = [
        'club_name',
        'contact_name',
        'email',
        'phone',
        'status',
        'notes',
    ];
}
