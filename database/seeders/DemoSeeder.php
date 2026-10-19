<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\ClubSetting;
use App\Models\LicensePackage;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $package = LicensePackage::query()->firstOrCreate(
            ['name' => 'Demo Full'],
            [
                'id' => (string) Str::uuid(),
                'athlete_cap' => 2000,
                'modules' => ['*'],
                'is_active' => true,
            ]
        );

        $club = Club::query()->firstOrCreate(
            ['slug' => 'demo-academy'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Demo Ακαδημία',
                'license_package_id' => $package->id,
                'enabled_modules' => ['*'],
                'primary_color' => '#0f1419',
                'accent_color' => '#0ea5e9',
                'is_active' => true,
            ]
        );

        ClubSetting::query()->firstOrCreate(
            ['club_id' => $club->id],
            ['id' => (string) Str::uuid()]
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@teamsuite.local'],
            [
                'name' => 'Διαχειριστής Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_platform_admin' => false,
                'active_club_id' => $club->id,
            ]
        );

        $admin->clubs()->syncWithoutDetaching([
            $club->id => [
                'role' => Roles::ADMIN,
                'permissions' => null,
                'finance_own_entries_only' => false,
                'is_active' => true,
            ],
        ]);

        // Screenshot-parity DEMO login (00-login.png)
        $demoPresentation = User::query()->updateOrCreate(
            ['email' => 'demo@teamsuite.app'],
            [
                'name' => 'Διαχειριστής DEMO',
                'password' => Hash::make('demo1234'),
                'email_verified_at' => now(),
                'is_platform_admin' => false,
                'active_club_id' => $club->id,
            ]
        );
        $demoPresentation->clubs()->syncWithoutDetaching([
            $club->id => [
                'role' => Roles::ADMIN,
                'permissions' => null,
                'finance_own_entries_only' => false,
                'is_active' => true,
            ],
        ]);

        // Primary Platform Admin (production operator)
        $platform = User::query()->updateOrCreate(
            ['email' => 'tsemperlidis.nikos@gmail.com'],
            [
                'name' => 'Νίκος Τσεμπερλίδης',
                'password' => Hash::make('ntstgt160813'),
                'email_verified_at' => now(),
                'is_platform_admin' => true,
                'active_club_id' => $club->id,
            ]
        );

        $platform->clubs()->syncWithoutDetaching([
            $club->id => [
                'role' => Roles::ADMIN,
                'permissions' => null,
                'finance_own_entries_only' => false,
                'is_active' => true,
            ],
        ]);

        // Keep legacy local platform account for demos
        User::query()->updateOrCreate(
            ['email' => 'platform@teamsuite.local'],
            [
                'name' => 'Platform Admin (demo)',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_platform_admin' => true,
                'active_club_id' => $club->id,
            ]
        );

        $parent = User::query()->updateOrCreate(
            ['email' => 'parent@teamsuite.local'],
            [
                'name' => 'Γονέας Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_platform_admin' => false,
                'active_club_id' => $club->id,
            ]
        );
        $parent->clubs()->syncWithoutDetaching([
            $club->id => [
                'role' => Roles::PARENT,
                'permissions' => null,
                'finance_own_entries_only' => false,
                'is_active' => true,
            ],
        ]);

        $parentApp = User::query()->updateOrCreate(
            ['email' => 'parent@teamsuite.app'],
            [
                'name' => 'Γονέας DEMO',
                'password' => Hash::make('demo1234'),
                'email_verified_at' => now(),
                'is_platform_admin' => false,
                'active_club_id' => $club->id,
            ]
        );
        $parentApp->clubs()->syncWithoutDetaching([
            $club->id => [
                'role' => Roles::PARENT,
                'permissions' => null,
                'finance_own_entries_only' => false,
                'is_active' => true,
            ],
        ]);

        $coach = User::query()->updateOrCreate(
            ['email' => 'coach@teamsuite.local'],
            [
                'name' => 'Προπονητής Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_platform_admin' => false,
                'active_club_id' => $club->id,
            ]
        );
        $coach->clubs()->syncWithoutDetaching([
            $club->id => [
                'role' => Roles::COACH,
                'permissions' => null,
                'finance_own_entries_only' => false,
                'is_active' => true,
            ],
        ]);

        $coachApp = User::query()->updateOrCreate(
            ['email' => 'coach@teamsuite.app'],
            [
                'name' => 'Προπονητής DEMO',
                'password' => Hash::make('demo1234'),
                'email_verified_at' => now(),
                'is_platform_admin' => false,
                'active_club_id' => $club->id,
            ]
        );
        $coachApp->clubs()->syncWithoutDetaching([
            $club->id => [
                'role' => Roles::COACH,
                'permissions' => null,
                'finance_own_entries_only' => false,
                'is_active' => true,
            ],
        ]);
    }
}
