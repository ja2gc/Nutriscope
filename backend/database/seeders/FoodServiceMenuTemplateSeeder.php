<?php

namespace Database\Seeders;

use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\MenuCycleTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FoodServiceMenuTemplateSeeder extends Seeder
{
    use ResolvesReferenceOwner;

    public function run(): void
    {
        $data = require database_path('seeders/data/food-service.php');
        $owner = $this->referenceOwner();
        $recipes = FoodServiceRecipe::query()->get()->keyBy('name');
        $items = FsItem::query()->get()->keyBy('name');
        foreach ($data['templates'] as $name => $days) {
            foreach ($days as $meals) {
                foreach ($meals as $lines) {
                    foreach ($lines as $itemName) {
                        if (! $recipes->has($itemName) && ! $items->has($itemName)) {
                            throw new RuntimeException("Template '{$name}' requires missing item '{$itemName}'.");
                        }
                    }
                }
            }
        }

        DB::transaction(function () use ($data, $owner, $recipes, $items): void {
            foreach ($data['templates'] as $name => $days) {
                $template = MenuCycleTemplate::query()->firstOrCreate(['name' => $name], [
                    'rnd_user_id' => $owner->id,
                    'cycle_days' => 7,
                    'description' => 'September rotation for breakfast, lunch and dinner. Review estimated recipe quantities for the planned population.',
                ]);
                if (! $template->wasRecentlyCreated) {
                    continue;
                }
                foreach ($days as $day => $meals) {
                    foreach (['breakfast', 'lunch', 'dinner'] as $index => $meal) {
                        foreach ($meals[$index] as $line => $itemName) {
                            $recipe = $recipes->get($itemName);
                            $item = $recipe ? null : $items->get($itemName);
                            $template->days()->create([
                                'day_of_week' => $day,
                                'meal_type' => $meal,
                                'line_order' => $line + 1,
                                'recipe_id' => $recipe?->id,
                                'fs_item_id' => $item?->id,
                                'quantity' => $data['portions'][$itemName] ?? 1,
                            ]);
                        }
                    }
                }
            }
        });
    }
}
