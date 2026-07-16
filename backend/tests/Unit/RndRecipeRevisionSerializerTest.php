<?php

namespace Tests\Unit;

use App\Models\FoodItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Services\Audit\Revisions\Serializers\RndRecipeRevisionSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RndRecipeRevisionSerializerTest extends TestCase
{
    use RefreshDatabase;

    public function test_serializer_captures_only_the_complete_safe_recipe_structure(): void
    {
        $rnd = User::factory()->rnd()->create();
        $recipe = Recipe::factory()->for($rnd, 'rnd')->create([
            'name' => 'Brown Rice Bowl',
            'category' => 'lunch',
            'meal_types' => ['lunch', 'dinner'],
            'servings' => 4,
            'prep_notes' => 'Combine and portion.',
            'total_calories' => 420.5,
            'total_protein' => 18.25,
            'total_carbs' => 62.75,
            'total_fat' => 8.5,
            'cost' => 180.25,
            'micronutrients' => ['raw_nested_sentinel' => 'DO-NOT-COPY'],
        ]);
        $food = FoodItem::factory()->create(['name' => 'Brown Rice']);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'food_item_id' => $food->id,
            'quantity' => 150,
            'unit' => 'g',
        ]);

        $serializer = new RndRecipeRevisionSerializer;
        $snapshot = $serializer->capture($recipe);
        $presented = $serializer->present($snapshot->payload)->toArray();
        $encoded = json_encode($snapshot->payload, JSON_THROW_ON_ERROR);

        $this->assertSame('rnd_recipe', $snapshot->serializer);
        $this->assertSame($recipe->uuid, $snapshot->subjectPublicId);
        $this->assertSame('Brown Rice Bowl', $presented['title']);
        $this->assertSame(['lunch', 'dinner'], collect($presented['fields'])->firstWhere('key', 'meal_types')['value']['value']);
        $this->assertSame('Brown Rice', $presented['tables'][0]['rows'][0]['values']['ingredient']['value']);
        $this->assertSame(150.0, $presented['tables'][0]['rows'][0]['values']['quantity']['value']);
        $this->assertSame('g', $presented['tables'][0]['rows'][0]['values']['unit']['value']);
        $this->assertStringNotContainsString('rnd_user_id', $encoded);
        $this->assertStringNotContainsString('recipe_id', $encoded);
        $this->assertStringNotContainsString('food_item_id', $encoded);
        $this->assertStringNotContainsString('DO-NOT-COPY', $encoded);
    }

    public function test_serializer_rejects_the_wrong_model_and_malformed_stored_payload(): void
    {
        $serializer = new RndRecipeRevisionSerializer;

        try {
            $serializer->capture(FoodItem::factory()->create());
            $this->fail('Wrong model unexpectedly serialized.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('RND recipe serializer requires a recipe.', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid RND recipe revision payload.');
        $serializer->present(['name' => ['RAW-NESTED-SENTINEL']]);
    }

    public function test_schema_one_payload_without_meal_types_remains_readable(): void
    {
        $recipe = Recipe::factory()->create(['meal_types' => ['dinner']]);
        $serializer = new RndRecipeRevisionSerializer;
        $legacy = $serializer->capture($recipe)->payload;
        unset($legacy['meal_types']);

        $presented = $serializer->present($legacy)->toArray();

        $mealTypes = collect($presented['fields'])->firstWhere('key', 'meal_types');
        $this->assertSame([], $mealTypes['value']['value']);
    }
}
