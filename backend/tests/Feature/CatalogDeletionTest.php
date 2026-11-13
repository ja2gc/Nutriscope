<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\MealPlanTemplate;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\MenuCycleTemplate;
use App\Models\NcpRecord;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\MenuCycleCostService;
use Database\Seeders\FoodServiceRecipeSeeder;
use Database\Seeders\FsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_recipe_deletion_prunes_template_lines_and_preserves_patient_data(): void
    {
        $rnd = User::factory()->rnd()->create();
        $recipe = Recipe::factory()->create(['rnd_user_id' => $rnd->id]);
        $food = FoodItem::factory()->create();
        $saved = MealPlanItem::factory()->create(['recipe_id' => $recipe->id, 'nutrient_snapshot' => ['name' => $recipe->name, 'calories' => 75]]);
        $before = $saved->fresh()->getAttributes();
        $template = MealPlanTemplate::create(['rnd_user_id' => $rnd->id, 'name' => 'My rotation']);
        $slot = $template->days()->create(['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'recipe_id' => $recipe->id, 'quantity' => 1, 'unit' => 'serving']);
        $removed = $slot->items()->create(['recipe_id' => $recipe->id, 'quantity' => 1, 'unit' => 'serving', 'line_order' => 1]);
        $retained = $slot->items()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g', 'line_order' => 2]);
        $empty = $template->days()->create(['day_of_week' => 'Tuesday', 'meal_type' => 'lunch', 'recipe_id' => $recipe->id]);

        $this->actingAs($rnd)->deleteJson('/api/rnd/recipes/'.$recipe->uuid)->assertNoContent();
        $this->assertSoftDeleted($recipe);
        $this->assertSame($before, $saved->fresh()->getAttributes());
        $this->assertSame($recipe->name, $saved->fresh()->recipe->name);
        $this->assertModelMissing($removed);
        $this->assertModelMissing($empty);
        $this->assertModelExists($retained);
        $this->assertNull($slot->fresh()->recipe_id);
        $this->assertEquals($food->id, $slot->fresh()->food_item_id);
        $this->assertEquals(100, $slot->fresh()->quantity);
    }

    public function test_food_deletion_preserves_saved_nutrients_and_rejects_new_recipe_use(): void
    {
        $rnd = User::factory()->rnd()->create();
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g']);
        $recipe = Recipe::factory()->create(['rnd_user_id' => $rnd->id]);
        $ingredient = $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        $saved = MealPlanItem::factory()->create(['food_item_id' => $food->id, 'nutrient_snapshot' => ['name' => $food->name, 'calories' => 75]]);
        $before = $saved->fresh()->getAttributes();
        $template = MealPlanTemplate::create(['rnd_user_id' => $rnd->id, 'name' => 'Food rotation']);
        $slot = $template->days()->create(['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'food_item_id' => $food->id]);
        $slot->items()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);

        $this->actingAs($rnd)->deleteJson('/api/rnd/food-items/'.$food->uuid)->assertNoContent();
        $this->assertSoftDeleted($food);
        $this->assertSame($before, $saved->fresh()->getAttributes());
        $this->assertSame($food->name, $ingredient->fresh()->foodItem->name);
        $this->assertModelMissing($slot);
        $this->postJson('/api/rnd/recipes', ['name' => 'New dish', 'category' => 'meal', 'servings' => 1,
            'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 100, 'unit' => 'g']]])
            ->assertUnprocessable()->assertJsonValidationErrors('ingredients.0.food_item_id');
    }

    public function test_food_service_deletion_preserves_menus_shopping_and_purchase_orders(): void
    {
        $rnd = User::factory()->rnd()->create();
        $item = FsItem::factory()->create(['name' => 'Measured rice', 'base_unit' => 'g', 'purchase_unit' => 'g', 'purchase_price' => 0.08]);
        $recipe = FoodServiceRecipe::create(['rnd_user_id' => $rnd->id, 'name' => 'Rice dish', 'category' => 'meal', 'servings' => 2]);
        $recipe->ingredients()->create(['fs_item_id' => $item->id, 'quantity' => 200, 'unit' => 'g']);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id]);
        foreach (['Monday', 'Tuesday'] as $index => $day) {
            MenuCycleDay::factory()->create(['menu_cycle_id' => $cycle->id, 'day_of_week' => $day, 'recipe_id' => $recipe->id, 'fs_item_id' => null,
                'estimate_population' => 10, 'recipe_override' => $index === 0 ? null : MenuCycleDay::recipeBaseline($recipe->id)]);
        }
        $template = MenuCycleTemplate::create(['rnd_user_id' => $rnd->id, 'name' => 'Kitchen rotation', 'cycle_days' => 7]);
        $template->days()->create(['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'recipe_id' => $recipe->id]);
        $template->days()->create(['day_of_week' => 'Monday', 'meal_type' => 'dinner', 'fs_item_id' => $item->id]);
        $list = ShoppingList::factory()->create(['rnd_user_id' => $rnd->id, 'list_type' => 'manual', 'status' => 'draft']);
        $listItem = $list->items()->create(['fs_item_id' => $item->id, 'ingredient_name' => 'Measured rice', 'qty' => 1000, 'unit' => 'g', 'unit_price' => 0.08, 'total' => 80, 'source' => 'manual']);
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $rnd->id]);
        $poItem = $po->items()->create(['fs_item_id' => $item->id, 'description' => 'Measured rice', 'qty' => 1000, 'unit' => 'g', 'unit_price' => 0.08, 'total_value' => 80]);
        $cost = MenuCycleCostService::forCycle($cycle->fresh());
        $listBefore = $listItem->fresh()->getAttributes();
        $poBefore = $poItem->fresh()->getAttributes();

        $this->actingAs($rnd)->deleteJson('/api/fss/fs-items/'.$item->uuid)->assertNoContent();
        $this->deleteJson('/api/fss/food-service-recipes/'.$recipe->uuid)->assertNoContent();
        $this->assertSoftDeleted($item);
        $this->assertSoftDeleted($recipe);
        $this->assertSame(0, $template->days()->count());
        $this->assertSame($cost, MenuCycleCostService::forCycle($cycle->fresh()));
        $this->assertSame($listBefore, $listItem->fresh()->getAttributes());
        $this->assertSame($poBefore, $poItem->fresh()->getAttributes());
        $this->assertSame('Measured rice', $listItem->fresh()->fsItem->name);
        $this->assertSame('Measured rice', $poItem->fresh()->fsItem->name);
        $line = $cycle->days()->firstOrFail();
        $this->getJson('/api/fss/menu-cycles/'.$cycle->uuid)->assertOk()->assertJsonPath('data.days.0.recipe.name', 'Rice dish');
        $this->getJson("/api/fss/menu-cycles/{$cycle->uuid}/lines/{$line->uuid}")->assertOk()->assertJsonPath('data.ingredients.0.name', 'Measured rice');
        $this->getJson('/api/fss/shopping-lists/'.$list->uuid)->assertOk()->assertJsonPath('data.items.0.inventory_unit', 'g');
        $this->postJson('/api/fss/fs-items', ['name' => 'Measured rice', 'kind' => 'ingredient', 'base_unit' => 'g', 'purchase_price' => 9])->assertCreated();
        $replacement = FsItem::where('name', 'Measured rice')->firstOrFail();
        $this->postJson('/api/fss/food-service-recipes', ['name' => 'Rice dish', 'category' => 'meal', 'servings' => 1,
            'ingredients' => [['fs_item_id' => $replacement->uuid, 'quantity' => 100, 'unit' => 'g']]])->assertCreated();
        $this->assertNotEquals($item->id, $replacement->id);
        $this->assertNotEquals($recipe->id, FoodServiceRecipe::where('name', 'Rice dish')->value('id'));
        $this->assertSame($cost, MenuCycleCostService::forCycle($cycle->fresh()));
        $this->postJson('/api/fss/menu-cycles', ['week_start_date' => '2026-12-07', 'days' => [['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'recipe_id' => $recipe->uuid]]])
            ->assertUnprocessable()->assertJsonValidationErrors('days.0.recipe_id');
        $this->postJson('/api/fss/shopping-lists/'.$list->uuid.'/items', ['fs_item_id' => $item->uuid, 'ingredient_name' => 'Retired rice', 'qty' => 1, 'unit' => 'g'])
            ->assertUnprocessable()->assertJsonValidationErrors('fs_item_id');
    }

    public function test_food_service_reseeding_keeps_saved_menu_records_and_new_choices_separate(): void
    {
        $rnd = User::factory()->rnd()->create();
        $this->seed([FsCatalogSeeder::class, FoodServiceRecipeSeeder::class]);
        $recipe = FoodServiceRecipe::firstOrFail();
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id]);
        MenuCycleDay::factory()->create(['menu_cycle_id' => $cycle->id, 'recipe_id' => $recipe->id, 'fs_item_id' => null, 'estimate_population' => 10]);
        $before = MenuCycleCostService::forCycle($cycle->fresh());
        $oldItems = FsItem::pluck('id')->all();
        $oldRecipes = FoodServiceRecipe::pluck('id')->all();
        FoodServiceRecipe::get()->each->delete();
        FsItem::get()->each->delete();
        $this->seed([FsCatalogSeeder::class, FoodServiceRecipeSeeder::class]);
        $newItems = FsItem::pluck('id')->all();
        $newRecipes = FoodServiceRecipe::pluck('id')->all();
        $this->seed([FsCatalogSeeder::class, FoodServiceRecipeSeeder::class]);
        $this->assertSame([], array_intersect($oldItems, $newItems));
        $this->assertSame([], array_intersect($oldRecipes, $newRecipes));
        $this->assertSame($newItems, FsItem::pluck('id')->all());
        $this->assertSame($newRecipes, FoodServiceRecipe::pluck('id')->all());
        $this->assertSame($before, MenuCycleCostService::forCycle($cycle->fresh()));
    }

    public function test_saved_menu_can_keep_a_deleted_source_but_cannot_add_it_to_another_slot(): void
    {
        $rnd = User::factory()->rnd()->create();
        $recipe = FoodServiceRecipe::create(['rnd_user_id' => $rnd->id, 'name' => 'Saved dish', 'servings' => 1]);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id, 'week_start_date' => '2026-12-07', 'status' => 'draft']);
        $line = MenuCycleDay::factory()->create(['menu_cycle_id' => $cycle->id, 'recipe_id' => $recipe->id, 'fs_item_id' => null, 'day_of_week' => 'Monday', 'meal_type' => 'lunch']);
        $recipe->delete();
        $payload = ['days' => [['id' => $line->uuid, 'day_of_week' => 'Monday', 'meal_type' => 'lunch', 'recipe_id' => $recipe->uuid]]];
        $this->actingAs($rnd)->patchJson('/api/fss/menu-cycles/'.$cycle->uuid, $payload)->assertOk();
        $this->assertEquals($recipe->id, MenuCycleDay::where('uuid', $line->uuid)->value('recipe_id'));
        $payload['days'][] = ['day_of_week' => 'Tuesday', 'meal_type' => 'dinner', 'recipe_id' => $recipe->uuid];
        $this->patchJson('/api/fss/menu-cycles/'.$cycle->uuid, $payload)->assertUnprocessable()->assertJsonValidationErrors('days.1.recipe_id');
        $payload['days'][1] = ['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'recipe_id' => $recipe->uuid];
        $this->patchJson('/api/fss/menu-cycles/'.$cycle->uuid, $payload)->assertUnprocessable()->assertJsonValidationErrors('days.1.recipe_id');
        $this->postJson('/api/fss/menu-cycles/'.$cycle->uuid.'/save-template', ['name' => 'Available kitchen choices'])->assertCreated();
        $this->assertSame(0, MenuCycleTemplate::where('name', 'Available kitchen choices')->firstOrFail()->days()->count());
    }

    public function test_saving_a_patient_plan_as_a_template_excludes_deleted_sources(): void
    {
        $rnd = User::factory()->rnd()->create();
        $ncp = NcpRecord::factory()->create(['rnd_user_id' => $rnd->id]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncp->id]);
        $plan = MealPlan::factory()->create(['intervention_id' => $intervention->id, 'patient_id' => $ncp->patient_id]);
        $day = MealPlanDay::factory()->create(['meal_plan_id' => $plan->id]);
        $recipe = Recipe::factory()->create(['rnd_user_id' => $rnd->id]);
        $saved = $day->items()->create(['recipe_id' => $recipe->id, 'quantity' => 1, 'unit' => 'serving', 'nutrient_snapshot' => ['name' => 'Saved dish', 'calories' => 100]]);
        $recipe->delete();
        $this->actingAs($rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/{$plan->uuid}/save-template", ['name' => 'Available choices'])->assertCreated();
        $this->assertSame(0, MealPlanTemplate::where('name', 'Available choices')->firstOrFail()->days()->count());
        $this->assertSame('Saved dish', $saved->fresh()->nutrient_snapshot['name']);
    }

    public function test_inventory_catalog_immediately_excludes_deleted_recipes_and_items(): void
    {
        $rnd = User::factory()->rnd()->create();
        $recipe = FoodServiceRecipe::create(['rnd_user_id' => $rnd->id, 'name' => 'Catalog dish', 'servings' => 1]);
        $item = FsItem::factory()->create();
        $this->actingAs($rnd)->getJson('/api/fss/inventory/rows')->assertOk()->assertJsonPath('meta.total', 2);
        $this->deleteJson('/api/fss/food-service-recipes/'.$recipe->uuid)->assertNoContent();
        $this->getJson('/api/fss/inventory/rows')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('stats.recipes', 0);
        $this->deleteJson('/api/fss/fs-items/'.$item->uuid)->assertNoContent();
        $this->getJson('/api/fss/inventory/rows')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('stats.catalog_items', 0);
    }

    public function test_generated_and_saved_shopping_rows_keep_deleted_sources_but_new_manual_rows_cannot_use_them(): void
    {
        $rnd = User::factory()->rnd()->create();
        $item = FsItem::factory()->create(['base_unit' => 'g', 'purchase_unit' => 'g', 'purchase_price' => 0.1]);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $rnd->id, 'week_start_date' => '2026-12-07']);
        MenuCycleDay::factory()->create(['menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday', 'meal_type' => 'lunch', 'recipe_id' => null,
            'fs_item_id' => $item->id, 'quantity' => 100, 'estimate_population' => 10]);
        $this->actingAs($rnd)->deleteJson('/api/fss/fs-items/'.$item->uuid)->assertNoContent();
        $payload = ['start_date' => '2026-12-07', 'end_date' => '2026-12-07', 'estimate_population' => 10];
        $preview = $this->postJson('/api/fss/shopping-lists/generate', [...$payload, 'preview' => true])
            ->assertOk()->assertJsonPath('data.items.0.fs_item_uuid', $item->uuid)->json('data.items.0');
        $line = array_intersect_key($preview, array_flip(['fs_item_uuid', 'source', 'purchase_unit', 'purchase_qty', 'purchase_price', 'supplier_id', 'quantity_mode', 'included_in_po', 'exclusion_note']));
        $saved = $this->postJson('/api/fss/shopping-lists/generate', [...$payload, 'items' => [$line]])->assertCreated();
        $listId = $saved->json('data.id');
        $line['id'] = $saved->json('data.items.0.id');
        $line['quantity_mode'] = 'manual';
        $line['purchase_qty'] = 1100;
        $this->patchJson('/api/fss/shopping-lists/'.$listId, ['items' => [$line]])->assertOk()->assertJsonPath('data.items.0.purchase_qty', '1100.000000');
        $extra = $line;
        unset($extra['id']);
        $extra['source'] = 'manual';
        $this->patchJson('/api/fss/shopping-lists/'.$listId, ['items' => [$line, $extra]])->assertUnprocessable();
        $this->assertSame(1, ShoppingList::where('uuid', $listId)->firstOrFail()->items()->count());
        $unrelated = FsItem::factory()->create();
        $unrelated->delete();
        $line['fs_item_uuid'] = $unrelated->uuid;
        $this->patchJson('/api/fss/shopping-lists/'.$listId, ['items' => [$line]])->assertUnprocessable();
    }
}
