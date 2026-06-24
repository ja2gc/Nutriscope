<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,              // 1. users
            AiUsageLimitSeeder::class,           // 2. AI token caps (35k daily / 1M monthly)
            FoodItemsSeeder::class,              // 3. food_items (USDA / NCP library)
            ClinicalRulesSeeder::class,          // 4. clinical rules
            RecipeSeeder::class,                 // 5. NCP meal-plan recipes
            FsCatalogSeeder::class,              // 6. fs_items catalog (decoupled FS catalog)
            FoodServiceDemoSeeder::class,        // 7. FS operational demo (recipes/inventory/cycle/budget/POs)
            PatientSeeder::class,                // 8. demo NCP patients
            AnnouncementSeeder::class,           // 9. announcements
            SopSeeder::class,                    // 10. standard operating procedure + history
            ReportTemplateSeeder::class,         // 11. report templates
        ]);
    }
}
