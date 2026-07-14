<?php

namespace App\Console\Commands;

use App\Models\FoodItem;
use App\Services\UsdaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BackfillWaterG extends Command
{
    protected $signature = 'food:backfill-water {--dry-run : Show what would be updated without saving}';

    protected $description = 'Backfill water_g on USDA food items and patch existing meal plan item snapshots.';

    public function handle(UsdaService $usda): int
    {
        $dry = $this->option('dry-run');

        // ── Step 1: Fetch water_g from USDA for items that are missing it ────────
        $missing = FoodItem::whereNotNull('usda_fdc_id')->whereNull('water_g')->get();
        $this->info("Step 1: {$missing->count()} USDA food items missing water_g.");

        foreach ($missing as $food) {
            try {
                // Bust cached response — it was stored before water_g extraction was added
                Cache::forget("usda_food_{$food->usda_fdc_id}");
                $data = $usda->fetch((int) $food->usda_fdc_id);
                $waterG = $data['water_g'] ?? null;

                if ($waterG === null) {
                    $this->line("  – {$food->name}: no water data in USDA");

                    continue;
                }

                $this->line("  ✓ {$food->name}: {$waterG} g");

                if (! $dry) {
                    $food->update(['water_g' => $waterG]);
                }

                usleep(300000); // 0.3 s — stay within USDA rate limit
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$food->name} (#{$food->usda_fdc_id}): {$e->getMessage()}");
                usleep(500000);
            }
        }

        // ── Step 2: Patch meal_plan_items snapshots for library-sourced items ───
        // Snapshots are frozen JSON — we update water_g in-place for items whose
        // parent food_item now has water_g, but the snapshot was saved before we
        // started tracking it.
        $this->info('Step 2: Patching meal plan item snapshots...');

        $rows = DB::table('meal_plan_items')
            ->join('food_items', 'meal_plan_items.food_item_id', '=', 'food_items.id')
            ->whereNotNull('food_items.water_g')
            ->whereNotNull('meal_plan_items.nutrient_snapshot')
            ->select('meal_plan_items.id', 'meal_plan_items.nutrient_snapshot', 'food_items.water_g')
            ->get();

        $patched = 0;
        foreach ($rows as $row) {
            $snap = json_decode($row->nutrient_snapshot, true);
            if (isset($snap['water_g'])) {
                continue;
            } // already has it

            $snap['water_g'] = (float) $row->water_g;

            if (! $dry) {
                DB::table('meal_plan_items')
                    ->where('id', $row->id)
                    ->update(['nutrient_snapshot' => json_encode($snap)]);
            }
            $patched++;
        }

        $this->info("  Patched {$patched} snapshots".($dry ? ' (dry run)' : '.'));

        return Command::SUCCESS;
    }
}
