<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToClub;

class PartnerOffer extends Model
{
    use HasUuids, SoftDeletes, BelongsToClub;


    protected $fillable = [
        'club_id',
        'partner_business_id',
        'title',
        'description',
        'legacy_id',
    ];
}
