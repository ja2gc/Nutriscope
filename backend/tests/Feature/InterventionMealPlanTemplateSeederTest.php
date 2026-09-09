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

    public function test_it_seeds_two_complete_variants_per_non_custom_goal_without_removing_manual_templates(): void
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
        $this->assertCount(16, $secondIds);
        $this->assertTrue(MealPlanTemplate::query()->whereKey($manual->id)->exists());

        $templates = MealPlanTemplate::query()
            ->where('name', 'like', 'Demo — %')
            ->with('days.items')
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
        }

        $this->assertCount(2, $templates->where('goal_type', 'liver_disease'));
        $this->assertTrue($templates->where('goal_type', 'liver_disease')->every(
            fn ($template): bool => ! str_contains($template->name, 'No Snacks')
                && str_contains(strtolower($template->description), 'frequent')
        ));
    }
}
