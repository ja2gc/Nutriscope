<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Models\ShoppingList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FoodServiceReportNamingMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_forward_correction_preserves_history_dates_custom_names_and_archive_bytes_and_is_repeatable(): void
    {
        Storage::fake('report_cache');
        $bytes = "%PDF-1.4\nfictional archived report\n%%EOF";
        Storage::disk('report_cache')->put('original.pdf', $bytes);
        $list = ShoppingList::factory()->create([
            'name' => 'Demo Marketing — Oct 9–Oct 12, 2026', 'list_type' => 'suggested',
            'procurement_track' => 'food', 'period_start' => '2026-10-09', 'period_end' => '2026-10-12',
        ]);
        $custom = ShoppingList::factory()->create(['name' => 'Demo Marketing fundraiser']);
        $po = PurchaseOrder::factory()->create(['shopping_list_id' => $list->id, 'po_number' => 'PO-2026-0042']);
        $pack = Report::factory()->create([
            'type' => 'procurement_pack', 'title' => 'PO — Demo Marketing — Oct 9–Oct 12, 2026',
            'parameters' => ['purchase_order_id' => $po->id], 'status' => 'archived', 'cache_path' => 'original.pdf',
            'created_at' => '2026-10-12 08:00:00', 'updated_at' => '2026-10-12 08:00:00',
        ]);
        $ppa = Report::factory()->create(['type' => 'program_project_activity', 'title' => 'PO — Sep 18, 2026', 'parameters' => ['purchase_order_id' => $po->id]]);
        $generic = Report::factory()->create(['type' => 'procurement_pack', 'title' => 'Procurement Pack', 'parameters' => ['purchase_order_id' => $po->id]]);
        $orphan = Report::factory()->create(['type' => 'program_project_activity', 'title' => 'Program Project Activity (PPA)', 'parameters' => ['purchase_order_id' => 999999]]);
        $customReport = Report::factory()->create(['type' => 'procurement_pack', 'title' => 'Board approved pack']);
        $other = Report::factory()->create(['type' => 'inspection_report', 'title' => $pack->title]);
        $beforeList = $list->fresh()->getRawOriginal();
        $beforePo = $po->fresh()->getRawOriginal();
        $beforeReports = Report::all()->mapWithKeys(fn ($report) => [$report->id => $report->getRawOriginal()])->all();
        $auditCount = DB::table('activity_log')->count();
        $migration = require database_path('migrations/2026_10_16_062200_normalize_food_service_report_names.php');
        $migration->up();

        $this->assertSame('Marketing — Oct 9–Oct 12, 2026', $list->fresh()->name);
        $this->assertSame('Demo Marketing fundraiser', $custom->fresh()->name);
        $this->assertSame('PP — Marketing — Oct 9–Oct 12, 2026', $pack->fresh()->title);
        $this->assertSame('PPA — Sep 18, 2026', $ppa->fresh()->title);
        $this->assertSame('PP — Marketing — Oct 9–Oct 12, 2026', $generic->fresh()->title);
        $this->assertSame('PPA', $orphan->fresh()->title);
        $this->assertSame('Board approved pack', $customReport->fresh()->title);
        $this->assertSame($beforeReports[$other->id]['title'], $other->fresh()->title);
        $this->assertSame($beforePo, $po->fresh()->getRawOriginal());
        $this->assertSame(Arr::except($beforeList, ['name']), Arr::except($list->fresh()->getRawOriginal(), ['name']));
        foreach (Report::all() as $report) {
            $this->assertSame(Arr::except($beforeReports[$report->id], ['title']), Arr::except($report->getRawOriginal(), ['title']));
        }
        $first = Report::all()->map->getRawOriginal()->all();
        $migration->up();
        $this->assertSame($first, Report::all()->map->getRawOriginal()->all());
        $this->assertSame($bytes, Storage::disk('report_cache')->get('original.pdf'));
        $this->assertSame($auditCount, DB::table('activity_log')->count());
        $this->assertDatabaseCount('reports', count($beforeReports));
    }
}
