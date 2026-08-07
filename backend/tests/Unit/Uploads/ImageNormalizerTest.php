<?php

namespace Tests\Unit\Uploads;

use App\Services\Uploads\ImageNormalizer;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ImageNormalizerTest extends TestCase
{
    #[Test]
    public function it_rejects_content_that_cannot_be_decoded_as_an_image(): void
    {
        $this->expectException(RuntimeException::class);

        app(ImageNormalizer::class)->normalize(
            UploadedFile::fake()->createWithContent('photo.jpg', 'not-an-image'),
            'profile',
        );
    }

    #[Test]
    public function detected_content_controls_the_stored_mime_and_extension(): void
    {
        $result = app(ImageNormalizer::class)->normalize(
            UploadedFile::fake()->createWithContent('spoofed.jpg', $this->pngBytes()),
            'profile',
        );

        $this->assertSame('image/png', $result['mime']);
        $this->assertSame('png', $result['extension']);
        $this->assertSame(1, $result['width']);
        $this->assertSame(1, $result['height']);
        $this->assertSame(hash('sha256', $result['bytes']), $result['sha256']);
    }

    #[Test]
    public function it_rejects_unknown_image_purposes(): void
    {
        $this->expectException(RuntimeException::class);

        app(ImageNormalizer::class)->normalizeBytes($this->pngBytes(), 'unknown');
    }

    private function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
