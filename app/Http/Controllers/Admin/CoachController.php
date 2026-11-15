<?php

namespace App\Http\Controllers\Admin;

use App\Models\Coach;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CoachController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'coaches';
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $coach = $this->clubQuery(Coach::class, $request)->findOrFail($id);

        foreach ([
            'email',
            'phone',
            'sport',
            'gga_code',
            'hire_date',
            'license_level',
            'license_starts_at',
            'license_expires_at',
            'first_aid_starts_at',
            'first_aid_expires_at',
        ] as $nullableField) {
            if ($request->input($nullableField) === '') {
                $request->merge([$nullableField => null]);
            }
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'sport' => ['nullable', 'string', 'max:80'],
            'gga_code' => ['nullable', 'string', 'max:80'],
            'hire_date' => ['nullable', 'date'],
            'license_level' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable'],
            'license_starts_at' => ['nullable', 'date'],
            'license_expires_at' => ['nullable', 'date'],
            'first_aid_starts_at' => ['nullable', 'date'],
            'first_aid_expires_at' => ['nullable', 'date'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
            'license_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'first_aid_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'remove_photo' => ['sometimes', 'boolean'],
        ]);

        $meta = is_array($coach->meta) ? $coach->meta : [];
        $clubId = (string) $coach->club_id;

        foreach ([
            'license_starts_at',
            'license_expires_at',
            'first_aid_starts_at',
            'first_aid_expires_at',
        ] as $metaKey) {
            $value = $validated[$metaKey] ?? null;
            if ($value) {
                $meta[$metaKey] = $value;
            } else {
                unset($meta[$metaKey]);
            }
            unset($validated[$metaKey]);
        }

        if (! empty($meta['first_aid_expires_at'])) {
            $meta['first_aid'] = $meta['first_aid_expires_at'];
        } else {
            unset($meta['first_aid']);
        }

        if ($request->boolean('remove_photo')) {
            $this->deleteStoredPublicPath($meta['photo_url'] ?? $meta['photoUrl'] ?? null);
            unset($meta['photo_url'], $meta['photoUrl']);
        }

        $photo = $request->file('photo');
        if ($photo instanceof UploadedFile && $photo->isValid()) {
            $this->deleteStoredPublicPath($meta['photo_url'] ?? $meta['photoUrl'] ?? null);
            $meta['photo_url'] = $photo->store('club-media/coaches/'.$clubId.'/photos', 'public');
            unset($meta['photoUrl']);
        }

        $licenseFile = $request->file('license_file');
        if ($licenseFile instanceof UploadedFile && $licenseFile->isValid()) {
            $this->deleteStoredPublicPath($meta['license_file'] ?? $meta['licenseFile'] ?? null);
            $meta['license_file'] = $licenseFile->store('club-media/coaches/'.$clubId.'/licenses', 'public');
            unset($meta['licenseFile']);
        }

        $firstAidFile = $request->file('first_aid_file');
        if ($firstAidFile instanceof UploadedFile && $firstAidFile->isValid()) {
            $this->deleteStoredPublicPath($meta['first_aid_file'] ?? $meta['firstAidFile'] ?? null);
            $meta['first_aid_file'] = $firstAidFile->store('club-media/coaches/'.$clubId.'/first-aid', 'public');
            unset($meta['firstAidFile']);
        }

        unset($validated['photo'], $validated['license_file'], $validated['first_aid_file'], $validated['remove_photo']);

        $validated['is_active'] = filter_var($validated['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $validated['meta'] = $meta;

        $coach->fill($validated);
        $coach->save();

        return $this->redirectToIndex('admin.coaches', 'Η εγγραφή ενημερώθηκε.');
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'uuid',
            'is_active' => 'required|in:0,1,true,false',
        ]);

        $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        $coaches = $this->clubQuery(Coach::class, $request)
            ->whereIn('id', $data['ids'])
            ->get();

        foreach ($coaches as $coach) {
            $coach->update(['is_active' => $isActive]);
        }

        return back()->with(
            'success',
            'Μαζική αλλαγή: ενημερώθηκαν '.$coaches->count().' προπονητές.'
        );
    }

    private function deleteStoredPublicPath(mixed $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $value = trim($path);
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return;
        }

        $relative = str_starts_with($value, '/storage/')
            ? substr($value, strlen('/storage/'))
            : ltrim($value, '/');

        if ($relative !== '' && Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }
}
