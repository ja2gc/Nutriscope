<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $previous = config('audit.seeding.suppress_model_events', false);
        config()->set('audit.seeding.suppress_model_events', true);

        try {
            DB::transaction(fn () => $this->call([
                AdminUserSeeder::class,
                AiUsageLimitSeeder::class,
                FoodItemsSeeder::class,
                ClinicalRulesSeeder::class,
                RecipeSeeder::class,
                FoodItemPricesSeeder::class,
                InterventionMealPlanTemplateSeeder::class,
                FsCatalogSeeder::class,
                FoodServiceRecipeSeeder::class,
                FoodServiceMenuTemplateSeeder::class,
                ReportTemplateSeeder::class,
            ]));
        } finally {
            config()->set('audit.seeding.suppress_model_events', $previous);
        }
    }
}
