<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClub;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Athlete extends Model
{
    use BelongsToClub, HasUuids, SoftDeletes;

    /** Keys stored inside meta JSON (legacy TeamSuite profile fields). */
    public const META_KEYS = [
        'adt', 'father_first_name', 'mother_first_name', 'father_email', 'mother_email',
        'preferred_parent_email', 'mother_phone', 'address', 'postal_code', 'city', 'county',
        'registration_number', 'club_name', 'sports', 'coach_names', 'jersey_number',
        'position', 'athletic_level', 'athletic_start_date', 'uniform_received', 'uniform_size',
        'clothing_package_ids', 'blood_type', 'allergies', 'chronic_conditions', 'medication',
        'doctor_name', 'doctor_phone', 'emergency_name', 'emergency_phone', 'emergency_relation',
        'registration_expires', 'auto_renewal', 'consent_expires', 'comments', 'photo_url',
        'gdpr_items', 'amka_consent_at', 'registration_charge', 'monthly_charge', 'custom_charge',
        'season_ticket', 'subscription_discount', 'discount_amount', 'discount_reason',
        'discount_reason_ids', 'place_of_birth', 'nationality', 'communication_language',
        'primary_class_id', 'class_ids',
    ];

    protected $fillable = [
        'club_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'birth_date',
        'status',
        'monthly_fee',
        'enrolled_at',
        'amka_encrypted',
        'amka_fingerprint',
        'gender',
        'guardian_name',
        'guardian_phone',
        'sport',
        'health_card',
        'health_card_expires',
        'gdpr_consent',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'enrolled_at' => 'date',
            'monthly_fee' => 'decimal:2',
            'health_card' => 'boolean',
            'health_card_expires' => 'date',
            'amka_encrypted' => 'encrypted',
            'meta' => 'array',
        ];
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(AcademyClass::class, 'academy_class_athlete')
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AthleteTransaction::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class);
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(AthleteChangeLog::class);
    }

    public function parentLinks(): HasMany
    {
        return $this->hasMany(ParentAthleteLink::class);
    }

    public function metaGet(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta ?? [], $key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function mergeMeta(array $values): void
    {
        $this->meta = array_merge($this->meta ?? [], $values);
    }

    /**
     * Full profile payload for Inertia (optionally reveal AMKA).
     *
     * @return array<string, mixed>
     */
    public function toProfileArray(bool $revealAmka = false): array
    {
        $meta = $this->meta ?? [];
        $base = [
            'id' => $this->id,
            'legacy_id' => $this->legacy_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birth_date' => optional($this->birth_date)->format('Y-m-d'),
            'status' => $this->status,
            'monthly_fee' => $this->monthly_fee,
            'enrolled_at' => optional($this->enrolled_at)->format('Y-m-d'),
            'gender' => $this->gender,
            'guardian_name' => $this->guardian_name,
            'guardian_phone' => $this->guardian_phone,
            'sport' => $this->sport,
            'health_card' => (bool) $this->health_card,
            'health_card_expires' => optional($this->health_card_expires)->format('Y-m-d'),
            'gdpr_consent' => $this->gdpr_consent,
            'amka' => $revealAmka ? ($this->amka_encrypted ?: '') : ($this->amka_encrypted ? '********' : ''),
            'amka_set' => filled($this->amka_encrypted),
            'class_ids' => $this->classes->pluck('id')->values()->all(),
            'classes' => $this->classes->map(fn (AcademyClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'is_primary' => (bool) $c->pivot->is_primary,
            ])->values()->all(),
        ];

        foreach (self::META_KEYS as $key) {
            if (! array_key_exists($key, $base)) {
                $base[$key] = $meta[$key] ?? $meta[Str::camel($key)] ?? null;
            }
        }

        return $base;
    }
}
