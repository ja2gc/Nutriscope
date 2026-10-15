<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealPlanTemplate;
use App\Models\MealPlanTemplateDay;
use App\Models\MealPlanTemplateItem;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NutritionMenuTaxonomyMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        $schema = Schema::connection('sqlite');
        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });
        $schema->create('food_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('usda_fdc_id')->nullable();
            $table->decimal('calories', 8, 2)->default(0);
            $table->decimal('protein', 8, 2)->default(0);
            $table->decimal('carbs', 8, 2)->default(0);
            $table->decimal('fat', 8, 2)->default(0);
            $table->float('water_g')->nullable();
            $table->json('micronutrients')->nullable();
            $table->json('allergens')->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->string('serving_unit')->default('g');
            $table->decimal('serving_size', 8, 2)->default(100);
            $table->timestamps();
        });
        $schema->create('recipes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('rnd_user_id');
            $table->string('name');
            $table->text('prep_notes')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('total_calories', 8, 2)->default(0);
            $table->decimal('total_protein', 8, 2)->default(0);
            $table->decimal('total_carbs', 8, 2)->default(0);
            $table->decimal('total_fat', 8, 2)->default(0);
            $table->decimal('total_water_g', 8, 2)->default(0);
            $table->json('micronutrients')->nullable();
            $table->string('category')->nullable();
            $table->json('meal_types')->nullable();
            $table->string('component_type')->nullable();
            $table->unsignedInteger('servings')->default(1);
            $table->decimal('prepared_portion_amount', 8, 2)->nullable();
            $table->string('prepared_portion_unit')->nullable();
            $table->timestamps();
        });
        $schema->create('recipe_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedBigInteger('food_item_id');
            $table->decimal('quantity', 8, 2);
            $table->string('unit')->default('g');
        });
        $schema->create('meal_plan_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('rnd_user_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('goal_type')->nullable();
            $table->string('disease_stage')->nullable();
            $table->string('maternal_status')->nullable();
            $table->timestamps();
        });
        $schema->create('meal_plan_template_days', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->string('day_of_week');
            $table->string('meal_type');
            $table->unsignedBigInteger('food_item_id')->nullable();
            $table->unsignedBigInteger('recipe_id')->nullable();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->default('serving');
            $table->timestamps();
        });
        $schema->create('meal_plan_template_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('template_day_id');
            $table->unsignedBigInteger('food_item_id')->nullable();
            $table->unsignedBigInteger('recipe_id')->nullable();
            $table->string('fdc_id', 20)->nullable();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->default('serving');
            $table->json('nutrient_snapshot')->nullable();
            $table->boolean('ai_suggested')->default(false);
            $table->unsignedSmallInteger('line_order')->default(1);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_it_migrates_verified_legacy_categories_and_template_items_idempotently(): void
    {
        $migrationFiles = glob(database_path('migrations/2026_10_15_*_normalize_nutrition_care_menu_templates.php'));
        $this->assertCount(1, $migrationFiles, 'The forward taxonomy and template migration must exist.');

        $rnd = User::factory()->rnd()->create();
        $legacyCategories = [
            'Plain White Rice Meal' => 'Staple',
            'Boiled Kamote (Sweet Potato)' => 'Snack',
            'Fresh Mango Serving' => 'Snack',
            'Mixed Vegetable Plate' => 'Vegetarian',
            'Ginisang Sayote (Sautéed Chayote)' => 'Vegetable',
            'Boiled Chicken Breast' => 'High Protein',
            'Manual Meal Item' => 'meal',
        ];

        foreach ($legacyCategories as $name => $category) {
            Recipe::forceCreate([
                'rnd_user_id' => $rnd->id,
                'name' => $name,
                'category' => $category,
                'servings' => 1,
            ]);
        }

        $food = FoodItem::factory()->create([
            'name' => 'Papaya (Raw)',
            'category' => 'fruit',
            'usda_fdc_id' => 'TEST-FDC-1',
            'serving_size' => 100,
            'serving_unit' => 'g',
            'calories' => 40,
            'protein' => 0.5,
            'carbs' => 10,
            'fat' => 0.2,
            'water_g' => 80,
            'micronutrients' => ['vitamin_c' => 12],
        ]);
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $rnd->id,
            'name' => 'Legacy template',
        ]);
        $directDay = MealPlanTemplateDay::forceCreate([
            'template_id' => $template->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'am_snack',
            'food_item_id' => $food->id,
            'quantity' => 120,
            'unit' => 'g',
        ]);
        $directSnapshot = [
            'name' => $food->name,
            'calories' => 40,
            'serving_size' => 100,
            'serving_unit' => 'g',
            'source' => 'food_item',
        ];
        $directItem = MealPlanTemplateItem::forceCreate([
            'template_day_id' => $directDay->id,
            'food_item_id' => $food->id,
            'quantity' => 120,
            'unit' => 'g',
            'nutrient_snapshot' => $directSnapshot,
            'line_order' => 2,
        ]);

        $recipe = Recipe::query()->create([
            'rnd_user_id' => $rnd->id,
            'name' => 'Bulanglang (Vegetable Soup)',
            'category' => 'Vegetable',
            'servings' => 1,
            'prepared_portion_amount' => 1.5,
            'prepared_portion_unit' => 'cup',
        ]);
        $recipeDay = MealPlanTemplateDay::forceCreate([
            'template_id' => $template->id,
            'day_of_week' => 'Tuesday',
            'meal_type' => 'lunch',
            'recipe_id' => $recipe->id,
            'quantity' => 2,
            'unit' => 'cup',
        ]);
        $recipeSnapshot = ['name' => $recipe->name, 'calories' => 120, 'source' => 'recipe'];
        $recipeItem = MealPlanTemplateItem::forceCreate([
            'template_day_id' => $recipeDay->id,
            'recipe_id' => $recipe->id,
            'quantity' => 2,
            'unit' => 'cup',
            'nutrient_snapshot' => $recipeSnapshot,
            'line_order' => 3,
        ]);
        $itemCount = MealPlanTemplateItem::query()->count();
        $recipeCount = Recipe::query()->count();

        $migration = require $migrationFiles[0];
        $migration->up();

        $expectedCategories = [
            'Plain White Rice Meal' => 'carb',
            'Boiled Kamote (Sweet Potato)' => 'carb',
            'Fresh Mango Serving' => 'snack',
            'Mixed Vegetable Plate' => 'meal',
            'Ginisang Sayote (Sautéed Chayote)' => 'meal',
            'Boiled Chicken Breast' => 'meal',
            'Manual Meal Item' => 'meal',
        ];
        foreach ($expectedCategories as $name => $category) {
            $this->assertSame($category, Recipe::query()->where('name', $name)->value('category'));
        }

        $wrapper = Recipe::query()->where('rnd_user_id', $rnd->id)->where('name', $food->name)->firstOrFail();
        $wrapper->load('ingredients');
        $this->assertSame('snack', $wrapper->category);
        $this->assertSame('40.00', $wrapper->total_calories);
        $this->assertSame('100.00', $wrapper->prepared_portion_amount);
        $this->assertSame('g', $wrapper->prepared_portion_unit);
        $this->assertCount(1, $wrapper->ingredients);
        $this->assertSame($food->id, $wrapper->ingredients->first()->food_item_id);
        $this->assertSame('100.000000', $wrapper->ingredients->first()->quantity);
        $this->assertSame('g', $wrapper->ingredients->first()->unit);

        $directItem = $directItem->fresh();
        $this->assertNull($directDay->fresh()->food_item_id);
        $this->assertSame($wrapper->id, $directDay->fresh()->recipe_id);
        $this->assertSame('1.20', $directDay->fresh()->quantity);
        $this->assertSame('serving', $directDay->fresh()->unit);
        $this->assertNull($directItem->food_item_id);
        $this->assertSame($wrapper->id, $directItem->recipe_id);
        $this->assertSame('1.20', $directItem->quantity);
        $this->assertSame('serving', $directItem->unit);
        $this->assertSame($directSnapshot, $directItem->nutrient_snapshot);
        $this->assertSame(2, $directItem->line_order);

        $recipeItem = $recipeItem->fresh();
        $this->assertSame($recipe->id, $recipeDay->fresh()->recipe_id);
        $this->assertSame('1.33', $recipeDay->fresh()->quantity);
        $this->assertSame('serving', $recipeDay->fresh()->unit);
        $this->assertSame('1.33', $recipeItem->quantity);
        $this->assertSame('serving', $recipeItem->unit);
        $this->assertSame($recipeSnapshot, $recipeItem->nutrient_snapshot);
        $this->assertSame(3, $recipeItem->line_order);
        $this->assertSame($itemCount, MealPlanTemplateItem::query()->count());
        $this->assertSame($recipeCount + 1, Recipe::query()->count());

        $migration->up();

        $this->assertSame($recipeCount + 1, Recipe::query()->count());
        $this->assertSame('1.20', $directItem->fresh()->quantity);
        $this->assertSame('1.33', $recipeItem->fresh()->quantity);
    }

    public function test_unknown_legacy_category_stops_migration_before_any_category_change(): void
    {
        $migrationFiles = glob(database_path('migrations/2026_10_15_*_normalize_nutrition_care_menu_templates.php'));
        $this->assertCount(1, $migrationFiles, 'The forward taxonomy and template migration must exist.');

        $rnd = User::factory()->rnd()->create();
        $rice = Recipe::forceCreate([
            'rnd_user_id' => $rnd->id,
            'name' => 'Plain White Rice Meal',
            'category' => 'Staple',
            'servings' => 1,
        ]);
        Recipe::forceCreate([
            'rnd_user_id' => $rnd->id,
            'name' => 'Unknown custom recipe',
            'category' => 'Unresolved Legacy Label',
            'servings' => 1,
        ]);
        $migration = require $migrationFiles[0];

        try {
            $migration->up();
            $this->fail('Expected migration to reject an unverified legacy category.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Unresolved Legacy Label', $exception->getMessage());
        }

        $this->assertSame('Staple', $rice->fresh()->category);
    }

    public function test_verified_source_correction_refreshes_library_nutrients_and_preserves_history(): void
    {
        $rnd = User::factory()->rnd()->create();
        $food = FoodItem::factory()->create([
            'name' => 'Coconut Milk (Canned)', 'usda_fdc_id' => '175194',
            'serving_size' => 100, 'serving_unit' => 'g',
            'calories' => 127, 'protein' => 8.67, 'carbs' => 22.8, 'fat' => 0.5,
        ]);
        $recipe = Recipe::forceCreate([
            'rnd_user_id' => $rnd->id, 'name' => 'Ginataan (Coconut Fruit Dessert)',
            'category' => 'snack', 'servings' => 1, 'total_calories' => 63.5,
        ]);
        $ingredient = $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 50, 'unit' => 'mL']);
        $template = MealPlanTemplate::forceCreate(['rnd_user_id' => $rnd->id, 'name' => 'Reusable template']);
        $day = MealPlanTemplateDay::forceCreate(['template_id' => $template->id, 'day_of_week' => 'Monday', 'meal_type' => 'am_snack']);
        $line = MealPlanTemplateItem::forceCreate([
            'template_day_id' => $day->id, 'recipe_id' => $recipe->id, 'quantity' => 2,
            'unit' => 'serving', 'nutrient_snapshot' => ['name' => $recipe->name, 'calories' => 63.5], 'line_order' => 3,
        ]);
        Schema::create('meal_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->json('nutrient_snapshot');
        });
        $history = json_encode(['name' => 'Historical serving', 'calories' => 63.5]);
        DB::table('meal_plan_items')->insert(['nutrient_snapshot' => $history]);

        $migration = require database_path('migrations/2026_10_15_154503_correct_verified_clinical_catalog_sources.php');
        $migration->up();

        $this->assertSame('170173', (string) $food->fresh()->usda_fdc_id);
        $this->assertEquals(197, $food->fresh()->calories);
        $this->assertEquals(47.762355, (float) $ingredient->fresh()->quantity);
        $this->assertSame('g', $ingredient->fresh()->unit);
        $this->assertEquals(94.09, $recipe->fresh()->total_calories);
        $this->assertEquals(94.09, $line->fresh()->nutrient_snapshot['calories']);
        $this->assertSame('2.00', $line->fresh()->quantity);
        $this->assertSame(3, $line->fresh()->line_order);
        $this->assertSame($history, DB::table('meal_plan_items')->value('nutrient_snapshot'));

        $migration->up();
        $this->assertSame(1, FoodItem::query()->count());
        $this->assertSame(1, Recipe::query()->count());
        $this->assertEquals(47.762355, (float) $ingredient->fresh()->quantity);
    }

    public function test_unexpected_legacy_measure_stops_source_correction_before_any_write(): void
    {
        $rnd = User::factory()->rnd()->create();
        $food = FoodItem::factory()->create([
            'name' => 'Coconut Milk (Canned)', 'usda_fdc_id' => '175194',
            'serving_size' => 100, 'serving_unit' => 'g', 'calories' => 127, 'protein' => 8.67, 'carbs' => 22.8, 'fat' => 0.5,
        ]);
        $recipe = Recipe::forceCreate(['rnd_user_id' => $rnd->id, 'name' => 'Ginataan (Coconut Fruit Dessert)', 'category' => 'snack', 'servings' => 1]);
        $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 51, 'unit' => 'mL']);
        $migration = require database_path('migrations/2026_10_15_154503_correct_verified_clinical_catalog_sources.php');
        try {
            $migration->up();
            $this->fail('Unverified legacy amounts must block the data migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('measured', $exception->getMessage());
        }
        $this->assertSame('175194', (string) $food->fresh()->usda_fdc_id);
    }

    public function test_retained_source_correction_matches_the_approved_offline_catalog(): void
    {
        $migration = require database_path('migrations/2026_10_15_154503_correct_verified_clinical_catalog_sources.php');
        $corrections = (new \ReflectionClass($migration))->getConstant('SOURCE_CORRECTIONS');
        $this->assertIsArray($corrections);
        $this->assertCount(6, $corrections);
        $catalog = json_decode(file_get_contents(database_path('seeders/data/clinical-foods.json')), true, flags: JSON_THROW_ON_ERROR);
        $foods = array_column($catalog['foods'], null, 'name');
        foreach ($corrections as $name => $correction) {
            foreach (['usda_fdc_id', 'calories', 'protein', 'carbs', 'fat', 'water_g', 'micronutrients'] as $field) {
                $this->assertEquals($foods[$name][$field], $correction['new'][$field], $name.' '.$field);
            }
        }
    }
}
