<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\FoodItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RecipeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND', 'password' => Hash::make('password')]);
    }

    public function test_rnd_can_list_recipes(): void
    {
        Recipe::factory(4)->create(['rnd_user_id' => $this->rnd->id]);

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/recipes');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_rnd_can_create_recipe_with_ingredients(): void
    {
        $food1 = FoodItem::factory()->create([
            'calories' => 200, 'protein' => 20, 'carbs' => 10,
            'fat' => 5, 'serving_size' => 100, 'unit_price' => 50,
        ]);
        $food2 = FoodItem::factory()->create([
            'calories' => 100, 'protein' => 5, 'carbs' => 20,
            'fat' => 2, 'serving_size' => 100, 'unit_price' => 30,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/recipes', [
                'name' => 'Chicken & Rice Bowl',
                'category' => 'lunch',
                'servings' => 2,
                'prep_notes' => 'Cook chicken then combine.',
                'ingredients' => [
                    ['food_item_id' => $food1->uuid, 'quantity' => 150, 'unit' => 'g'],
                    ['food_item_id' => $food2->uuid, 'quantity' => 200, 'unit' => 'g'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Chicken & Rice Bowl');

        $this->assertDatabaseHas('recipes', ['name' => 'Chicken & Rice Bowl']);
        $this->assertDatabaseHas('recipe_ingredients', ['food_item_id' => $food1->id]);
        $this->assertDatabaseHas('recipe_ingredients', ['food_item_id' => $food2->id]);

        $activity = AuditActivity::query()->where('subject_type', Recipe::class)->sole();
        $this->assertSame('created', $activity->event);
        $this->assertContains('ingredients', $activity->properties['details']['changed_fields']);
        $this->assertNotContains('prep_notes', $activity->properties['details']['changed_fields']);
        $this->assertStringNotContainsString('Cook chicken then combine.', $activity->properties->toJson());
        $this->assertSame('Chicken & Rice Bowl', $activity->revision->after['name']);
        $this->assertSame('Chicken & Rice Bowl', $activity->revision->after['title']);
        $this->assertCount(2, $activity->revision->after['ingredients']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->getJson("/api/admin/audit-logs/{$activity->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.version.serializer', 'rnd_recipe')
            ->assertJsonPath('data.event.detail_mode', 'history')
            ->assertJsonPath('data.event.history.label', 'View created version')
            ->assertJsonPath('data.after.title', 'Chicken & Rice Bowl')
            ->assertJsonCount(2, 'data.after.tables.0.rows');
    }

    public function test_recipe_totals_auto_calculated_on_create(): void
    {
        $food = FoodItem::factory()->create([
            'calories' => 200,
            'protein' => 20,
            'carbs' => 10,
            'fat' => 5,
            'serving_size' => 100,
            'unit_price' => 50,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/recipes', [
                'name' => 'Simple Meal',
                'category' => 'lunch',
                'servings' => 1,
                'ingredients' => [
                    ['food_item_id' => $food->uuid, 'quantity' => 100, 'unit' => 'g'],
                ],
            ]);

        $response->assertCreated();
        // 100g at 100g serving = factor 1.0, so totals match food values
        $this->assertEquals('200.00', $response->json('data.total_calories'));
        $this->assertEquals('20.00', $response->json('data.total_protein'));
    }

    public function test_rnd_can_view_recipe_with_ingredients(): void
    {
        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id]);
        $food = FoodItem::factory()->create();
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'food_item_id' => $food->id,
            'quantity' => 100,
            'unit' => 'g',
        ]);

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/recipes/{$recipe->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.id', $recipe->uuid)
            ->assertJsonStructure(['data' => ['id', 'name', 'ingredients']]);
    }

    public function test_rnd_can_update_recipe_ingredients(): void
    {
        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id]);
        $food = FoodItem::factory()->create([
            'calories' => 150, 'protein' => 10, 'carbs' => 20,
            'fat' => 3, 'serving_size' => 100, 'unit_price' => 30,
        ]);

        $response = $this->actingAs($this->rnd)
            ->putJson("/api/rnd/recipes/{$recipe->uuid}", [
                'name' => 'Updated Recipe',
                'category' => 'dinner',
                'servings' => 3,
                'ingredients' => [
                    ['food_item_id' => $food->uuid, 'quantity' => 200, 'unit' => 'g'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Recipe');

        $this->assertDatabaseHas('recipe_ingredients', [
            'recipe_id' => $recipe->id,
            'food_item_id' => $food->id,
        ]);
        $activity = AuditActivity::query()->where('subject_type', Recipe::class)->sole();
        $this->assertSame('updated', $activity->event);
        $this->assertContains('ingredients', $activity->properties['details']['changed_fields']);
        $this->assertSame($recipe->name, $activity->revision->before['name']);
        $this->assertSame('Updated Recipe', $activity->revision->after['name']);
        $this->assertSame('Updated Recipe', $activity->revision->after['title']);
        $this->assertCount(1, $activity->revision->after['ingredients']);
        Recipe::withoutEvents(fn (): int => Recipe::query()->whereKey($recipe->id)->update(['name' => 'Later Current Name']));
        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/admin/audit-logs/{$activity->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.after.title', 'Updated Recipe')
            ->assertJsonPath('data.event.current_record_url', null);
    }

    public function test_rnd_can_delete_recipe(): void
    {
        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id]);

        $response = $this->actingAs($this->rnd)
            ->deleteJson("/api/rnd/recipes/{$recipe->uuid}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $activity = AuditActivity::query()->where('subject_type', Recipe::class)->sole();
        $this->assertSame('deleted', $activity->event);
        $this->assertSame($recipe->name, $activity->revision->before['name']);
        $this->assertNull($activity->revision->after);
        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/admin/audit-logs/{$activity->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.before.title', $recipe->name)
            ->assertJsonPath('data.after', null);
    }

    public function test_simple_recipe_field_update_stays_in_the_typed_drawer_without_a_revision(): void
    {
        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id, 'servings' => 2]);

        $this->actingAs($this->rnd)
            ->putJson("/api/rnd/recipes/{$recipe->uuid}", [
                'name' => $recipe->name,
                'category' => $recipe->category,
                'servings' => 6,
            ])
            ->assertOk();

        $activity = AuditActivity::query()->where('subject_type', Recipe::class)->sole();
        $this->assertSame('updated', $activity->event);
        $this->assertNull($activity->revision);
        $event = app(AuditEventPresenter::class)
            ->present($activity->load('causer'), User::factory()->admin()->create())
            ->toArray();
        $this->assertStringContainsString("recipe: {$recipe->name}", $event['summary']);
        $change = collect($event['changes'])->firstWhere('field', 'servings');
        $this->assertSame(2, $change['before']['value']);
        $this->assertSame(6, $change['after']['value']);
    }

    public function test_meal_type_change_stores_a_complete_historical_recipe_version(): void
    {
        $recipe = Recipe::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'meal_types' => ['lunch'],
        ]);

        $this->actingAs($this->rnd)
            ->putJson("/api/rnd/recipes/{$recipe->uuid}", [
                'meal_types' => ['dinner', 'pm_snack'],
            ])
            ->assertOk()
            ->assertJsonPath('data.meal_types.0', 'dinner');

        $activity = AuditActivity::query()->where('subject_type', Recipe::class)->sole();
        $this->assertNotNull($activity->revision);
        $this->assertSame(['lunch'], $activity->revision->before['meal_types']);
        $this->assertSame(['dinner', 'pm_snack'], $activity->revision->after['meal_types']);
    }

    public function test_recipe_belongs_to_creating_rnd_user(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/recipes', [
                'name' => 'My Recipe',
                'category' => 'breakfast',
                'servings' => 1,
                'ingredients' => [],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('recipes', [
            'name' => 'My Recipe',
            'rnd_user_id' => $this->rnd->id,
        ]);
    }
}
