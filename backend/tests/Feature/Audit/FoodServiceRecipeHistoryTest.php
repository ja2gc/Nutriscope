<?php

namespace Tests\Feature\Audit;

use App\Models\AuditActivity;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class FoodServiceRecipeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rnd = User::factory()->rnd()->create();
        $this->admin = User::factory()->admin()->create();
        AuditFixture::delete(AuditActivity::query());
    }

    public function test_create_emits_one_canonical_event_with_an_after_only_structural_revision(): void
    {
        $item = $this->item('Brown Rice', 80);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)->postJson('/api/fss/food-service-recipes', [
            'name' => 'Hospital Rice Bowl',
            'category' => 'Main dish',
            'prep_notes' => 'Steam and portion.',
            'servings' => 20,
            'ingredients' => [[
                'fs_item_id' => $item->uuid,
                'quantity' => 2000,
                'unit' => 'g',
            ]],
        ])->assertCreated();

        $activity = AuditActivity::query()->where('subject_type', FoodServiceRecipe::class)->sole();
        $this->assertSame('created', $activity->event);
        $this->assertContains('ingredients', $activity->properties['details']['changed_fields']);
        $this->assertNull($activity->revision->before);
        $this->assertSame('Hospital Rice Bowl', $activity->revision->after['title']);
        $this->assertSame('Brown Rice', $activity->revision->after['ingredients'][0]['ingredient']);

        $this->actingAs($this->admin)
            ->getJson("/api/admin/audit-logs/{$activity->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.version.serializer', 'food_service_recipe')
            ->assertJsonPath('data.event.detail_mode', 'history')
            ->assertJsonPath('data.after.type', 'food_service_recipe')
            ->assertJsonPath('data.after.tables.0.rows.0.values.ingredient.value', 'Brown Rice');
    }

    public function test_structural_update_preserves_event_time_before_and_after_versions(): void
    {
        $rice = $this->item('Brown Rice', 80);
        $chicken = $this->item('Chicken Breast', 240);
        $recipe = $this->recipeWithIngredient($rice, 'Original Tray Meal', 1000);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)->patchJson("/api/fss/food-service-recipes/{$recipe->uuid}", [
            'name' => 'Updated Tray Meal',
            'servings' => 25,
            'ingredients' => [[
                'fs_item_id' => $chicken->uuid,
                'quantity' => 1500,
                'unit' => 'g',
            ]],
        ])->assertOk();

        $activity = AuditActivity::query()->where('subject_type', FoodServiceRecipe::class)->sole();
        $this->assertSame('updated', $activity->event);
        $this->assertSame('Original Tray Meal', $activity->revision->before['name']);
        $this->assertSame('Brown Rice', $activity->revision->before['ingredients'][0]['ingredient']);
        $this->assertSame('Updated Tray Meal', $activity->revision->after['name']);
        $this->assertSame('Chicken Breast', $activity->revision->after['ingredients'][0]['ingredient']);

        FoodServiceRecipe::withoutEvents(fn (): int => FoodServiceRecipe::query()
            ->whereKey($recipe->id)
            ->update(['name' => 'Later Mutable Name']));

        $this->actingAs($this->admin)
            ->getJson("/api/admin/audit-logs/{$activity->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.before.title', 'Original Tray Meal')
            ->assertJsonPath('data.after.title', 'Updated Tray Meal')
            ->assertJsonPath('data.event.current_record_url', null);
    }

    public function test_simple_field_update_stays_in_the_typed_drawer_without_a_revision(): void
    {
        $item = $this->item('Rice', 80);
        $recipe = $this->recipeWithIngredient($item, 'Simple Recipe', 1000);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)
            ->patchJson("/api/fss/food-service-recipes/{$recipe->uuid}", ['servings' => 30])
            ->assertOk();

        $activity = AuditActivity::query()->where('subject_type', FoodServiceRecipe::class)->sole();
        $this->assertSame('updated', $activity->event);
        $this->assertNull($activity->revision);
        $this->assertSame(20, $activity->properties['old']['servings']);
        $this->assertSame(30, $activity->properties['attributes']['servings']);
        $presented = app(AuditEventPresenter::class)->present($activity, $this->admin)->toArray();
        $this->assertSame('changes', $presented['detail_mode']);
        $this->assertSame('Servings', $presented['changes'][0]['label']);
        $this->assertSame(20, $presented['changes'][0]['before']['value']);
        $this->assertSame(30, $presented['changes'][0]['after']['value']);
    }

    public function test_delete_revision_survives_the_deleted_live_recipe(): void
    {
        $item = $this->item('Rice', 80);
        $recipe = $this->recipeWithIngredient($item, 'Retired Recipe', 1000);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)
            ->deleteJson("/api/fss/food-service-recipes/{$recipe->uuid}")
            ->assertNoContent();

        $activity = AuditActivity::query()->where('subject_type', FoodServiceRecipe::class)->sole();
        $this->assertDatabaseMissing('food_service_recipes', ['id' => $recipe->id]);
        $this->assertSame('Retired Recipe', $activity->revision->before['name']);
        $this->assertNull($activity->revision->after);
        $this->actingAs($this->admin)
            ->getJson("/api/admin/audit-logs/{$activity->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.before.title', 'Retired Recipe')
            ->assertJsonPath('data.after', null);
    }

    public function test_blocked_delete_leaves_no_audit_event_or_revision(): void
    {
        $item = $this->item('Rice', 80);
        $recipe = $this->recipeWithIngredient($item, 'Menu Recipe', 1000);
        $cycle = MenuCycle::factory()->create(['rnd_user_id' => $this->rnd->id]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'recipe_id' => $recipe->id,
            'fs_item_id' => null,
        ]);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($this->rnd)
            ->deleteJson("/api/fss/food-service-recipes/{$recipe->uuid}")
            ->assertConflict();

        $this->assertDatabaseHas('food_service_recipes', ['id' => $recipe->id]);
        $this->assertSame(0, AuditActivity::query()->where('subject_type', FoodServiceRecipe::class)->count());
    }

    private function item(string $name, float $purchasePrice): FsItem
    {
        return FsItem::factory()->create([
            'name' => $name,
            'base_unit' => 'g',
            'purchase_unit' => 'kg',
            'purchase_price' => $purchasePrice,
        ]);
    }

    private function recipeWithIngredient(FsItem $item, string $name, float $quantity): FoodServiceRecipe
    {
        $recipe = FoodServiceRecipe::create([
            'rnd_user_id' => $this->rnd->id,
            'name' => $name,
            'category' => 'Main dish',
            'servings' => 20,
            'cost' => $quantity * $item->unit_cost,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id' => $item->id,
            'quantity' => $quantity,
            'unit' => 'g',
        ]);

        return $recipe;
    }
}
