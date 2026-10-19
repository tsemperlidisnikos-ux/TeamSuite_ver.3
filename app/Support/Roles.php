<?php

namespace App\Support;

final class Roles
{
    public const PLATFORM_ADMIN = 'platform_admin';

    public const ADMIN = 'admin';

    public const SECRETARIAT = 'secretariat';

    public const COACH = 'coach';

    public const STAFF = 'staff';

    public const DOCTOR = 'doctor';

    public const ATHLETE = 'athlete';

    public const PARENT = 'parent';

    /** @return list<string> */
    public static function clubRoles(): array
    {
        return [
            self::ADMIN,
            self::SECRETARIAT,
            self::COACH,
            self::STAFF,
            self::DOCTOR,
            self::ATHLETE,
            self::PARENT,
        ];
    }

    /** @return list<string> */
    public static function adminSidebarRoles(): array
    {
        return [
            self::ADMIN,
            self::SECRETARIAT,
            self::COACH,
            self::STAFF,
            self::DOCTOR,
        ];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::PLATFORM_ADMIN => 'Διαχειριστής πλατφόρμας',
            self::ADMIN => 'Διαχειριστής συλλόγου',
            self::DOCTOR => 'Ιατρός',
            self::COACH => 'Προπονητής',
            self::SECRETARIAT => 'Γραμματεία',
            self::STAFF => 'Προσωπικό',
            self::ATHLETE => 'Αθλητής',
            self::PARENT => 'Γονέας',
        ];
    }

    public static function label(?string $role): string
    {
        if ($role === null || $role === '') {
            return '';
        }

        return self::labels()[$role] ?? $role;
    }
}
