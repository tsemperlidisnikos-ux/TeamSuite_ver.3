<?php

namespace App\Support;

final class GreekGivenName
{
    /**
     * @return 'father'|'mother'|null
     */
    public static function guessParentRole(?string $fullName): ?string
    {
        $raw = trim((string) $fullName);
        if ($raw === '') {
            return null;
        }

        $tokens = preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $given = $tokens[0] ?? '';
        $upper = self::foldUpper($given);
        if ($upper === '') {
            return null;
        }

        if (preg_match('/[ΑΗΩ]$/u', $upper) === 1) {
            return 'mother';
        }

        if (preg_match('/[ΣΝΡΞΨ]$/u', $upper) === 1) {
            return 'father';
        }

        if (count($tokens) > 1) {
            $surname = self::foldUpper($tokens[array_key_last($tokens)]);
            if (
                str_ends_with($surname, 'ΠΟΥΛΟΥ')
                || str_ends_with($surname, 'ΑΚΟΥ')
                || str_ends_with($surname, 'ΙΔΟΥ')
                || (str_ends_with($surname, 'ΟΥ') && ! str_ends_with($surname, 'ΟΓΛΟΥ'))
            ) {
                return 'mother';
            }
            if (
                str_ends_with($surname, 'ΠΟΥΛΟΣ')
                || str_ends_with($surname, 'ΑΚΗΣ')
                || str_ends_with($surname, 'ΙΔΗΣ')
                || str_ends_with($surname, 'ΟΣ')
                || str_ends_with($surname, 'ΗΣ')
            ) {
                return 'father';
            }
        }

        return null;
    }

    public static function extractGivenName(?string $fullName): string
    {
        $raw = trim((string) $fullName);
        if ($raw === '') {
            return '';
        }

        $tokens = preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $tokens[0] ?? '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function migrateGuardianNameIntoParentFields(array $data): array
    {
        $guardian = trim((string) ($data['guardian_name'] ?? ''));
        if ($guardian === '') {
            return $data;
        }

        $role = self::guessParentRole($guardian);
        $given = self::extractGivenName($guardian);
        if ($role === null || $given === '') {
            return $data;
        }

        if ($role === 'father' && trim((string) ($data['father_first_name'] ?? '')) === '') {
            $data['father_first_name'] = $given;
            $data['guardian_name'] = null;
        } elseif ($role === 'mother' && trim((string) ($data['mother_first_name'] ?? '')) === '') {
            $data['mother_first_name'] = $given;
            $data['guardian_name'] = null;
        }

        return $data;
    }

    private static function foldUpper(string $value): string
    {
        $folded = \Normalizer::normalize($value, \Normalizer::FORM_D);
        if (! is_string($folded)) {
            $folded = $value;
        }
        $folded = preg_replace('/\p{M}/u', '', $folded) ?? $folded;

        return mb_strtoupper($folded, 'UTF-8');
    }
}
