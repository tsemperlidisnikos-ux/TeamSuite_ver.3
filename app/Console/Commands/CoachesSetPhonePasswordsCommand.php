<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Coach;
use App\Models\User;
use App\Support\Roles;
use App\Support\UserPasswordVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoachesSetPhonePasswordsCommand extends Command
{
    protected $signature = 'coaches:set-phone-passwords
                            {--club= : Club id or name fragment (default: ΑΠΟΛΛΩΝ ΠΑΤΡ)}
                            {--dry-run : Show actions without saving}';

    protected $description = 'Set coach login passwords to their mobile phone (Platform Admin credential directory).';

    public function handle(): int
    {
        $clubOpt = trim((string) $this->option('club'));
        if ($clubOpt === '') {
            $clubOpt = 'ΑΠΟΛΛΩΝ ΠΑΤΡ';
        }

        $club = Club::query()->where('id', $clubOpt)->first();
        if (! $club) {
            $club = Club::query()->where('name', 'like', '%'.$clubOpt.'%')->orderBy('name')->first();
        }

        if (! $club) {
            $this->error('Club not found: '.$clubOpt);

            return self::FAILURE;
        }

        $this->info('Club: '.$club->name.' ('.$club->id.')');

        $coaches = Coach::query()
            ->withoutGlobalScopes()
            ->where('club_id', $club->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($coaches->isEmpty()) {
            $this->warn('No coaches found.');

            return self::SUCCESS;
        }

        $emailCounts = [];
        foreach ($coaches as $coach) {
            $email = strtolower(trim((string) $coach->email));
            if ($email !== '') {
                $emailCounts[$email] = ($emailCounts[$email] ?? 0) + 1;
            }
        }

        $rows = [];
        $updated = 0;
        $skipped = 0;

        $apply = function () use ($coaches, $club, $emailCounts, &$rows, &$updated, &$skipped): void {
            foreach ($coaches as $coach) {
                $phone = $this->normalizePhone($coach->phone);
                $rawEmail = strtolower(trim((string) $coach->email));
                $name = trim($coach->last_name.' '.$coach->first_name);

                if ($phone === '' || strlen($phone) < 8) {
                    $skipped++;
                    $rows[] = [$name, $rawEmail, '—', 'SKIP no phone'];

                    continue;
                }

                $sharedEmail = $rawEmail !== '' && ($emailCounts[$rawEmail] ?? 0) > 1;
                $loginEmail = $rawEmail;

                if ($sharedEmail || $rawEmail === '' || ! str_contains($rawEmail, '@')) {
                    $slug = Str::slug($coach->last_name.'-'.$coach->first_name, '.');
                    if ($slug === '') {
                        $slug = 'coach';
                    }
                    $loginEmail = $slug.'.'.$phone.'@coach.apollonpatras.local';
                }

                if ($this->option('dry-run')) {
                    $updated++;
                    $rows[] = [$name, $loginEmail, $phone, $sharedEmail ? 'DRY unique-login' : 'DRY'];

                    continue;
                }

                $user = null;
                if ($coach->user_id && ! $sharedEmail) {
                    $user = User::query()->find($coach->user_id);
                }
                if (! $user) {
                    $user = User::query()->where('email', $loginEmail)->first();
                }

                if (! $user) {
                    $user = User::query()->create([
                        'name' => $name !== '' ? $name : $loginEmail,
                        'email' => $loginEmail,
                        'password' => $phone,
                        'password_reveal' => UserPasswordVault::seal($phone),
                        'email_verified_at' => now(),
                        'is_platform_admin' => false,
                        'active_club_id' => $club->id,
                    ]);
                } else {
                    if (
                        $user->email !== $loginEmail
                        && ! User::query()->where('email', $loginEmail)->where('id', '!=', $user->id)->exists()
                    ) {
                        $user->email = $loginEmail;
                    }
                    UserPasswordVault::setPassword($user, $phone);
                    $user->forceFill([
                        'name' => $name !== '' ? $name : $user->name,
                        'active_club_id' => $user->active_club_id ?: $club->id,
                    ])->save();
                }

                $user->clubs()->syncWithoutDetaching([
                    $club->id => [
                        'role' => Roles::COACH,
                        'permissions' => json_encode([]),
                        'finance_own_entries_only' => false,
                        'is_active' => (bool) $coach->is_active,
                    ],
                ]);

                if ((string) $coach->user_id !== (string) $user->id) {
                    $coach->forceFill(['user_id' => $user->id])->save();
                }

                $updated++;
                $rows[] = [$name, $loginEmail, $phone, $sharedEmail ? 'OK unique-login' : 'OK'];
            }
        };

        if ($this->option('dry-run')) {
            $apply();
        } else {
            DB::transaction($apply);
        }

        $this->table(['Προπονητής', 'USERNAME (email)', 'PASSWORD (κινητό)', 'Κατάσταση'], $rows);
        $this->info("Updated: {$updated} · Skipped: {$skipped}");

        return self::SUCCESS;
    }

    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '30') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
}
