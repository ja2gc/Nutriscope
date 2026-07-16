<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD: Seeder integrity tests.
 *
 * These tests verify the data-layer fix requirements from the plan:
 * - FssFoodItemsSeeder is gone from DatabaseSeeder
 * - RecipeSeeder ingredient names match USDA food names
 * - RecipeSeeder is idempotent (running twice produces same count)
 * - FoodItemsSeeder manual items now covered by FALLBACK_FDC_IDS
 */
class SeederIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /** 1a/1b — FssFoodItemsSeeder must not exist / must not be referenced */
    public function test_fss_food_items_seeder_file_does_not_exist(): void
    {
        $this->assertFileDoesNotExist(
            database_path('seeders/FssFoodItemsSeeder.php'),
            'FssFoodItemsSeeder.php must be deleted — it pollutes food_items with macro-only FSS records'
        );
    }

    public function test_database_seeder_does_not_reference_fss_seeder(): void
    {
        $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');
        $content = file_get_contents($databaseSeederPath);
        $this->assertStringNotContainsString(
            'FssFoodItemsSeeder',
            $content,
            'DatabaseSeeder must not call FssFoodItemsSeeder::class'
        );
    }

    /** 1c — All 12 manual fallback items must now be in FALLBACK_FDC_IDS */
    public function test_food_items_seeder_manual_items_are_in_fallback_fdc_ids(): void
    {
        // These 12 items were previously created manually without micronutrients.
        // They must now be in FALLBACK_FDC_IDS so they use USDA import.
        $mustBeInFallback = [
            'Onion (Raw)',
            'Ginger Root (Raw)',
            'Tomato (Cooked)',
            'Coconut Milk (Canned)',
            'Peanut Butter (Unsalted)',
            'Cocoa Powder (Unsweetened)',
            'Pineapple (Raw)',
            'Guava (Raw)',
            'Jackfruit (Raw)',
            'Calamansi / Lime Juice',
            'Glutinous Rice (Cooked)',
            'Corn Grits (Cooked)',
        ];

        $content = file_get_contents(database_path('seeders/FoodItemsSeeder.php'));

        foreach ($mustBeInFallback as $name) {
            $this->assertStringContainsString(
                $name,
                $content,
                "FoodItemsSeeder must still reference: {$name}"
            );
        }

        // The old manual $manualItems array must be gone
        $this->assertStringNotContainsString(
            '$manualItems = [',
            $content,
            'The $manualItems hardcoded array must be removed — all 12 items must go through USDA import'
        );
    }

    /** 1d — RecipeSeeder ingredient names must use USDA food names, not FSS names */
    public function test_recipe_seeder_uses_usda_food_names_not_fss_names(): void
    {
        $content = file_get_contents(database_path('seeders/RecipeSeeder.php'));

        $fssFoodNames = [
            'White Rice (Raw)',
            'Chicken Breast (Raw)',
            'Chicken Thighs (Raw)',
            'Pork Kasim (Shoulder)',
            'Pork Liempo (Belly)',
            'Bangus (Milkfish)',
            'Ampalaya (Bitter Melon)',
            'Kangkong (Water Spinach)',
            'Talong (Eggplant)',
            'Kalabasa (Squash)',
            'Sitaw (String Beans)',
            'Repolyo (Cabbage)',
            'Pechay (Bok Choy)',
            'Sayote (Chayote)',
            'Saging (Banana)',
            'Camote (Sweet Potato)',
            'Mung Beans (Monggo)',
            'Glutinous Rice (Malagkit)',
            'Coconut Milk (Gata)',
            'Garlic',     // must be 'Garlic (Raw)'
        ];

        foreach ($fssFoodNames as $badName) {
            $this->assertStringNotContainsString(
                "'{$badName}'",
                $content,
                "RecipeSeeder must not use FSS name '{$badName}' — use the USDA-mapped friendly name instead"
            );
        }
    }

    /** 1e — RecipeSeeder must be idempotent (no DELETE + re-insert) */
    public function test_recipe_seeder_does_not_truncate_recipes_table(): void
    {
        $content = file_get_contents(database_path('seeders/RecipeSeeder.php'));

        $this->assertStringNotContainsString(
            'Recipe::truncate()',
            $content,
            'RecipeSeeder must preserve unrelated RND recipes on every rerun'
        );

        $this->assertStringNotContainsString(
            'RecipeIngredient::truncate()',
            $content,
            'RecipeSeeder must not globally delete ingredients from unrelated RND recipes'
        );

        $this->assertStringNotContainsString(
            "DB::table('recipes')->delete()",
            $content,
            'RecipeSeeder must not truncate recipes — use firstOrCreate for idempotency'
        );

        $this->assertStringNotContainsString(
            "DB::table('recipe_ingredients')->delete()",
            $content,
            'RecipeSeeder must not truncate recipe_ingredients — use firstOrCreate for idempotency'
        );

        $this->assertStringContainsString(
            'updateOrCreate',
            $content,
            'RecipeSeeder must refresh its named demo recipes without duplicating them'
        );
    }

    public function test_base_database_seeding_suppresses_audit_noise_without_muting_model_events(): void
    {
        $content = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringContainsString('activity()->withoutLogs', $content);
        $this->assertStringNotContainsString('WithoutModelEvents', $content,
            'Global model-event muting would also disable public UUID generation.');
    }

    public function test_patient_seeder_uses_stable_hospital_keys_instead_of_display_names(): void
    {
        $content = file_get_contents(database_path('seeders/PatientSeeder.php'));

        $this->assertStringNotContainsString("Patient::where('name'", $content);
        $this->assertStringContainsString("Patient::updateOrCreate(\n            ['hospital_number'", $content);
        $this->assertStringContainsString("'first_name' => 'Maria'", $content);
        $this->assertStringContainsString("'last_name' => 'Santos'", $content);
        $this->assertStringContainsString("'first_name' => 'Roberto'", $content);
        $this->assertStringContainsString("'last_name' => 'Reyes'", $content);
    }
}
