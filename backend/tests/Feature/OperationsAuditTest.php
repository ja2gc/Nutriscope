<?php

namespace Tests\Feature;

use App\Exceptions\AuditLoggingUnavailable;
use App\Models\Announcement;
use App\Models\AuditActivity;
use App\Models\FoodItem;
use App\Models\FsItem;
use App\Models\MenuCycleTemplate;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditContextResolver;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\AuditLogger;
use App\Services\UsdaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class OperationsAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_operation_is_explicitly_logged_but_failed_validation_is_not(): void
    {
        $user = User::factory()->rnd()->create();

        $this->actingAs($user)->postJson('/api/rnd/food-items', [])->assertUnprocessable();
        $this->assertSame(0, AuditActivity::query()->count());

        $this->actingAs($user)->postJson('/api/rnd/food-items', [
            'name' => 'Safe catalog item',
            'calories' => 100,
            'protein' => 5,
            'carbs' => 10,
            'fat' => 2,
            'serving_size' => 100,
            'serving_unit' => 'g',
        ])->assertCreated();

        $activity = AuditActivity::query()->sole();
        $this->assertSame('created', $activity->event);
        $this->assertSame('operations', $activity->category->value);
        $this->assertSame('nutrition_library', $activity->domain->value);
        $this->assertSame('success', $activity->outcome->value);
        $this->assertSame('info', $activity->severity->value);
        $this->assertSame($user->id, $activity->causer_id);
        $this->assertSame(['calories', 'carbs', 'fat', 'name', 'protein', 'serving_size', 'serving_unit'], $activity->properties['details']['changed_fields']);
        $this->assertArrayHasKey('public_id', $activity->properties['details']);
    }

    public function test_explicit_announcement_and_sop_events_exclude_content(): void
    {
        $user = User::factory()->rnd()->create();

        $this->actingAs($user)->postJson('/api/rnd/announcements', [
            'title' => 'Confidential title',
            'body' => 'Confidential announcement body',
            'category' => 'General',
            'visibility' => 'All',
            'attachments' => ['private/report.pdf'],
        ])->assertCreated();
        $this->actingAs($user)->postJson('/api/sop', [
            'title' => 'Confidential SOP',
            'body' => 'Confidential SOP body',
        ])->assertCreated();

        $serialized = AuditActivity::query()->get()->toJson();
        $this->assertSame(2, AuditActivity::query()->count());
        $this->assertStringNotContainsString('Confidential announcement body', $serialized);
        $this->assertStringNotContainsString('private/report.pdf', $serialized);
        $this->assertStringNotContainsString('Confidential SOP body', $serialized);
    }

    public function test_explicit_fs_item_event_replaces_model_event(): void
    {
        $user = User::factory()->rnd()->create();

        $this->actingAs($user)->postJson('/api/fss/fs-items', [
            'name' => 'Rice',
            'kind' => 'ingredient',
            'base_unit' => 'kg',
            'purchase_price' => 55,
        ])->assertCreated();

        $activity = AuditActivity::query()->sole();
        $this->assertSame('created', $activity->event);
        $this->assertSame('operations', $activity->category->value);
        $this->assertSame('food_service', $activity->domain->value);
        $this->assertSame(
            ['base_unit', 'include_in_generated_lists', 'is_active', 'kind', 'name', 'purchase_price', 'purchase_unit', 'unit_cost', 'units_per_purchase', 'vendor_locked'],
            $activity->properties['details']['changed_fields'],
        );
    }

    public function test_fs_item_crud_exposes_only_safe_typed_values_and_final_state(): void
    {
        $user = User::factory()->rnd()->create();
        $admin = User::factory()->admin()->create();
        $supplier = Supplier::factory()->create(['name' => 'Farm Cooperative']);
        AuditFixture::delete(AuditActivity::query());

        $id = $this->actingAs($user)->postJson('/api/fss/fs-items', [
            'name' => 'Brown rice',
            'kind' => 'ingredient',
            'category' => 'Grains',
            'base_unit' => 'kg',
            'purchase_price' => 55,
            'default_supplier_id' => $supplier->uuid,
        ])->assertCreated()->json('data.id');

        $created = AuditActivity::query()->sole();
        $this->assertSame('Farm Cooperative', $created->properties['attributes']['vendor']);
        $this->assertEquals(55.0, $created->properties['attributes']['purchase_price']);
        $this->assertArrayNotHasKey('default_supplier_id', $created->properties['attributes']);
        $createdEvent = app(AuditEventPresenter::class)
            ->present($created->load('causer'), $admin)
            ->toArray();
        $this->assertStringContainsString('food service item: Brown rice', $createdEvent['summary']);
        $this->assertSame('currency', collect($createdEvent['changes'])->firstWhere('field', 'purchase_price')['after']['type']);

        $this->patchJson("/api/fss/fs-items/{$id}", ['purchase_price' => 60])->assertOk();
        $updated = AuditActivity::query()->where('event', 'updated')->sole();
        $this->assertSame(['purchase_price', 'unit_cost'], $updated->properties['details']['changed_fields']);
        $this->assertEquals(55.0, $updated->properties['old']['purchase_price']);
        $this->assertEquals(60.0, $updated->properties['attributes']['purchase_price']);

        $this->deleteJson("/api/fss/fs-items/{$id}")->assertNoContent();
        $deleted = AuditActivity::query()->where('event', 'deleted')->sole();
        $this->assertSame('Brown rice', $deleted->properties['old']['name']);
        $this->assertNull($deleted->properties['attributes']['name']);
    }

    public function test_supplier_crud_exposes_safe_values_but_not_notes(): void
    {
        $user = User::factory()->rnd()->create();
        $admin = User::factory()->admin()->create();

        $id = $this->actingAs($user)->postJson('/api/fss/suppliers', [
            'name' => 'Safe Foods Inc',
            'category' => 'Produce',
            'contact' => '09171234567',
            'address' => 'Public market district',
            'payment_terms' => 'Net 30',
            'notes' => 'SUPPLIER-NOTES-SENTINEL',
        ])->assertCreated()->json('data.id');

        $created = AuditActivity::query()->sole();
        $this->assertSame('Safe Foods Inc', $created->properties['attributes']['name']);
        $this->assertSame('Net 30', $created->properties['attributes']['payment_terms']);
        $this->assertStringNotContainsString('SUPPLIER-NOTES-SENTINEL', $created->properties->toJson());
        $event = app(AuditEventPresenter::class)->present($created->load('causer'), $admin)->toArray();
        $this->assertStringContainsString('supplier: Safe Foods Inc', $event['summary']);

        $this->patchJson("/api/fss/suppliers/{$id}", [
            'category' => 'Dry Goods',
            'notes' => 'UPDATED-NOTES-SENTINEL',
        ])->assertOk();
        $updated = AuditActivity::query()->where('event', 'updated')->sole();
        $this->assertSame('Produce', $updated->properties['old']['category']);
        $this->assertSame('Dry Goods', $updated->properties['attributes']['category']);
        $this->assertStringNotContainsString('UPDATED-NOTES-SENTINEL', $updated->properties->toJson());

        $this->deleteJson("/api/fss/suppliers/{$id}")->assertNoContent();
        $deleted = AuditActivity::query()->where('event', 'deleted')->sole();
        $this->assertSame('Safe Foods Inc', $deleted->properties['old']['name']);
        $this->assertNull($deleted->properties['attributes']['name']);
    }

    public function test_ai_usage_limit_event_contains_only_safe_changed_field_labels(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->putJson('/api/admin/ai-usage-limits', [
            'daily_token_limit' => 1000,
            'monthly_token_limit' => 20000,
            'input_cost_per_1m_tokens_usd' => 1.25,
            'output_cost_per_1m_tokens_usd' => 5.75,
        ])->assertOk();

        $activity = AuditActivity::query()->sole();
        $this->assertSame('settings_changed', $activity->event);
        $this->assertSame('system', $activity->domain->value);
        $this->assertSame([
            'daily_token_limit',
            'input_cost_per_1m_tokens_usd',
            'monthly_token_limit',
            'output_cost_per_1m_tokens_usd',
        ], $activity->properties['details']['changed_fields']);
    }

    public function test_menu_cycle_and_template_events_keep_days_as_a_structural_label_only(): void
    {
        $user = User::factory()->rnd()->create();
        $item = FsItem::factory()->create();
        AuditFixture::delete(AuditActivity::query());
        $days = collect(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])
            ->map(fn (string $day): array => [
                'day_of_week' => $day,
                'meal_type' => 'breakfast',
                'fs_item_id' => $item->uuid,
                'quantity' => 123.45,
                'estimate_population' => 10,
            ])->all();

        $cycleId = $this->actingAs($user)->postJson('/api/fss/menu-cycles', [
            'name' => 'Structural cycle',
            'days' => $days,
        ])->assertCreated()->json('data.id');
        $templateId = $this->actingAs($user)->postJson('/api/fss/menu-cycle-templates', [
            'name' => 'Structural template',
            'days' => $days,
        ])->assertCreated()->json('data.id');
        $this->actingAs($user)->patchJson("/api/fss/menu-cycle-templates/{$templateId}", [
            'days' => $days,
        ])->assertOk();
        $this->actingAs($user)->patchJson("/api/fss/menu-cycles/{$cycleId}", [
            'days' => $days,
        ])->assertOk();

        $fromCycleId = $this->actingAs($user)->postJson("/api/fss/menu-cycles/{$cycleId}/save-template", [
            'name' => 'Copied structural template',
        ])->assertCreated()->json('data.id');
        $instantiatedId = $this->actingAs($user)->postJson("/api/fss/menu-cycle-templates/{$fromCycleId}/instantiate", [
            'name' => 'Instantiated structural cycle',
        ])->assertCreated()->json('data.id');
        $this->actingAs($user)->patchJson("/api/fss/menu-cycles/{$cycleId}/activate")->assertOk();
        $this->actingAs($user)->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => now()->startOfWeek()->toDateString(),
            'end_date' => now()->endOfWeek()->toDateString(),
            'estimate_population' => 10,
        ])->assertCreated();
        $this->actingAs($user)->deleteJson("/api/fss/menu-cycles/{$instantiatedId}")->assertNoContent();
        $this->actingAs($user)->deleteJson("/api/fss/menu-cycle-templates/{$templateId}")->assertNoContent();
        $this->actingAs($user)->deleteJson("/api/fss/menu-cycle-templates/{$fromCycleId}")->assertNoContent();

        $activities = AuditActivity::query()->orderBy('id')->get();
        $this->assertCount(9, $activities);
        foreach ($activities->where('event', 'created') as $activity) {
            $this->assertContains('days', $activity->properties['details']['changed_fields']);
        }
        $serialized = $activities->toJson();
        $this->assertStringNotContainsString('123.45', $serialized);
        $this->assertStringNotContainsString($item->uuid, $serialized);
    }

    public function test_supplier_fs_item_and_shopping_list_crud_emit_exactly_one_event_per_mutation(): void
    {
        $user = User::factory()->rnd()->create();
        $this->actingAs($user);

        $supplierId = $this->postJson('/api/fss/suppliers', ['name' => 'Audit supplier'])
            ->assertCreated()->json('data.id');
        $this->patchJson("/api/fss/suppliers/{$supplierId}", ['category' => 'Produce'])->assertOk();
        $this->deleteJson("/api/fss/suppliers/{$supplierId}")->assertNoContent();

        $itemId = $this->postJson('/api/fss/fs-items', [
            'name' => 'Audit supply', 'kind' => 'supply', 'base_unit' => 'piece', 'purchase_price' => 10,
        ])->assertCreated()->json('data.id');
        $this->patchJson("/api/fss/fs-items/{$itemId}", ['purchase_price' => 11])->assertOk();
        $this->patchJson("/api/fss/fs-items/{$itemId}/vendor-lock", ['locked' => true])->assertOk();

        $listId = $this->postJson('/api/fss/shopping-lists', [
            'name' => 'Audit list', 'procurement_track' => 'supplies',
        ])->assertCreated()->json('data.id');
        $lineId = $this->postJson("/api/fss/shopping-lists/{$listId}/items", [
            'fs_item_id' => $itemId, 'qty' => 2,
        ])->assertCreated()->json('data.id');
        $this->patchJson("/api/fss/shopping-list-items/{$lineId}", ['qty' => 3])->assertOk();
        $this->deleteJson("/api/fss/shopping-list-items/{$lineId}")->assertNoContent();
        $this->patchJson("/api/fss/shopping-lists/{$listId}", ['name' => 'Audit list updated'])->assertOk();
        $this->deleteJson("/api/fss/shopping-lists/{$listId}")->assertNoContent();
        $this->deleteJson("/api/fss/fs-items/{$itemId}")->assertNoContent();

        $this->assertSame(
            ['created', 'updated', 'deleted', 'created', 'updated', 'updated', 'created', 'updated', 'updated', 'updated', 'updated', 'deleted', 'deleted'],
            AuditActivity::query()->orderBy('id')->pluck('event')->all(),
        );
        $this->assertSame(13, AuditActivity::query()->count());
    }

    public function test_announcement_update_delete_and_admin_store_each_emit_one_sanitized_event(): void
    {
        $rnd = User::factory()->rnd()->create();
        $payload = ['title' => 'Audit post', 'body' => 'SECRET-BODY', 'category' => 'General', 'visibility' => 'All'];
        $id = $this->actingAs($rnd)->postJson('/api/rnd/announcements', $payload)->assertCreated()->json('data.id');
        $this->patchJson("/api/rnd/announcements/{$id}", ['title' => 'Updated audit post'])->assertOk();
        $this->deleteJson("/api/rnd/announcements/{$id}")->assertNoContent();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/api/admin/announcements', $payload)->assertCreated();

        $this->assertSame(['created', 'updated', 'deleted', 'created'], AuditActivity::query()->orderBy('id')->pluck('event')->all());
        $this->assertStringNotContainsString('SECRET-BODY', AuditActivity::query()->get()->toJson());
    }

    public function test_usda_import_logs_success_once_and_does_not_log_failure(): void
    {
        $user = User::factory()->rnd()->create();
        $food = FoodItem::factory()->create(['usda_fdc_id' => 123]);
        $usda = Mockery::mock(UsdaService::class);
        $usda->shouldReceive('prepareImport')->once()->with(123)->andReturn(['usda_fdc_id' => 123]);
        $usda->shouldReceive('persistImport')->once()->with(['usda_fdc_id' => 123])->andReturn($food);
        $usda->shouldReceive('prepareImport')->once()->with(124)->andThrow(new RuntimeException('upstream failed'));
        $this->app->instance(UsdaService::class, $usda);

        $this->actingAs($user)->postJson('/api/rnd/usda/import/123')->assertCreated();
        $this->postJson('/api/rnd/usda/import/124')->assertStatus(502);

        $activity = AuditActivity::query()->sole();
        $this->assertSame('imported', $activity->event);
        $this->assertSame([
            'calories',
            'carbs',
            'category',
            'fat',
            'name',
            'protein',
            'serving_size',
            'serving_unit',
            'unit_price',
            'usda_fdc_id',
        ], $activity->properties['details']['changed_fields']);
        $this->assertSame('usda', $activity->properties['details']['source']);
        $this->assertSame(123, $activity->properties['attributes']['usda_fdc_id']);
    }

    public function test_same_value_food_item_update_emits_no_event(): void
    {
        $user = User::factory()->rnd()->create();
        $food = FoodItem::factory()->create(['name' => 'Same', 'calories' => 100]);

        $this->actingAs($user)->putJson("/api/rnd/food-items/{$food->uuid}", [
            'name' => 'Same', 'calories' => 100,
        ])->assertOk();

        $this->assertSame(0, AuditActivity::query()->count());
    }

    #[DataProvider('rollbackMutationCases')]
    public function test_audit_failure_rolls_back_simple_and_nested_mutations(string $case): void
    {
        $user = User::factory()->rnd()->create();
        $food = FoodItem::factory()->create(['name' => 'Original']);
        $recipe = Recipe::factory()->create(['rnd_user_id' => $user->id, 'name' => 'Original recipe']);
        $logger = Mockery::mock(AuditLogger::class);
        $logger->shouldReceive('assertAvailable')->once();
        $logger->shouldReceive('recordMutation')->once()->andThrow(new RuntimeException('audit unavailable'));
        $this->app->instance(AuditLogger::class, $logger);

        $response = match ($case) {
            'create' => $this->actingAs($user)->postJson('/api/rnd/food-items', ['name' => 'Rollback create', 'calories' => 10]),
            'update' => $this->actingAs($user)->putJson("/api/rnd/food-items/{$food->uuid}", ['name' => 'Rollback update', 'calories' => 10]),
            'delete' => $this->actingAs($user)->deleteJson("/api/rnd/food-items/{$food->uuid}"),
            'nested' => $this->actingAs($user)->putJson("/api/rnd/recipes/{$recipe->uuid}", [
                'name' => 'Rollback recipe',
                'ingredients' => [['food_item_id' => $food->uuid, 'quantity' => 2, 'unit' => 'g']],
            ]),
        };
        $response->assertStatus(500);

        match ($case) {
            'create' => $this->assertDatabaseMissing('food_items', ['name' => 'Rollback create']),
            'update' => $this->assertDatabaseHas('food_items', ['id' => $food->id, 'name' => 'Original']),
            'delete' => $this->assertDatabaseHas('food_items', ['id' => $food->id]),
            'nested' => $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'name' => 'Original recipe']),
        };
        if ($case === 'nested') {
            $this->assertDatabaseMissing('recipe_ingredients', ['recipe_id' => $recipe->id]);
        }
    }

    public static function rollbackMutationCases(): array
    {
        return [['create'], ['update'], ['delete'], ['nested']];
    }

    #[DataProvider('unavailableAuditConfigurations')]
    public function test_unavailable_or_cross_connection_audit_configuration_fails_before_mutation(array $config): void
    {
        $user = User::factory()->rnd()->create();
        config($config);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->postJson('/api/rnd/food-items', [
                'name' => 'Must roll back unavailable audit', 'calories' => 10,
            ]);
            $this->fail('Expected audit configuration failure.');
        } catch (AuditLoggingUnavailable) {
        }

        $this->assertDatabaseMissing('food_items', ['name' => 'Must roll back unavailable audit']);
    }

    public static function unavailableAuditConfigurations(): array
    {
        return [
            'disabled logger' => [['activitylog.enabled' => false]],
            'different connection' => [['activitylog.database_connection' => 'audit-secondary']],
        ];
    }

    public function test_shopping_list_item_mutation_uses_parent_event_and_missing_parent_is_safe(): void
    {
        $user = User::factory()->rnd()->create();
        $item = FsItem::factory()->create(['kind' => 'supply']);
        AuditFixture::delete(AuditActivity::query());
        $listId = $this->actingAs($user)->postJson('/api/fss/shopping-lists', [
            'name' => 'Context list', 'procurement_track' => 'supplies',
        ])->assertCreated()->json('data.id');
        $lineId = $this->postJson("/api/fss/shopping-lists/{$listId}/items", [
            'fs_item_id' => $item->uuid, 'qty' => 1,
        ])->assertCreated()->json('data.id');

        $list = ShoppingList::where('uuid', $listId)->firstOrFail();
        $line = ShoppingListItem::where('uuid', $lineId)->firstOrFail();
        $activity = AuditActivity::query()
            ->where('subject_type', ShoppingList::class)
            ->where('event', 'updated')
            ->sole();
        $this->assertSame($list->id, $activity->subject_id);
        $this->assertSame(ShoppingList::class, $activity->context_type);
        $this->assertSame($list->id, $activity->context_id);
        $this->assertSame($list->uuid, $activity->properties['details']['context_public_id']);
        $this->assertSame(['items'], $activity->properties['details']['changed_fields']);
        $this->assertNotNull($activity->revision);

        $ghost = new ShoppingListItem(['shopping_list_id' => 999999]);
        $ghost->id = $line->id + 999999;
        $ghost->exists = true;
        $this->assertNull(app(AuditContextResolver::class)->resolve($ghost));
    }

    public function test_rejected_task4_controller_commands_emit_only_security_denials(): void
    {
        $rnd = User::factory()->rnd()->create();
        $other = User::factory()->rnd()->create();
        $fss = User::factory()->fss()->create();
        $adminOnly = User::factory()->admin()->create();
        $announcement = Announcement::create([
            'user_id' => $other->id,
            'title' => 'Owned elsewhere',
            'body' => 'private',
            'category' => 'General',
            'visibility' => 'All',
        ]);

        $cases = [
            'supplier authorization' => [fn () => $this->actingAs($fss)->postJson('/api/fss/suppliers', [])->assertForbidden(), 1],
            'recipe validation' => [fn () => $this->actingAs($rnd)->postJson('/api/rnd/recipes', [])->assertUnprocessable(), 0],
            'announcement ownership' => [fn () => $this->actingAs($rnd)->patchJson("/api/rnd/announcements/{$announcement->uuid}", ['title' => 'blocked'])->assertForbidden(), 1],
            'fs item validation' => [fn () => $this->actingAs($rnd)->postJson('/api/fss/fs-items', [])->assertUnprocessable(), 0],
            'shopping list validation' => [fn () => $this->actingAs($rnd)->postJson('/api/fss/shopping-lists', [])->assertUnprocessable(), 0],
            'menu cycle validation' => [fn () => $this->actingAs($rnd)->postJson('/api/fss/menu-cycles', ['cycle_days' => 0])->assertUnprocessable(), 0],
            'template validation' => [fn () => $this->actingAs($rnd)->postJson('/api/fss/menu-cycle-templates', [])->assertUnprocessable(), 0],
            'AI setting authorization' => [fn () => $this->actingAs($rnd)->putJson('/api/admin/ai-usage-limits', [])->assertForbidden(), 1],
            'SOP authorization' => [fn () => $this->actingAs($fss)->postJson('/api/sop', ['title' => 'x', 'body' => 'y'])->assertForbidden(), 1],
        ];

        foreach ($cases as $label => [$command, $expectedEvents]) {
            AuditFixture::delete(AuditActivity::query());
            $command();
            $this->assertSame($expectedEvents, AuditActivity::query()->count(), $label);
            if ($expectedEvents === 1) {
                $this->assertSame('authorization_denied', AuditActivity::query()->sole()->event, $label);
            }
        }
    }

    public function test_content_only_updates_emit_one_neutral_label_event_without_content(): void
    {
        $user = User::factory()->rnd()->create();
        $announcement = Announcement::create([
            'user_id' => $user->id, 'title' => 'Existing', 'body' => 'old', 'category' => 'General', 'visibility' => 'All',
        ]);
        $recipe = Recipe::factory()->create(['rnd_user_id' => $user->id, 'prep_notes' => 'old']);
        $supplier = Supplier::create(['name' => 'Existing supplier', 'notes' => 'old']);
        $template = MenuCycleTemplate::create(['rnd_user_id' => $user->id, 'name' => 'Existing template', 'description' => 'old']);

        $sentinels = [
            'ANNOUNCEMENT-BODY-SENTINEL', 'private/ANNOUNCEMENT-FILE-SENTINEL.pdf',
            'RECIPE-NOTES-SENTINEL', 'SUPPLIER-NOTES-SENTINEL', 'TEMPLATE-DESCRIPTION-SENTINEL',
        ];
        $this->actingAs($user)->patchJson("/api/rnd/announcements/{$announcement->uuid}", [
            'body' => $sentinels[0], 'attachments' => [$sentinels[1]],
        ])->assertOk();
        $this->putJson("/api/rnd/recipes/{$recipe->uuid}", ['prep_notes' => $sentinels[2]])->assertOk();
        $this->patchJson("/api/fss/suppliers/{$supplier->uuid}", ['notes' => $sentinels[3]])->assertOk();
        $this->patchJson("/api/fss/menu-cycle-templates/{$template->uuid}", ['description' => $sentinels[4]])->assertOk();

        $activities = AuditActivity::query()->orderBy('id')->get();
        $this->assertCount(4, $activities);
        $this->assertSame(['attachment', 'content'], $activities[0]->properties['details']['changed_fields']);
        foreach ($activities->slice(1) as $activity) {
            $this->assertSame(['content'], $activity->properties['details']['changed_fields']);
        }
        foreach ($sentinels as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $activities->toJson());
        }
    }
}
