<?php

namespace Tests\Feature;

use App\Models\MealPlanTemplate;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\FoodItemsSeeder;
use Database\Seeders\InterventionMealPlanTemplateSeeder;
use Database\Seeders\RecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterventionMealPlanTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_goal_specific_custom_and_maternal_templates_with_separate_carb_items(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(FoodItemsSeeder::class);
        $this->seed(RecipeSeeder::class);

        $rnd = User::query()->where('role', 'RND')->firstOrFail();
        $manual = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $rnd->id,
            'name' => 'My manual template',
            'goal_type' => 'custom',
        ]);

        $this->seed(InterventionMealPlanTemplateSeeder::class);
        $firstIds = MealPlanTemplate::query()->where('name', 'like', 'Demo — %')->orderBy('name')->pluck('id')->all();
        $this->seed(InterventionMealPlanTemplateSeeder::class);
        $secondIds = MealPlanTemplate::query()->where('name', 'like', 'Demo — %')->orderBy('name')->pluck('id')->all();

        $this->assertSame($firstIds, $secondIds);
        $this->assertCount(22, $secondIds);
        $this->assertTrue(MealPlanTemplate::query()->whereKey($manual->id)->exists());

        $templates = MealPlanTemplate::query()
            ->where('name', 'like', 'Demo — %')
            ->with(['days.items.foodItem', 'days.items.recipe'])
            ->get();

        foreach ($templates as $template) {
            $this->assertCount(35, $template->days, $template->name);
            $this->assertTrue($template->days->every(
                fn ($day): bool => $day->items->every(fn ($item): bool => $item->nutrient_snapshot !== null)
            ), $template->name);

            $snackItems = $template->days
                ->whereIn('meal_type', ['am_snack', 'pm_snack'])
                ->sum(fn ($day): int => $day->items->count());

            if (str_contains($template->name, 'No Snacks')) {
                $this->assertSame(0, $snackItems, $template->name);
            } else {
                $this->assertGreaterThan(0, $snackItems, $template->name);
            }

            foreach ($template->days->whereIn('meal_type', ['lunch', 'dinner']) as $mainMeal) {
                $carbItems = $mainMeal->items->filter(fn ($item): bool => $item->foodItem?->category === 'carbs');
                $this->assertLessThanOrEqual(1, $carbItems->count(), $template->name.' '.$mainMeal->day_of_week.' '.$mainMeal->meal_type);
                $this->assertTrue($carbItems->every(fn ($item): bool => $item->recipe_id === null));
            }
        }

        $this->assertDatabaseMissing('recipes', ['name' => 'Plain White Rice Meal']);
        $this->assertDatabaseMissing('recipes', ['name' => 'Plain Brown Rice Meal']);
        $this->assertTrue($templates->flatMap->days->flatMap->items->contains(
            fn ($item): bool => $item->foodItem?->category === 'carbs'
                && ! str_contains(strtolower($item->foodItem->name), 'rice')
        ));

        $this->assertCount(2, $templates->where('goal_type', 'liver_disease'));
        $this->assertTrue($templates->where('goal_type', 'liver_disease')->every(
            fn ($template): bool => ! str_contains($template->name, 'No Snacks')
                && str_contains(strtolower($template->description), 'frequent')
        ));

        $maternalTemplates = $templates->whereNotNull('maternal_status')->values();
        $this->assertCount(4, $maternalTemplates);
        $this->assertCount(2, $maternalTemplates->where('maternal_status', 'pregnant'));
        $this->assertCount(2, $maternalTemplates->where('maternal_status', 'lactating'));
        $this->assertCount(2, $maternalTemplates->filter(
            fn ($template): bool => str_contains($template->name, 'No Snacks')
        ));

        foreach ($maternalTemplates as $maternal) {
            $this->assertNull($maternal->goal_type);
            $this->assertStringContainsString('Maternal', $maternal->name);
            $this->assertStringContainsString('NNC/DOH', $maternal->description);
            $this->assertTrue($maternal->days->whereIn('meal_type', ['lunch', 'dinner'])->every(
                fn ($day): bool => $day->items->contains(fn ($item): bool => $item->foodItem?->category === 'vegetable')
            ));

            foreach ($maternal->days->groupBy('day_of_week') as $dayItems) {
                $items = $dayItems->flatMap->items;
                $requiredMilkServings = $maternal->maternal_status === 'lactating' ? 2 : 1;

                $this->assertGreaterThanOrEqual(
                    $requiredMilkServings,
                    $items->filter(fn ($item): bool => $item->foodItem?->name === 'Low-fat Milk (1%)')->count(),
                    $maternal->name.' '.$dayItems->first()->day_of_week.' needs its maternal milk servings.'
                );
                $this->assertTrue(
                    $items->contains(fn ($item): bool => $item->foodItem?->category === 'fruit'),
                    $maternal->name.' '.$dayItems->first()->day_of_week.' needs fruit.'
                );
            }
        }

        $customTemplates = $templates->where('goal_type', 'custom');
        $this->assertCount(2, $customTemplates);
        $this->assertTrue($customTemplates->every(
            fn ($custom): bool => $custom->days->whereIn('meal_type', ['lunch', 'dinner'])->every(
                fn ($day): bool => $day->items->contains(fn ($item): bool => $item->foodItem?->category === 'vegetable')
            )
        ));
    }
}
