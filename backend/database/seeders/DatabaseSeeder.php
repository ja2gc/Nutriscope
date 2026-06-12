<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,              // 1. users
            FoodItemsSeeder::class,              // 2. food_items (USDA / NCP library)
            ClinicalRulesSeeder::class,          // 3. clinical rules
            RecipeSeeder::class,                 // 4. NCP meal-plan recipes
            FsCatalogSeeder::class,              // 5. fs_items catalog (decoupled FS catalog)
            FoodServiceDemoSeeder::class,        // 6. FS operational demo (recipes/inventory/cycle/budget/POs)
            PatientSeeder::class,                // 7. demo NCP patients
            AnnouncementSeeder::class,           // 8. announcements
            ExtractionTemplateSeeder::class,     // 9. OCR templates
            ReportTemplateSeeder::class,         // 10. report templates
        ]);
    }
}
