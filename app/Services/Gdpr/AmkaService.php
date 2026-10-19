<?php

namespace App\Services\Gdpr;

use App\Models\AmkaAccessLog;
use App\Models\Athlete;
use App\Models\User;

class AmkaService
{
    public function normalize(?string $amka): ?string
    {
        if ($amka === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $amka) ?: '';

        return $digits !== '' ? $digits : null;
    }

    public function fingerprint(?string $amka): ?string
    {
        $digits = $this->normalize($amka);

        return $digits ? hash('sha256', $digits) : null;
    }

    /**
     * Attributes for mass-assignment / backup import (no DB write).
     *
     * @return array{amka_encrypted: ?string, amka_fingerprint: ?string}
     */
    public function storeFromPlaintext(?string $amka): array
    {
        $digits = $this->normalize($amka);

        return [
            'amka_encrypted' => $digits,
            'amka_fingerprint' => $this->fingerprint($digits),
        ];
    }

    public function storeOnAthlete(Athlete $athlete, ?string $amka, ?User $actor = null): void
    {
        $athlete->fill($this->storeFromPlaintext($amka))->save();

        if ($actor) {
            AmkaAccessLog::query()->withoutGlobalScopes()->create([
                'club_id' => $athlete->club_id,
                'athlete_id' => $athlete->id,
                'user_id' => $actor->id,
                'action' => 'amka.write',
            ]);
        }
    }

    public function reveal(Athlete $athlete, User $actor): ?string
    {
        AmkaAccessLog::query()->withoutGlobalScopes()->create([
            'club_id' => $athlete->club_id,
            'athlete_id' => $athlete->id,
            'user_id' => $actor->id,
            'action' => 'amka.read',
        ]);

        // encrypted cast returns plaintext to authorized caller only
        return $athlete->amka_encrypted;
    }
}
