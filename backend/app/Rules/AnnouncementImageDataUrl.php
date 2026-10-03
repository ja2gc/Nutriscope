<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AnnouncementImageDataUrl implements ValidationRule
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || preg_match('#\Adata:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/]*={0,2})\z#D', $value, $matches) !== 1
            || strlen($matches[2]) > 4 * (int) ceil(self::MAX_BYTES / 3)) {
            $fail('Each attachment must be a JPEG, PNG, or WebP image no larger than 5 MiB.');

            return;
        }

        $bytes = base64_decode($matches[2], true);
        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            $fail('Each attachment must be a JPEG, PNG, or WebP image no larger than 5 MiB.');

            return;
        }

        $details = @getimagesizefromstring($bytes);
        $mimeByType = [
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG => 'image/png',
            IMAGETYPE_WEBP => 'image/webp',
        ];
        if ($details === false
            || ($mimeByType[$details[2] ?? null] ?? null) !== $matches[1]
            || config('uploads.max_pixels') < $details[0] * $details[1]) {
            $fail('Each attachment must contain a valid JPEG, PNG, or WebP image.');
        }
    }
}
