<?php

namespace App\Support;

final class PersonNameSplitter
{
    /**
     * @return array{last_name: string, first_name: string}
     */
    public static function split(string $fullName): array
    {
        $tokens = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return ['last_name' => '', 'first_name' => ''];
        }
        if (count($tokens) === 1) {
            return ['last_name' => $tokens[0], 'first_name' => ''];
        }

        // Ελληνική συνήθεια σε καταλόγους: ΕΠΩΝΥΜΟ ΟΝΟΜΑ …
        return [
            'last_name' => $tokens[0],
            'first_name' => implode(' ', array_slice($tokens, 1)),
        ];
    }

    /**
     * ΟΝΟΜΑ … ΕΠΩΝΥΜΟ — όπως ονόματα γονέων (father/mother full name).
     *
     * @return array{last_name: string, first_name: string}
     */
    public static function splitGivenNameFirst(string $fullName): array
    {
        $tokens = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return ['last_name' => '', 'first_name' => ''];
        }
        if (count($tokens) === 1) {
            return ['last_name' => $tokens[0], 'first_name' => ''];
        }

        return [
            'last_name' => $tokens[array_key_last($tokens)],
            'first_name' => implode(' ', array_slice($tokens, 0, -1)),
        ];
    }
}
