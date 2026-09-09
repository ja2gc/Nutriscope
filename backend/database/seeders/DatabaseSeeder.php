<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Base/demo model persistence is setup state, not user activity. Suppress
        // only automatic model audit events so UUID/model hooks still run and
        // explicit named system events from real domain flows remain available.
        $previous = config('audit.seeding.suppress_model_events', false);
        config()->set('audit.seeding.suppress_model_events', true);

        try {
            $this->call([
                AdminUserSeeder::class,              // 1. users
                ProfilePhotoSeeder::class,            // 2. private demo profile photos
                AiUsageLimitSeeder::class,           // 3. AI token caps (35k daily / 1M monthly)
                FoodItemsSeeder::class,              // 4. food_items (USDA / NCP library)
                ClinicalRulesSeeder::class,          // 5. clinical rules
                RecipeSeeder::class,                 // 6. NCP meal-plan recipes
                FoodItemPricesSeeder::class,         // 7. deterministic prices and recipe costs
                InterventionMealPlanTemplateSeeder::class, // 8. reusable clinical goal templates
                FsCatalogSeeder::class,              // 9. fs_items catalog (decoupled FS catalog)
                FoodServiceDemoSeeder::class,        // 10. FS operational demo (recipes/catalog/cycle/budget/POs)
                FoodServiceMenuTemplateSeeder::class, // 11. reusable seven-day FS templates
                PatientSeeder::class,                // 12. demo NCP patients
                AnnouncementSeeder::class,           // 13. announcements
                NotificationSeeder::class,           // 14. role demo notifications
                SopSeeder::class,                    // 15. standard operating procedure + history
                ReportTemplateSeeder::class,         // 16. report templates
            ]);
        } finally {
            config()->set('audit.seeding.suppress_model_events', $previous);
        }
    }
}
