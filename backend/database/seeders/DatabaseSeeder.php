<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,    // users first (recipes depend on RND user)
            FoodItemsSeeder::class,    // food_items (recipes depend on this)
            InventorySeeder::class,    // inventory levels
            ClinicalRulesSeeder::class,// clinical_rules (RecommendService depends on this)
            RecipeSeeder::class,       // recipes + recipe_ingredients (MealPlanService depends on >=15)
            AnnouncementSeeder::class, // dashboard announcement feed seed posts
            ExtractionTemplateSeeder::class, // OCR extraction templates
            ReportTemplateSeeder::class, // Report templates for PDFs
        ]);

    }
}
