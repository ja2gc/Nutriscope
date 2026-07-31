<?php

namespace Tests\Unit;

use App\Models\PurchaseOrderAttachment;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploadDiskConfigurationTest extends TestCase
{
    #[Test]
    public function durable_public_upload_services_use_the_configured_disk(): void
    {
        foreach ([
            'app/Services/FSS/PurchaseOrderAttachmentStorage.php',
            'app/Services/Reports/BrandingAssetStorage.php',
            'app/Jobs/DeleteQuarantinedPurchaseOrderAttachment.php',
            'app/Jobs/RestoreQuarantinedPurchaseOrderAttachment.php',
        ] as $file) {
            $contents = file_get_contents(base_path($file));
            $this->assertIsString($contents);
            $this->assertStringContainsString("config('filesystems.uploads')", $contents, $file);
            $this->assertStringNotContainsString("disk('public')", $contents, $file);
        }
    }

    #[Test]
    public function generated_reports_remain_separate_from_user_upload_storage(): void
    {
        $contents = file_get_contents(base_path('app/Services/Reports/ReportService.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString("disk('public')", $contents);
    }

    #[Test]
    public function purchase_order_attachments_expose_the_configured_disk_url(): void
    {
        Storage::fake('public');
        config(['filesystems.uploads' => 'public']);

        $attachment = new PurchaseOrderAttachment(['path' => 'po-attachments/receipt.jpg']);

        $this->assertSame(Storage::disk('public')->url($attachment->path), $attachment->url);
        $this->assertArrayHasKey('url', $attachment->toArray());
    }
}
