<?php

namespace Tests\Feature;

use App\Models\DietListCount;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Models\ShoppingList;
use Carbon\CarbonImmutable;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\FoodServiceDemoSeeder;
use Database\Seeders\FoodServiceOctoberBackfillSeeder;
use Database\Seeders\FsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FoodServiceOctoberBackfillSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_backfill_is_additive_repeatable_and_leaves_october_15_for_recording(): void
    {
        Storage::fake((string) config('filesystems.private_uploads'));
        CarbonImmutable::setTestNow('2026-09-21 12:00:00 Asia/Manila');
        $this->seed(AdminUserSeeder::class);
        $this->seed(FsCatalogSeeder::class);
        $this->seed(FoodServiceDemoSeeder::class);

        $existingMenu = MenuCycle::query()->whereDate('week_start_date', '2026-09-21')->sole();
        $existingPoIds = PurchaseOrder::query()->pluck('id')->all();
        $legacyReports = Report::query()->whereNull('review_state')->get()->mapWithKeys(fn (Report $report) => [
            $report->id => [$report->getRawOriginal('updated_at'), $report->cache_path, $report->parameters, $report->review_state],
        ])->all();
        $this->assertSame('2026-09-14', MenuCycle::query()->orderBy('week_start_date')->firstOrFail()->week_start_date->toDateString());
        $this->assertSame('2026-09-14', MenuCycle::coveringDate('2026-09-20')?->week_start_date->toDateString());
        CarbonImmutable::setTestNow('2026-10-14 12:00:00 Asia/Manila');

        $this->seed(FoodServiceOctoberBackfillSeeder::class);
        $first = [
            MenuCycle::count(), ShoppingList::count(), PurchaseOrder::count(),
            MealPrepLog::count(), DietListCount::count(), Report::count(),
        ];
        $this->seed(FoodServiceOctoberBackfillSeeder::class);

        $this->assertSame(0, ShoppingList::where('name', 'like', 'Demo Marketing%')->count());
        $this->assertGreaterThan(0, ShoppingList::where('name', 'like', 'Marketing%')->count());

        $this->assertSame($first, [
            MenuCycle::count(), ShoppingList::count(), PurchaseOrder::count(),
            MealPrepLog::count(), DietListCount::count(), Report::count(),
        ]);
        $this->assertModelExists($existingMenu);
        foreach ($existingPoIds as $id) {
            $this->assertTrue(PurchaseOrder::query()->whereKey($id)->exists());
        }
        foreach ($legacyReports as $id => $before) {
            $report = Report::query()->findOrFail($id);
            $this->assertSame($before, [$report->getRawOriginal('updated_at'), $report->cache_path, $report->parameters, $report->review_state]);
        }
        $this->assertGreaterThan(0, MenuCycle::query()->whereDate('week_start_date', '2026-10-12')->sole()->days()->count());
        $this->assertSame(1, DietListCount::query()->whereDate('service_date', '2026-09-15')->count());
        $this->assertSame(1, DietListCount::query()->whereDate('service_date', '2026-10-14')->count());
        $this->assertSame(0, DietListCount::query()->whereDate('service_date', '2026-10-15')->count());
        $currentPo = PurchaseOrder::query()->whereHas('shoppingList', fn ($query) => $query->whereDate('period_start', '2026-10-13'))->sole();
        $this->assertSame('open_execution', $currentPo->lifecycle_status);
        $this->assertSame(1, $currentPo->vendorGroups()->where('status', 'pending')->count());
        $this->assertSame(0, PurchaseOrder::query()->whereHas('shoppingList', fn ($query) => $query->whereDate('period_end', '<', '2026-10-13'))->where('lifecycle_status', 'open_execution')->count());
    }
}
