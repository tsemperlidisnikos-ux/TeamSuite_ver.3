<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToClub;

class SuppressedFeeChargeKey extends Model
{
    use BelongsToClub;


    protected $fillable = [
        'club_id',
        'suppress_key',
    ];
}
