<?php

namespace App\Support;

use App\Models\Athlete;

final class PreferredNotifyEmail
{
    /**
     * @return list<'father'|'mother'|'athlete'>
     */
    public static function targets(?string $preferred): array
    {
        $raw = strtolower(trim((string) $preferred));
        if ($raw === '' || $raw === 'both') {
            return ['father', 'mother'];
        }

        if (in_array($raw, ['father', 'mother', 'athlete'], true)) {
            return [$raw];
        }

        $parts = preg_split('/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $part) {
            if ($part === 'both') {
                $out[] = 'father';
                $out[] = 'mother';

                continue;
            }
            if (in_array($part, ['father', 'mother', 'athlete'], true)) {
                $out[] = $part;
            }
        }

        $out = array_values(array_unique($out));

        return $out !== [] ? $out : ['father'];
    }

    public static function resolvePrimary(Athlete $athlete): ?string
    {
        $meta = $athlete->meta ?? [];
        $preferred = $meta['preferred_parent_email'] ?? $meta['preferredParentEmail'] ?? null;
        $targets = self::targets(is_string($preferred) ? $preferred : null);

        $map = [
            'father' => trim((string) ($meta['father_email'] ?? $meta['fatherEmail'] ?? '')),
            'mother' => trim((string) ($meta['mother_email'] ?? $meta['motherEmail'] ?? '')),
            'athlete' => trim((string) ($athlete->email ?? '')),
        ];

        foreach ($targets as $target) {
            $email = $map[$target] ?? '';
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        foreach (['father', 'mother', 'athlete'] as $fallback) {
            if (in_array($fallback, $targets, true)) {
                continue;
            }
            $email = $map[$fallback] ?? '';
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }
}
