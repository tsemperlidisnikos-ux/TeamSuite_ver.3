<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'club_id',
        'smtp',
        'sms',
        'push',
        'viva',
        'stripe',
        'eurobank',
        'profile',
        'public_join_enabled',
        'public_rent_enabled',
        'public_registration',
        'terms_of_use_html',
        'dpa_html',
        'retention_policy_html',
        'data_retention_months',
        'dpa_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'smtp' => 'encrypted:array',
            'sms' => 'encrypted:array',
            'push' => 'encrypted:array',
            'viva' => 'encrypted:array',
            'stripe' => 'encrypted:array',
            'eurobank' => 'encrypted:array',
            'profile' => 'array',
            'public_registration' => 'array',
            'public_join_enabled' => 'boolean',
            'public_rent_enabled' => 'boolean',
            'data_retention_months' => 'integer',
            'dpa_accepted_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
