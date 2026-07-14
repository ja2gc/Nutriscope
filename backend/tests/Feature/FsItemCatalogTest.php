<?php

namespace Tests\Feature;

use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reference catalog (fs_items) — the backend list of ingredients/supplies that
 * recipes and procurement are built from. Carries unit + cost, never a stock qty.
 */
class FsItemCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
        $this->fss = User::factory()->create(['role' => 'FSS']);
    }

    public function test_catalog_lists_items_by_kind(): void
    {
        FsItem::factory()->create(['name' => 'Pork', 'kind' => 'ingredient']);
        FsItem::factory()->create(['name' => 'Garbage bag', 'kind' => 'supply']);

        $data = $this->actingAs($this->rnd)
            ->getJson('/api/fss/fs-items/catalog?kind=supply')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Garbage bag', $data[0]['name']);
        $this->assertArrayHasKey('unit_cost', $data[0]);
        $this->assertArrayHasKey('vendor', $data[0]);
    }

    public function test_rnd_creates_ingredient_with_unit_and_cost(): void
    {
        $vendor = Supplier::factory()->create();

        // Plan: ingredient = name, category, vendor, unit, unit/cost. Single unit, cost per unit.
        $this->actingAs($this->rnd)
            ->postJson('/api/fss/fs-items', [
                'name' => 'Tilapia', 'kind' => 'ingredient', 'category' => 'Fish',
                'base_unit' => 'kg', 'purchase_price' => 160,
                'default_supplier_id' => $vendor->uuid,
            ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'ingredient')
            ->assertJsonPath('data.vendor', $vendor->name);

        // unit_cost == entered cost (purchase_unit == base_unit, 1 per purchase).
        $item = FsItem::where('name', 'Tilapia')->firstOrFail();
        $this->assertSame('kg', $item->purchase_unit);
        $this->assertEqualsWithDelta(1, (float) $item->units_per_purchase, 0.0001);
        $this->assertEqualsWithDelta(160, $item->unit_cost, 0.0001);
    }

    public function test_supply_has_no_qty_only_cost_per_unit(): void
    {
        $this->actingAs($this->rnd)
            ->postJson('/api/fss/fs-items', [
                'name' => 'Dish soap', 'kind' => 'supply',
                'base_unit' => 'bottle', 'purchase_price' => 45,
            ])
            ->assertCreated();

        $item = FsItem::where('name', 'Dish soap')->firstOrFail();
        // Cost per unit: purchase unit == base unit, 1 per purchase, unit_cost == price.
        $this->assertSame('bottle', $item->purchase_unit);
        $this->assertEqualsWithDelta(1, (float) $item->units_per_purchase, 0.0001);
        $this->assertEqualsWithDelta(45, $item->unit_cost, 0.0001);
    }

    public function test_fss_cannot_create_catalog_item(): void
    {
        $this->actingAs($this->fss)
            ->postJson('/api/fss/fs-items', [
                'name' => 'Beef', 'kind' => 'ingredient', 'base_unit' => 'g', 'purchase_price' => 5,
            ])
            ->assertForbidden();
    }

    public function test_delete_blocked_while_used_by_recipe(): void
    {
        $item = FsItem::factory()->create(['kind' => 'ingredient']);
        $recipe = FoodServiceRecipe::create(['rnd_user_id' => $this->rnd->id, 'name' => 'Stew', 'servings' => 10]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id, 'fs_item_id' => $item->id,
            'quantity' => 100, 'unit' => $item->base_unit,
        ]);

        $this->actingAs($this->rnd)
            ->deleteJson("/api/fss/fs-items/{$item->uuid}")
            ->assertStatus(409);

        $this->assertDatabaseHas('fs_items', ['id' => $item->id]);
    }

    public function test_rnd_can_delete_unused_item(): void
    {
        $item = FsItem::factory()->create(['kind' => 'supply']);

        $this->actingAs($this->rnd)
            ->deleteJson("/api/fss/fs-items/{$item->uuid}")
            ->assertNoContent();

        $this->assertDatabaseMissing('fs_items', ['id' => $item->id]);
    }
}
