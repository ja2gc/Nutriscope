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

    public function test_menu_item_can_save_label_nutrients_with_a_measured_reusable_ingredient(): void
    {
        $response = $this->actingAs($this->rnd)->postJson('/api/rnd/recipes', [
            'name' => 'Demo branded drink', 'category' => 'snack', 'servings' => 6, 'prep_notes' => 'Shake before serving.',
            'nutrition_label' => [
                'serving_size' => 250, 'serving_unit' => 'mL', 'calories' => 120,
                'protein' => 8, 'carbs' => 12, 'fat' => 4, 'water_g' => 220,
                'micronutrients' => ['calcium' => 300, 'sodium' => 90], 'allergens' => ['milk'],
            ],
        ])->assertCreated();
        $recipe = Recipe::where('uuid', $response->json('data.id'))->firstOrFail()->load('ingredients.foodItem');
        $ingredient = $recipe->ingredients->sole();
        $this->assertEquals(1500, $ingredient->quantity);
        $this->assertSame('mL', $ingredient->unit);
        $this->assertEquals(250, $ingredient->foodItem->serving_size);
        $this->assertSame(['milk'], $ingredient->foodItem->allergens);
        $this->assertEquals(720, $recipe->total_calories);
        $snapshot = $recipe->planSnapshot();
        $this->assertEquals(120, $snapshot['calories']);
        $this->assertEquals(300, $snapshot['micronutrients']['calcium']);
        $this->assertEquals(6, $snapshot['recipe_yield']);
        $this->assertSame('Shake before serving.', $snapshot['prep_notes']);
        $recipe->update(['prep_notes' => 'New library notes']);
        $this->assertSame('Shake before serving.', $recipe->planSnapshot([], $snapshot)['prep_notes']);
        $this->assertEquals(250, $snapshot['recipe_original_ingredients'][0]['food_item']['serving_size']);
        $this->assertTrue(AuditActivity::where('subject_type', FoodItem::class)->where('subject_id', $ingredient->foodItem->id)->where('event', 'created')->exists());
    }

    public function test_label_nutrients_reject_ambiguous_or_invalid_input_without_partial_records(): void
    {
        $label = ['serving_size' => 30, 'serving_unit' => 'g', 'calories' => 100];
        $food = FoodItem::factory()->create();
        $cases = [
            [['nutrition_label' => array_replace($label, ['serving_size' => 0])], 'nutrition_label.serving_size'],
            [['nutrition_label' => array_replace($label, ['serving_unit' => 'pack'])], 'nutrition_label.serving_unit'],
            [['nutrition_label' => array_replace($label, ['serving_unit' => 'piece'])], 'nutrition_label.serving_size'],
            [['nutrition_label' => array_replace($label, ['calories' => -1])], 'nutrition_label.calories'],
            [['nutrition_label' => array_replace($label, ['micronutrients' => ['sodium' => -1]])], 'nutrition_label.micronutrients.sodium'],
            [['nutrition_label' => $label, 'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 100, 'unit' => 'g']]], 'ingredients'],
            [['nutrition_label' => array_replace($label, ['serving_size' => 10000]), 'servings' => 10000], 'nutrition_label.serving_size'],
        ];
        foreach ($cases as [$payload, $field]) {
            $this->actingAs($this->rnd)->postJson('/api/rnd/recipes', array_replace([
                'name' => 'Invalid branded item', 'category' => 'snack', 'servings' => 1,
            ], $payload))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('food_items', 1);
        $this->assertDatabaseCount('recipes', 0);
    }

    public function test_clinical_recipe_converts_all_nutrients_from_the_measured_baseline(): void
    {
        $food = FoodItem::factory()->create([
            'serving_size' => 100, 'serving_unit' => 'g', 'calories' => 200,
            'protein' => 10, 'carbs' => 20, 'fat' => 5, 'water_g' => 80,
            'micronutrients' => ['calcium' => 15],
        ]);
        $response = $this->actingAs($this->rnd)->postJson('/api/rnd/recipes', [
            'name' => 'Measured batch', 'category' => 'meal', 'servings' => 5,
            'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 0.5, 'unit' => 'kg']],
        ])->assertCreated();
        $recipe = Recipe::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertEquals(1000, $recipe->total_calories);
        $this->assertEquals(50, $recipe->total_protein);
        $this->assertEquals(100, $recipe->total_carbs);
        $this->assertEquals(25, $recipe->total_fat);
        $this->assertEquals(400, $recipe->total_water_g);
        $this->assertEquals(75, $recipe->micronutrients['calcium']);
    }

    public function test_clinical_recipe_rejects_unmeasured_units_before_any_saved_mutation(): void
    {
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g']);
        foreach (['piece', 'cup', 'pinch'] as $unit) {
            $this->actingAs($this->rnd)->postJson('/api/rnd/recipes', [
                'name' => 'Invalid '.$unit, 'category' => 'meal', 'servings' => 1,
                'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 1, 'unit' => $unit]],
            ])->assertUnprocessable()->assertJsonValidationErrors('ingredients.0.unit');
            $this->assertDatabaseMissing('recipes', ['name' => 'Invalid '.$unit]);
        }
    }

    public function test_clinical_recipe_retains_six_decimal_quantities(): void
    {
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g']);
        $response = $this->actingAs($this->rnd)->postJson('/api/rnd/recipes', [
            'name' => 'Small measured amount', 'category' => 'snack', 'servings' => 1,
            'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 0.000001, 'unit' => 'kg']],
        ])->assertCreated();
        $recipe = Recipe::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertEquals(0.000001, (float) $recipe->ingredients()->sole()->quantity);
    }

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
                'category' => 'meal',
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

    public function test_new_recipe_requires_an_explicit_yield(): void
    {
        $food = FoodItem::factory()->create();

        $this->actingAs($this->rnd)
            ->postJson('/api/rnd/recipes', [
                'name' => 'Unspecified yield',
                'category' => 'meal',
                'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 100, 'unit' => 'g']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('servings');
    }

    public function test_only_the_three_menu_item_categories_are_accepted(): void
    {
        $food = FoodItem::factory()->create();

        foreach (['meal', 'carb', 'snack'] as $category) {
            $response = $this->actingAs($this->rnd)->postJson('/api/rnd/recipes', [
                'name' => "Valid {$category}",
                'category' => $category,
                'servings' => 1,
                'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 100, 'unit' => 'g']],
            ]);

            $response->assertCreated()->assertJsonPath('data.category', $category);
        }

        foreach (['Breakfast', 'Diabetic-Friendly', 'lunch', 'vegetarian'] as $category) {
            $this->actingAs($this->rnd)->postJson('/api/rnd/recipes', [
                'name' => "Invalid {$category}",
                'category' => $category,
                'servings' => 1,
                'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 100, 'unit' => 'g']],
            ])->assertUnprocessable()->assertJsonValidationErrors('category');
        }

        $this->actingAs($this->rnd)->postJson('/api/rnd/recipes', [
            'name' => 'Missing category',
            'servings' => 1,
            'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 100, 'unit' => 'g']],
        ])->assertUnprocessable()->assertJsonValidationErrors('category');

        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id]);
        $this->actingAs($this->rnd)->putJson("/api/rnd/recipes/{$recipe->uuid}", [
            'category' => 'Breakfast',
        ])->assertUnprocessable()->assertJsonValidationErrors('category');
    }

    public function test_new_menu_item_requires_at_least_one_measured_ingredient(): void
    {
        foreach ([null, []] as $ingredients) {
            $payload = [
                'name' => 'No measured ingredient',
                'category' => 'meal',
                'servings' => 1,
            ];
            if ($ingredients !== null) {
                $payload['ingredients'] = $ingredients;
            }

            $this->actingAs($this->rnd)->postJson('/api/rnd/recipes', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('ingredients');
        }

        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id]);
        $this->actingAs($this->rnd)->putJson("/api/rnd/recipes/{$recipe->uuid}", [
            'ingredients' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('ingredients');
    }

    public function test_multi_ingredient_snack_menu_item_is_valid(): void
    {
        $fruit = FoodItem::factory()->create();
        $milk = FoodItem::factory()->create(['serving_unit' => 'mL']);

        $this->actingAs($this->rnd)->postJson('/api/rnd/recipes', [
            'name' => 'Fruit and Milk Snack',
            'category' => 'snack',
            'servings' => 1,
            'ingredients' => [
                ['food_item_id' => $fruit->uuid, 'quantity' => 100, 'unit' => 'g'],
                ['food_item_id' => $milk->uuid, 'quantity' => 150, 'unit' => 'mL'],
            ],
        ])->assertCreated()->assertJsonPath('data.category', 'snack');
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
                'category' => 'meal',
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
        $food = FoodItem::factory()->create(['water_g' => 70]);
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
            ->assertJsonPath('data.ingredients.0.food_item.water_g', 70)
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
                'category' => 'carb',
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
        $food = FoodItem::factory()->create();
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/recipes', [
                'name' => 'My Recipe',
                'category' => 'meal',
                'servings' => 1,
                'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 100, 'unit' => 'g']],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('recipes', [
            'name' => 'My Recipe',
            'rnd_user_id' => $this->rnd->id,
        ]);
    }

    public function test_recipe_rejects_oversized_notes_servings_and_ingredient_quantities(): void
    {
        $food = FoodItem::factory()->create();

        foreach ([
            [['prep_notes' => str_repeat('x', 2001)], 'prep_notes'],
            [['servings' => 10001], 'servings'],
            [['ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 100000.01, 'unit' => 'g']]], 'ingredients.0.quantity'],
        ] as [$override, $field]) {
            $this->actingAs($this->rnd)->postJson('/api/rnd/recipes', array_merge([
                'name' => 'Validation '.$field,
            ], $override))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
}
