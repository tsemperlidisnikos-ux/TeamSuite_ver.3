<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClub;
use Illuminate\Database\Eloquent\Model;

class ClosedFinanceMonth extends Model
{
    use BelongsToClub;

    protected $fillable = [
        'club_id',
        'year_month',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }
}
