<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,              // 1. users
            FoodItemsSeeder::class,              // 2. food_items (USDA)
            ClinicalRulesSeeder::class,          // 3. clinical rules
            RecipeSeeder::class,                 // 4. NCP meal plan recipes (no Kapampangan recipes)
            InventorySeeder::class,              // 5. food ingredient inventory (₱/100g prices)
            FoodServiceRecipeSeeder::class,      // 6. FSS recipes (cost calculated from inventory)
            PatientSeeder::class,                // 7. demo NCP patients
            AnnouncementSeeder::class,           // 8. announcements
            ExtractionTemplateSeeder::class,     // 9. OCR templates
            ReportTemplateSeeder::class,         // 10. report templates
        ]);
    }
}
