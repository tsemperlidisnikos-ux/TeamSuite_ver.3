<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ClubPublicMedia
{
    /**
     * Persist a browser data-URL image to the public disk and return a relative path.
     */
    public static function storeDataUrl(string $clubId, string $dataUrl, string $prefix): ?string
    {
        if (! preg_match('#^data:(image/(?:jpeg|jpg|png|webp|gif));base64,(.+)$#i', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = strtolower($matches[1]);
        $extension = match ($extension) {
            'image/jpg', 'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $path = trim($prefix, '/').'/'.$clubId.'/'.Str::uuid()->toString().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /**
     * Normalize an uploaded/stored media value into a durable relative path or absolute/data URL.
     */
    public static function normalizeForStorage(?string $value, string $clubId, string $prefix): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:image/')) {
            return self::storeDataUrl($clubId, $value, $prefix) ?? null;
        }

        if (self::isLegacyClubMediaPath($value)) {
            return null;
        }

        if (str_starts_with($value, '/storage/')) {
            return ltrim(substr($value, strlen('/storage/')), '/');
        }

        $publicUrl = rtrim((string) Storage::disk('public')->url(''), '/').'/';
        if (str_starts_with($value, $publicUrl)) {
            return ltrim(substr($value, strlen($publicUrl)), '/');
        }

        return $value;
    }

    /**
     * Resolve a stored media value to a browser-usable URL (or null when unusable).
     */
    public static function publicUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }

        if (self::isLegacyClubMediaPath($value)) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        if (str_starts_with($value, '/storage/')) {
            return $value;
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }

    public static function isLegacyClubMediaPath(string $value): bool
    {
        return str_contains($value, '/api/club-media')
            || str_starts_with($value, 'ss360-media/')
            || str_contains($value, 'ss360-media%2F')
            || str_contains($value, 'ss360-media/');
    }
}
