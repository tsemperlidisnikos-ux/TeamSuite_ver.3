<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClub;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClubMember extends Model
{
    use BelongsToClub, HasUuids, SoftDeletes;

    protected $table = 'club_members';

    /** @var list<string> */
    protected static array $metaKeys = ['address', 'postal_code', 'city', 'prefecture'];

    protected $fillable = [
        'club_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'member_number',
        'status',
        'legacy_id',
        'meta',
        'address',
        'postal_code',
        'city',
        'prefecture',
    ];

    protected $appends = ['address', 'postal_code', 'city', 'prefecture'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ClubMember $member): void {
            $meta = is_array($member->meta) ? $member->meta : [];
            foreach (static::$metaKeys as $key) {
                if (array_key_exists($key, $member->getAttributes()) || $member->isDirty($key)) {
                    $meta[$key] = $member->getAttribute($key);
                    unset($member->attributes[$key]);
                }
            }
            $member->meta = $meta;
        });
    }

    public function getAddressAttribute(): ?string
    {
        return is_array($this->meta) ? ($this->meta['address'] ?? null) : null;
    }

    public function getPostalCodeAttribute(): ?string
    {
        return is_array($this->meta) ? ($this->meta['postal_code'] ?? null) : null;
    }

    public function getCityAttribute(): ?string
    {
        return is_array($this->meta) ? ($this->meta['city'] ?? null) : null;
    }

    public function getPrefectureAttribute(): ?string
    {
        return is_array($this->meta) ? ($this->meta['prefecture'] ?? null) : null;
    }
}
