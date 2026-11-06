<?php

namespace Tests\Feature;

use App\Actions\Reports\PrepareSavedReport;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\Reports\Generators\ProgramProjectActivityGenerator;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcurementReportKindsTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $kind): PurchaseOrder
    {
        $rnd = User::factory()->rnd()->create();
        $suggested = $kind === 'suggested_food';
        $list = ShoppingList::factory()->create([
            'rnd_user_id' => $rnd->id, 'name' => 'Custom example', 'list_date' => '2026-11-06',
            'list_type' => $suggested ? 'suggested' : 'manual', 'procurement_track' => $kind === 'supplies' ? 'supplies' : 'food',
            'period_start' => $suggested ? '2026-11-01' : null, 'period_end' => $suggested ? '2026-11-02' : null,
            'status' => 'converted',
        ]);
        $po = PurchaseOrder::factory()->create([
            'rnd_user_id' => $rnd->id, 'shopping_list_id' => $list->id, 'procurement_track' => $list->procurement_track,
            'order_date' => '2026-11-06', 'lifecycle_status' => 'completed', 'status' => 'received', 'total_amount' => 420,
        ]);
        $po->programProjectActivity()->create([
            'activity' => $kind === 'supplies' ? 'Food Service Supplies' : 'Food Subsistence for Patients',
            'period_start' => $list->period_start, 'period_end' => $list->period_end,
            'actual_total_cost' => 420, 'actual_output_patients' => $suggested ? 350 : null,
            'execution_frozen_at' => now(), 'execution_payload' => $suggested ? ['served_populations' => ['2026-11-01' => 160, '2026-11-02' => 190]] : [],
        ]);
        $po->items()->create(['description' => 'Demo item', 'qty' => 2, 'unit' => 'unit', 'unit_price' => 210, 'total_value' => 420, 'actual_qty' => 2, 'actual_unit_price' => 210]);

        return $po;
    }

    public function test_manual_and_supplies_pos_do_not_expose_population_progress(): void
    {
        foreach (['manual_food', 'supplies'] as $kind) {
            $payload = (new PurchaseOrderResource($this->order($kind)->load('shoppingList')))->toArray(Request::create('/'));
            $this->assertNull($payload['served_population_progress']);
        }
    }

    public function test_all_procurement_kinds_have_ppa_data_without_suggested_only_placeholders(): void
    {
        foreach (['manual_food', 'supplies'] as $kind) {
            $po = $this->order($kind);
            $data = app(ProgramProjectActivityGenerator::class)->data(new Report(['parameters' => ['purchase_order_id' => $po->id]]));
            $this->assertFalse($data['is_suggested_food']);
            $this->assertSame('Demo item', $data['purchase_items'][0]['description']);
            $this->assertSame(420.0, $data['total_cost']);
            $html = view('reports.program-project-activity', array_merge($data, [
                'report' => new Report, 'branding' => new ReportBranding, 'signatories' => [],
                'generated_at' => now(), 'paper_orientation' => 'portrait',
            ]))->render();
            $this->assertStringNotContainsString('Patients', strip_tags(str_replace('Food Subsistence for Patients', '', $html)));
            $this->assertStringNotContainsString('Average cost per head', $html);
            $this->assertStringNotContainsString('<th>Menu</th>', $html);
        }
    }

    public function test_suggested_ppa_uses_frozen_daily_counts_and_keeps_one_total_cost(): void
    {
        $po = $this->order('suggested_food');
        $data = app(ProgramProjectActivityGenerator::class)->data(new Report(['parameters' => ['purchase_order_id' => $po->id]]));
        $this->assertTrue($data['is_suggested_food']);
        $this->assertSame(['2026-11-01' => 160, '2026-11-02' => 190], $data['served_populations']);
        $this->assertSame(350, $data['population']);
        $this->assertSame(420.0, $data['total_cost']);
        $html = view('reports.program-project-activity', array_merge($data, [
            'report' => new Report, 'branding' => new ReportBranding, 'signatories' => [],
            'generated_at' => now(), 'paper_orientation' => 'portrait',
        ]))->render();
        $this->assertStringNotContainsString('Nov 1, 2026', $html);
        $this->assertStringContainsString('Total Output', $html);
        $this->assertStringContainsString('Average cost per head a day', $html);
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $rows = (new \DOMXPath($dom))->query('//table[@class="grid"]/tbody/tr');
        $this->assertCount(2, $rows);
        $this->assertSame('01', trim($rows->item(0)->getElementsByTagName('td')->item(1)->textContent));
        $this->assertSame('160', trim($rows->item(0)->getElementsByTagName('td')->item(3)->textContent));
        $this->assertSame('02', trim($rows->item(1)->getElementsByTagName('td')->item(0)->textContent));
        $this->assertSame('190', trim($rows->item(1)->getElementsByTagName('td')->item(1)->textContent));
    }

    public function test_procurement_kind_filters_apply_before_pagination_for_both_report_types(): void
    {
        $orders = [];
        foreach (['suggested_food', 'manual_food', 'supplies'] as $kind) {
            $orders[$kind] = $this->order($kind);
        }
        $this->actingAs($orders['manual_food']->fss);
        foreach (['program_project_activity', 'procurement_pack'] as $type) {
            foreach ($orders as $kind => $po) {
                $this->getJson("/api/rnd/reports/{$type}/instances?procurement_kind={$kind}&per_page=1")
                    ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.instances.0.params.purchase_order_id', $po->id);
            }
            $this->getJson("/api/rnd/reports/{$type}/instances?procurement_kind=all")->assertOk()->assertJsonPath('meta.total', 3);
            $this->getJson("/api/rnd/reports/{$type}/instances?procurement_kind=unknown")->assertUnprocessable();
        }
    }

    public function test_saved_ppa_layout_update_is_explicit_and_preserves_identity_and_branding(): void
    {
        Storage::fake('report_cache');
        Storage::fake(config('filesystems.private_uploads'));
        $this->mock(ReportService::class, function ($mock): void {
            $mock->shouldReceive('signatoriesFor')->andReturn([]);
            $mock->shouldReceive('buildPdf')->twice()->andReturn(['bytes' => "%PDF-1.4\noriginal\n%%EOF"], ['bytes' => "%PDF-1.4\nupdated\n%%EOF"]);
        });
        $po = $this->order('suggested_food');
        $prepare = app(PrepareSavedReport::class);
        $params = ['purchase_order_id' => (string) $po->id];
        $original = $prepare->execute($po->fss, 'program_project_activity', $params);
        $original->update(['appearance_version' => 'v1']);
        $untouched = $prepare->execute($po->fss, 'program_project_activity', $params);
        $this->assertSame($original->official_file_stored_object_id, $untouched->official_file_stored_object_id);
        $updated = $prepare->execute($po->fss, 'program_project_activity', $params, refreshLayout: true);
        $this->assertSame($original->uuid, $updated->uuid);
        $this->assertSame($original->archive_identity, $updated->archive_identity);
        $this->assertSame($original->snapshot, $updated->snapshot);
        $this->assertTrue($original->created_at->equalTo($updated->created_at));
        $this->assertNotSame($original->official_file_stored_object_id, $updated->official_file_stored_object_id);
        $this->assertSame(ProgramProjectActivityGenerator::APPEARANCE_VERSION, $updated->appearance_version);
        $again = $prepare->execute($po->fss, 'program_project_activity', $params, refreshLayout: true);
        $this->assertSame($updated->official_file_stored_object_id, $again->official_file_stored_object_id);
    }

    public function test_procurement_pdf_layouts_render_for_each_kind(): void
    {
        foreach (['suggested_food', 'manual_food', 'supplies'] as $kind) {
            $po = $this->order($kind);
            foreach (['program_project_activity', 'procurement_pack'] as $type) {
                $report = new Report(['type' => $type, 'parameters' => ['purchase_order_id' => $po->id]]);
                $pdf = app(ReportService::class)->buildPdf($report)['bytes'];
                $this->assertStringStartsWith('%PDF-', $pdf);
            }
        }
    }
}
