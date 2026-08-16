<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\Generators\ProcurementPackGenerator;
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
        Storage::fake('private_uploads');
        [$fss, $po] = $this->fssPo();

        $res = $this->actingAs($fss)->post("/api/fss/purchase-orders/{$po->uuid}/attachments", [
            'type' => 'receipt',
            'file' => UploadedFile::fake()->createWithContent('receipt.png', $this->pngBytes()),
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.type', 'receipt')
            ->assertJsonPath('data.url', fn (string $url): bool => str_starts_with($url, '/api/fss/purchase-order-attachments/'))
            ->assertJsonMissingPath('data.path');
        $this->assertDatabaseCount('purchase_order_attachments', 1);
        $this->assertDatabaseCount('stored_objects', 1);
    }

    public function test_multiple_files_upload_returns_array(): void
    {
        Storage::fake('private_uploads');
        [$fss, $po] = $this->fssPo();

        $res = $this->actingAs($fss)->post("/api/fss/purchase-orders/{$po->uuid}/attachments", [
            'type' => 'proof',
            'files' => [
                UploadedFile::fake()->createWithContent('a.png', $this->pngBytes()),
                UploadedFile::fake()->createWithContent('b.png', $this->pngBytes()),
                UploadedFile::fake()->createWithContent('c.png', $this->pngBytes()),
            ],
        ]);

        $res->assertCreated();
        $this->assertCount(3, $res->json('data'));
        $this->assertStringStartsWith('/api/fss/purchase-order-attachments/', $res->json('data.0.url'));
        $res->assertJsonMissingPath('data.0.path');
        $this->assertDatabaseCount('purchase_order_attachments', 3);
        $this->assertDatabaseCount('stored_objects', 3);
    }

    public function test_procurement_pack_embeds_private_evidence(): void
    {
        Storage::fake('private_uploads');
        [$fss, $po] = $this->fssPo();

        $this->actingAs($fss)->post("/api/fss/purchase-orders/{$po->uuid}/attachments", [
            'type' => 'proof',
            'file' => UploadedFile::fake()->createWithContent('proof.png', $this->pngBytes()),
        ])->assertCreated();

        $report = new Report(['type' => 'procurement_pack', 'parameters' => ['purchase_order_id' => $po->id]]);
        $pack = (new ProcurementPackGenerator)->data($report)['packs'][0];

        $this->assertStringStartsWith('data:image/', $pack['attachments'][0]['src']);
    }

    private function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
