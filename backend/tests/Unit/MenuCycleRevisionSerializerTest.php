<?php

namespace Tests\Unit;

use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\User;
use App\Services\Audit\Revisions\Serializers\MenuCycleRevisionSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MenuCycleRevisionSerializerTest extends TestCase
{
    use RefreshDatabase;

    public function test_serializer_captures_the_complete_safe_weekly_menu_structure_and_totals(): void
    {
        $rnd = User::factory()->rnd()->create();
        $rice = FsItem::factory()->create([
            'name' => 'Brown Rice',
            'base_unit' => 'g',
            'purchase_unit' => 'kg',
            'purchase_price' => 80,
        ]);
        $banana = FsItem::factory()->create([
            'name' => 'Banana',
            'base_unit' => 'piece',
            'purchase_unit' => 'piece',
            'purchase_price' => 12,
        ]);
        $recipe = FoodServiceRecipe::create([
            'rnd_user_id' => $rnd->id,
            'name' => 'Rice Bowl',
            'servings' => 10,
            'cost' => 8,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id' => $rice->id,
            'quantity' => 100,
            'unit' => 'g',
        ]);
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $rnd->id,
            'name' => 'July Week One',
            'week_start_date' => '2026-07-13',
            'status' => 'upcoming',
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'recipe_id' => $recipe->id,
            'quantity' => 1,
            'servings_override' => 20,
            'estimate_population' => 20,
        ]);
        MenuCycleDay::create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Tuesday',
            'meal_type' => 'am_snack',
            'fs_item_id' => $banana->id,
            'quantity' => 1,
            'estimate_population' => 10,
            'is_event' => true,
            'event_allocation' => 120,
        ]);

        $serializer = new MenuCycleRevisionSerializer;
        $snapshot = $serializer->capture($cycle);
        $presented = $serializer->present($snapshot->payload)->toArray();
        $encoded = json_encode($snapshot->payload, JSON_THROW_ON_ERROR);
        $fields = collect($presented['fields'])->keyBy('key');

        $this->assertSame('menu_cycle', $snapshot->serializer);
        $this->assertSame($cycle->uuid, $snapshot->subjectPublicId);
        $this->assertSame('July Week One', $presented['title']);
        $this->assertSame('2026-07-13', $fields['week_start_date']['value']['value']);
        $this->assertSame('2026-07-19', $fields['week_end_date']['value']['value']);
        $this->assertSame(30, $fields['population']['value']['value']);
        $this->assertSame(136.0, $fields['total_cost']['value']['value']);
        $this->assertSame('Rice Bowl', $presented['tables'][0]['rows'][0]['values']['item']['value']);
        $this->assertSame('Banana', $presented['tables'][0]['rows'][1]['values']['item']['value']);
        $this->assertSame(2, count($presented['tables'][1]['rows']));
        $this->assertStringNotContainsString('rnd_user_id', $encoded);
        $this->assertStringNotContainsString('menu_cycle_id', $encoded);
        $this->assertStringNotContainsString('recipe_id', $encoded);
        $this->assertStringNotContainsString('fs_item_id', $encoded);
        $this->assertStringNotContainsString('po_snapshot', $encoded);
        $this->assertStringNotContainsString('snapshot_purchase_order_id', $encoded);
    }

    public function test_serializer_rejects_the_wrong_model_and_malformed_stored_payload(): void
    {
        $serializer = new MenuCycleRevisionSerializer;

        try {
            $serializer->capture(FsItem::factory()->create());
            $this->fail('Wrong model unexpectedly serialized.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Menu cycle serializer requires a menu cycle.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid menu cycle revision payload.');
        $serializer->present(['days' => ['RAW-NESTED-SENTINEL']]);
    }
}
