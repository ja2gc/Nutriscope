<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** PO receipt/proof uploads — single (back-compat) and multiple files. */
class PoAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    private function fssPo(): array
    {
        $fss = User::factory()->create(['role' => 'FSS']);
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $fss->id]);

        return [$fss, $po];
    }

    public function test_single_file_upload_returns_object(): void
    {
        Storage::fake('public');
        [$fss, $po] = $this->fssPo();

        $res = $this->actingAs($fss)->post("/api/fss/purchase-orders/{$po->uuid}/attachments", [
            'type' => 'receipt',
            'file' => UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'),
        ]);

        $res->assertCreated()->assertJsonPath('data.type', 'receipt');
        $this->assertDatabaseCount('purchase_order_attachments', 1);
    }

    public function test_multiple_files_upload_returns_array(): void
    {
        Storage::fake('public');
        [$fss, $po] = $this->fssPo();

        $res = $this->actingAs($fss)->post("/api/fss/purchase-orders/{$po->uuid}/attachments", [
            'type' => 'proof',
            'files' => [
                UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('b.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('c.jpg', 100, 'image/jpeg'),
            ],
        ]);

        $res->assertCreated();
        $this->assertCount(3, $res->json('data'));
        $this->assertDatabaseCount('purchase_order_attachments', 3);
    }
}
