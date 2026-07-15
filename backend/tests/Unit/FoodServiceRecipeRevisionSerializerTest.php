<?php

namespace Tests\Unit;

use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\User;
use App\Services\Audit\Revisions\Serializers\FoodServiceRecipeRevisionSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class FoodServiceRecipeRevisionSerializerTest extends TestCase
{
    use RefreshDatabase;

    public function test_serializer_captures_only_the_complete_safe_food_service_recipe_structure(): void
    {
        $rnd = User::factory()->rnd()->create();
        $item = FsItem::factory()->create([
            'name' => 'Brown Rice',
            'base_unit' => 'g',
            'purchase_unit' => 'kg',
            'purchase_price' => 80,
        ]);
        $recipe = FoodServiceRecipe::create([
            'rnd_user_id' => $rnd->id,
            'name' => 'Hospital Rice Bowl',
            'category' => 'Main dish',
            'prep_notes' => 'Steam and portion.',
            'servings' => 20,
            'cost' => 160,
        ]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id,
            'fs_item_id' => $item->id,
            'quantity' => 2000,
            'unit' => 'g',
        ]);

        $serializer = new FoodServiceRecipeRevisionSerializer;
        $snapshot = $serializer->capture($recipe);
        $presented = $serializer->present($snapshot->payload)->toArray();
        $encoded = json_encode($snapshot->payload, JSON_THROW_ON_ERROR);

        $this->assertSame('food_service_recipe', $snapshot->serializer);
        $this->assertSame($recipe->uuid, $snapshot->subjectPublicId);
        $this->assertSame('Hospital Rice Bowl', $presented['title']);
        $this->assertSame(160.0, $presented['fields'][4]['value']['value']);
        $this->assertSame('Brown Rice', $presented['tables'][0]['rows'][0]['values']['ingredient']['value']);
        $this->assertSame(2000.0, $presented['tables'][0]['rows'][0]['values']['quantity']['value']);
        $this->assertSame('g', $presented['tables'][0]['rows'][0]['values']['catalog_unit']['value']);
        $this->assertSame($item->unit_cost, $presented['tables'][0]['rows'][0]['values']['unit_cost']['value']);
        $this->assertStringNotContainsString('rnd_user_id', $encoded);
        $this->assertStringNotContainsString('food_service_recipe_id', $encoded);
        $this->assertStringNotContainsString('fs_item_id', $encoded);
        $this->assertStringNotContainsString('default_supplier_id', $encoded);
    }

    public function test_serializer_rejects_the_wrong_model_and_malformed_stored_payload(): void
    {
        $serializer = new FoodServiceRecipeRevisionSerializer;

        try {
            $serializer->capture(FsItem::factory()->create());
            $this->fail('Wrong model unexpectedly serialized.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Food service recipe serializer requires a food service recipe.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid food service recipe revision payload.');
        $serializer->present(['name' => ['RAW-NESTED-SENTINEL']]);
    }
}
