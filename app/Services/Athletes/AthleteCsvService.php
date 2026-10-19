<?php

namespace App\Services\Athletes;

use App\Models\Athlete;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AthleteCsvService
{
    /** @return list<string> */
    public function headers(): array
    {
        return [
            'first_name',
            'last_name',
            'email',
            'phone',
            'status',
            'sport',
            'monthly_fee',
            'gender',
            'guardian_name',
            'guardian_phone',
            'birth_date',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Athlete>  $athletes
     */
    public function export(iterable $athletes): string
    {
        $lines = [implode(';', $this->headers())];

        foreach ($athletes as $a) {
            $lines[] = implode(';', [
                $this->csv($a->first_name),
                $this->csv($a->last_name),
                $this->csv($a->email),
                $this->csv($a->phone),
                $this->csv($a->status),
                $this->csv($a->sport),
                $this->csv((string) $a->monthly_fee),
                $this->csv($a->gender),
                $this->csv($a->guardian_name),
                $this->csv($a->guardian_phone),
                $this->csv(optional($a->birth_date)->format('Y-m-d')),
            ]);
        }

        return "\xEF\xBB\xBF".implode("\n", $lines);
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(string $clubId, UploadedFile $file): array
    {
        $content = $file->get();
        if ($content === false || $content === '') {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Άδειο αρχείο.']];
        }

        // Strip BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $rows = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        if ($rows === []) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Δεν βρέθηκαν γραμμές.']];
        }

        $delimiter = str_contains($rows[0], ';') ? ';' : ',';
        $header = str_getcsv(array_shift($rows), $delimiter);
        $header = array_map(fn ($h) => Str::of((string) $h)->trim()->lower()->replace(' ', '_')->toString(), $header);

        $map = [];
        foreach ($this->headers() as $expected) {
            $idx = array_search($expected, $header, true);
            if ($idx !== false) {
                $map[$expected] = $idx;
            }
        }

        if (! isset($map['first_name'], $map['last_name'])) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['Απαιτούνται στήλες first_name και last_name.'],
            ];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, $delimiter);
            $first = trim((string) ($cols[$map['first_name']] ?? ''));
            $last = trim((string) ($cols[$map['last_name']] ?? ''));
            if ($first === '' || $last === '') {
                $skipped++;
                $errors[] = 'Γραμμή '.($i + 2).': λείπει όνομα/επώνυμο.';

                continue;
            }

            $email = isset($map['email']) ? trim((string) ($cols[$map['email']] ?? '')) : '';
            $payload = [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email !== '' ? $email : null,
                'phone' => isset($map['phone']) ? (trim((string) ($cols[$map['phone']] ?? '')) ?: null) : null,
                'status' => $this->normalizeStatus(isset($map['status']) ? (string) ($cols[$map['status']] ?? '') : 'active'),
                'sport' => isset($map['sport']) ? (trim((string) ($cols[$map['sport']] ?? '')) ?: null) : null,
                'monthly_fee' => isset($map['monthly_fee']) ? (float) str_replace(',', '.', (string) ($cols[$map['monthly_fee']] ?? 0)) : 0,
                'gender' => isset($map['gender']) ? (trim((string) ($cols[$map['gender']] ?? '')) ?: null) : null,
                'guardian_name' => isset($map['guardian_name']) ? (trim((string) ($cols[$map['guardian_name']] ?? '')) ?: null) : null,
                'guardian_phone' => isset($map['guardian_phone']) ? (trim((string) ($cols[$map['guardian_phone']] ?? '')) ?: null) : null,
                'birth_date' => isset($map['birth_date']) ? ($this->parseDate((string) ($cols[$map['birth_date']] ?? '')) ?: null) : null,
            ];

            $existing = null;
            if ($email) {
                $existing = Athlete::query()->withoutGlobalScopes()
                    ->where('club_id', $clubId)
                    ->where('email', $email)
                    ->first();
            }
            if (! $existing) {
                $existing = Athlete::query()->withoutGlobalScopes()
                    ->where('club_id', $clubId)
                    ->where('first_name', $first)
                    ->where('last_name', $last)
                    ->first();
            }

            if ($existing) {
                $existing->fill($payload)->save();
                $updated++;
            } else {
                Athlete::query()->withoutGlobalScopes()->create([
                    ...$payload,
                    'id' => (string) Str::uuid(),
                    'club_id' => $clubId,
                    'gdpr_consent' => 'pending',
                ]);
                $created++;
            }
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    private function csv(?string $value): string
    {
        $value = (string) ($value ?? '');
        if (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    private function normalizeStatus(string $raw): string
    {
        $raw = Str::lower(trim($raw));
        if (in_array($raw, ['active', 'ενεργός', 'ενεργος', 'ενεργοί', 'ενεργοι'], true)) {
            return 'active';
        }
        if (in_array($raw, ['trial', 'δοκιμή', 'δοκιμη', 'δοκιμαστικός', 'δοκιμαστικος'], true)) {
            return 'trial';
        }
        if (in_array($raw, ['inactive', 'ανενεργός', 'ανενεργος', 'ανενεργοί', 'ανενεργοι'], true)) {
            return 'inactive';
        }

        return in_array($raw, ['active', 'inactive', 'trial'], true) ? $raw : 'active';
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
