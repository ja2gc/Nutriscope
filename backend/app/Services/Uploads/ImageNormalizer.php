<?php

namespace App\Services\Uploads;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class ImageNormalizer
{
    private const MIME_EXTENSIONS = [
        IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'extension' => 'jpg'],
        IMAGETYPE_PNG => ['mime' => 'image/png', 'extension' => 'png'],
        IMAGETYPE_WEBP => ['mime' => 'image/webp', 'extension' => 'webp'],
    ];

    /** @return array{bytes:string,mime:string,extension:string,width:int,height:int,sha256:string} */
    public function normalize(UploadedFile $file, string $purpose): array
    {
        return $this->normalizeBytes($file->get(), $purpose);
    }

    /** @return array{bytes:string,mime:string,extension:string,width:int,height:int,sha256:string} */
    public function normalizeBytes(string $bytes, string $purpose): array
    {
        $this->assertPurpose($purpose);
        if ($bytes === '' || strlen($bytes) > config("uploads.max_bytes.{$purpose}")) {
            throw new RuntimeException('Image size is invalid.');
        }

        $details = @getimagesizefromstring($bytes);
        if ($details === false || ! isset(self::MIME_EXTENSIONS[$details[2]])) {
            throw new RuntimeException('Image content is invalid.');
        }

        [$width, $height] = [$details[0], $details[1]];
        if ($width < 1 || $height < 1 || $width * $height > config('uploads.max_pixels')) {
            throw new RuntimeException('Image dimensions are invalid.');
        }

        $format = self::MIME_EXTENSIONS[$details[2]];
        $max = (int) config("uploads.max_dimension.{$purpose}");
        if (! extension_loaded('gd')) {
            if ($width > $max || $height > $max) {
                throw new RuntimeException('Image processing is unavailable.');
            }

            return $this->result($bytes, $format, $width, $height);
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new RuntimeException('Image decoding failed.');
        }

        try {
            $source = $this->orientJpeg($source, $bytes, $details[2]);
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, $max / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($details[2] === IMAGETYPE_PNG || $details[2] === IMAGETYPE_WEBP) {
                imagealphablending($target, false);
                imagesavealpha($target, true);
            }
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            $written = match ($details[2]) {
                IMAGETYPE_JPEG => imagejpeg($target, null, $purpose === 'clinical' ? 95 : 88),
                IMAGETYPE_PNG => imagepng($target, null, 6),
                IMAGETYPE_WEBP => imagewebp($target, null, $purpose === 'clinical' ? 95 : 88),
            };
            $normalized = ob_get_clean();
            imagedestroy($target);
            if (! $written || ! is_string($normalized) || $normalized === '') {
                throw new RuntimeException('Image encoding failed.');
            }

            return $this->result($normalized, $format, $targetWidth, $targetHeight);
        } finally {
            imagedestroy($source);
        }
    }

    private function assertPurpose(string $purpose): void
    {
        if (! in_array($purpose, ['profile', 'purchase_order', 'clinical', 'branding'], true)) {
            throw new RuntimeException('Image purpose is invalid.');
        }
    }

    private function orientJpeg(\GdImage $image, string $bytes, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }
        $path = tempnam(sys_get_temp_dir(), 'nutriscope-image-');
        if (! is_string($path)) {
            return $image;
        }
        file_put_contents($path, $bytes);
        $exif = @exif_read_data($path);
        @unlink($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    /** @param array{mime:string,extension:string} $format
     * @return array{bytes:string,mime:string,extension:string,width:int,height:int,sha256:string}
     */
    private function result(string $bytes, array $format, int $width, int $height): array
    {
        return [...$format, 'bytes' => $bytes, 'width' => $width, 'height' => $height, 'sha256' => hash('sha256', $bytes)];
    }
}
