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
                AiUsageLimitSeeder::class,           // 2. AI token caps (35k daily / 1M monthly)
                FoodItemsSeeder::class,              // 3. food_items (USDA / NCP library)
                ClinicalRulesSeeder::class,          // 4. clinical rules
                RecipeSeeder::class,                 // 5. NCP meal-plan recipes
                FsCatalogSeeder::class,              // 6. fs_items catalog (decoupled FS catalog)
                FoodServiceDemoSeeder::class,        // 7. FS operational demo (recipes/catalog/cycle/budget/POs)
                PatientSeeder::class,                // 8. demo NCP patients
                AnnouncementSeeder::class,           // 9. announcements
                NotificationSeeder::class,           // 10. role demo notifications
                SopSeeder::class,                    // 11. standard operating procedure + history
                ReportTemplateSeeder::class,         // 12. report templates
            ]);
        } finally {
            config()->set('audit.seeding.suppress_model_events', $previous);
        }
    }
}
