<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Stores a Platform-Admin-only recoverable copy of login passwords
 * (encrypted with APP_KEY). Login hash remains in `password`.
 */
final class UserPasswordVault
{
    public static function seal(string $plainPassword): string
    {
        return Crypt::encryptString($plainPassword);
    }

    public static function open(?string $sealed): string
    {
        if ($sealed === null || $sealed === '') {
            return '';
        }

        try {
            return Crypt::decryptString($sealed);
        } catch (DecryptException) {
            return '';
        }
    }

    public static function remember(User $user, string $plainPassword): void
    {
        $user->password_reveal = self::seal($plainPassword);
    }

    /** Set hashed password + encrypted reveal and persist. */
    public static function setPassword(User $user, string $plainPassword, bool $save = true): void
    {
        $user->password = $plainPassword;
        self::remember($user, $plainPassword);
        if ($save) {
            $user->save();
        }
    }

    /** After successful login, keep reveal in sync with the password just used. */
    public static function captureFromLogin(User $user, string $plainPassword): void
    {
        if ($plainPassword === '') {
            return;
        }

        $current = self::open($user->password_reveal);
        if ($current === $plainPassword) {
            return;
        }

        self::remember($user, $plainPassword);
        $user->save();
    }
}
