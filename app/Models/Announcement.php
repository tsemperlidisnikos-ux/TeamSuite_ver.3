<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClub;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Announcement extends Model
{
    use BelongsToClub, HasUuids, SoftDeletes;

    protected $fillable = [
        'club_id',
        'created_by',
        'title',
        'body',
        'audience',
        'published_at',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'published_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function displayBody(): string
    {
        $body = trim((string) $this->body);
        if ($body !== '') {
            return $body;
        }

        return trim((string) ($this->meta['message'] ?? ''));
    }

    /**
     * @return array{
     *     roles: list<string>,
     *     class_ids: list<string>,
     *     sport: string,
     *     parent_ids: list<string>,
     *     coach_ids: list<string>,
     *     athlete_ids: list<string>,
     *     staff_ids: list<string>
     * }
     */
    public function displayAudience(): array
    {
        $audience = is_array($this->audience) ? $this->audience : [];
        $roles = array_values(array_map('strval', $audience['roles'] ?? []));
        $classIds = array_values(array_map('strval', $audience['class_ids'] ?? []));
        $parentIds = array_values(array_map('strval', $audience['parent_ids'] ?? []));
        $coachIds = array_values(array_map('strval', $audience['coach_ids'] ?? []));
        $athleteIds = array_values(array_map('strval', $audience['athlete_ids'] ?? []));
        $staffIds = array_values(array_map('strval', $audience['staff_ids'] ?? []));
        $sport = trim((string) ($audience['sport'] ?? ''));

        $meta = is_array($this->meta) ? $this->meta : [];

        if ($roles === [] && ! empty($meta['audienceRoles']) && is_array($meta['audienceRoles'])) {
            $roleMap = [
                'parents' => 'parent',
                'parent' => 'parent',
                'athletes' => 'athlete',
                'athlete' => 'athlete',
                'coaches' => 'coach',
                'coach' => 'coach',
                'staff' => 'staff',
            ];
            foreach ($meta['audienceRoles'] as $role) {
                $key = strtolower(trim((string) $role));
                if (isset($roleMap[$key])) {
                    $roles[] = $roleMap[$key];
                }
            }
            $roles = array_values(array_unique($roles));
        }

        if ($classIds === [] && ! empty($meta['classIds']) && is_array($meta['classIds'])) {
            $classIds = array_values(array_map('strval', $meta['classIds']));
        }

        if ($sport === '' && ! empty($meta['sportCategories'])) {
            $sport = trim((string) $meta['sportCategories']);
        }

        if (
            $parentIds === [] && $coachIds === [] && $athleteIds === [] && $staffIds === []
            && ! empty($meta['recipientIds']) && is_array($meta['recipientIds'])
        ) {
            foreach ($meta['recipientIds'] as $recipient) {
                if (! is_array($recipient)) {
                    continue;
                }
                $kind = strtolower(trim((string) ($recipient['kind'] ?? '')));
                $id = trim((string) ($recipient['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                match ($kind) {
                    'parent' => $parentIds[] = $id,
                    'coach' => $coachIds[] = $id,
                    'athlete' => $athleteIds[] = $id,
                    'staff' => $staffIds[] = $id,
                    default => null,
                };
            }
        }

        return [
            'roles' => $roles,
            'class_ids' => $classIds,
            'sport' => $sport,
            'parent_ids' => array_values(array_unique($parentIds)),
            'coach_ids' => array_values(array_unique($coachIds)),
            'athlete_ids' => array_values(array_unique($athleteIds)),
            'staff_ids' => array_values(array_unique($staffIds)),
        ];
    }

    /**
     * @return array{sentCount: int, failedCount: int, recipients?: list<array<string, mixed>>}|null
     */
    public function emailStats(): ?array
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $stats = $meta['email_stats'] ?? $meta['emailStats'] ?? null;
        if (! is_array($stats)) {
            return null;
        }

        return [
            'sentCount' => (int) ($stats['sentCount'] ?? $stats['sent_count'] ?? 0),
            'failedCount' => (int) ($stats['failedCount'] ?? $stats['failed_count'] ?? 0),
            'recipients' => is_array($stats['recipients'] ?? null) ? array_values($stats['recipients']) : [],
            'queued_at' => $stats['queued_at'] ?? $stats['queuedAt'] ?? null,
        ];
    }

    public function imageUrl(): ?string
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $path = $meta['image_path'] ?? null;
        if (is_string($path) && $path !== '') {
            return Storage::disk('public')->url($path);
        }

        $url = $meta['imageUrl'] ?? null;
        if (is_string($url) && $url !== '') {
            return $url;
        }

        return null;
    }

    public function creatorDisplayName(?string $clubNameFallback = null): string
    {
        $fromUser = trim((string) ($this->creator?->name ?? ''));
        if ($fromUser !== '') {
            return $fromUser;
        }

        $fromMeta = trim((string) ($this->meta['created_by_name'] ?? $this->meta['createdBy'] ?? ''));
        if ($fromMeta !== '' && ! is_numeric($fromMeta)) {
            return $fromMeta;
        }

        $club = trim((string) ($clubNameFallback ?? ''));

        return $club !== '' ? $club : 'Διαχειριστής';
    }
}
