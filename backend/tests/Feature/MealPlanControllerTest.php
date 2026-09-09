<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\MealPlanTemplate;
use App\Models\MealPlanTemplateDay;
use App\Models\MealPlanTemplateItem;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MealPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create([
            'role' => 'RND',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeInterventionWithNcpRecord(): array
    {
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        $intervention = Intervention::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'energy_kcal' => 1800,
            'protein_g' => 70,
            'carbs_g' => 250,
            'fat_g' => 55,
            'fluid_ml' => 2000,
        ]);

        return [$ncpRecord, $intervention, $patient];
    }

    private function seedRecipes(int $count = 10): void
    {
        Recipe::factory($count)->create(['rnd_user_id' => $this->rnd->id]);
        FoodItem::factory(5)->create([
            'category' => 'fruit',
            'ready_to_eat' => true,
            'serving_size' => 100,
            'serving_unit' => 'g',
        ]);
    }

    // --- MealPlan CRUD ---

    public function test_store_meal_plan_manually(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans", [
                'week_start_date' => '2026-06-09',
                'generation_type' => 'manual',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.generation_type', 'manual');

        $this->assertDatabaseHas('meal_plans', [
            'intervention_id' => $intervention->id,
            'generation_type' => 'manual',
        ]);
    }

    public function test_show_meal_plan_with_days(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $mealPlan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$mealPlan->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.id', $mealPlan->uuid)
            ->assertJsonStructure(['data' => ['id', 'week_start_date', 'generation_type', 'days']]);
    }

    public function test_index_meal_plans_for_ncp_record(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        MealPlan::factory(3)->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_update_meal_plan_status(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $mealPlan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$mealPlan->uuid}", [
                'status' => 'active',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('meal_plans', ['id' => $mealPlan->id, 'status' => 'active']);
    }

    public function test_store_meal_plan_requires_week_start_date(): void
    {
        [$ncpRecord] = $this->makeInterventionWithNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans", [
                'generation_type' => 'manual',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['week_start_date']);
    }

    // --- AI auto-generate ---

    public function test_generate_meal_plan_auto_creates_7_days(): void
    {
        [$ncpRecord] = $this->makeInterventionWithNcpRecord();
        $this->seedRecipes(15);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/generate", [
                'week_start_date' => '2026-06-09',
                'conditions' => ['DM'],
                'allergens' => [],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.generation_type', 'auto')
            ->assertJsonStructure(['data' => ['id', 'days']]);

        $days = $response->json('data.days');
        $this->assertIsArray($days);
        $this->assertCount(35, $days); // 7 days × 5 meal types

        $mealPlan = MealPlan::where('intervention_id', '!=', null)->first();
        $this->assertNotNull($mealPlan);
        $this->assertDatabaseHas('activity_log', ['event' => 'generated']);
    }

    public function test_generate_meal_plan_requires_week_start_date(): void
    {
        [$ncpRecord] = $this->makeInterventionWithNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/generate", [
                'conditions' => ['DM'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['week_start_date']);
    }

    public function test_cannot_generate_meal_plan_without_intervention(): void
    {
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/generate", [
                'week_start_date' => '2026-06-09',
                'conditions' => ['DM'],
            ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('activity_log', ['event' => 'generated']);
    }

    public function test_cannot_generate_meal_plan_without_complete_prescription(): void
    {
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        // Intervention exists but has no prescription targets.
        Intervention::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'goal_type' => 'renal_diet',
            'energy_kcal' => null,
            'protein_g' => null,
            'carbs_g' => null,
            'fat_g' => null,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/generate", [
                'week_start_date' => '2026-06-09',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['intervention']]);
    }

    public function test_rnd_can_delete_meal_plan(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);

        $this->actingAs($this->rnd)
            ->deleteJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$plan->uuid}")
            ->assertNoContent();

        $this->assertDatabaseMissing('meal_plans', ['id' => $plan->id]);
    }

    // ─── Template tests ────────────────────────────────────────────────────────

    public function test_rnd_can_save_meal_plan_as_template(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$plan->uuid}/save-template", [
                'name' => 'CKD Stage 4 — Week A',
                'goal_type' => 'renal_diet',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'CKD Stage 4 — Week A');

        $this->assertDatabaseHas('meal_plan_templates', ['name' => 'CKD Stage 4 — Week A']);
    }

    public function test_saving_template_preserves_every_item_and_its_snapshot(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);
        $day = MealPlanDay::factory()->create([
            'meal_plan_id' => $plan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
        ]);
        $rice = FoodItem::factory()->create();
        $fish = FoodItem::factory()->create();
        $riceSnapshot = ['name' => 'Cooked rice', 'calories' => 205.5, 'protein' => 4.3];
        $fishSnapshot = ['name' => 'Paksiw na bangus', 'calories' => 220.5, 'protein' => 27.5];

        MealPlanItem::factory()->create([
            'meal_plan_day_id' => $day->id,
            'food_item_id' => $rice->id,
            'quantity' => 1.25,
            'unit' => 'cup',
            'nutrient_snapshot' => $riceSnapshot,
        ]);
        MealPlanItem::factory()->create([
            'meal_plan_day_id' => $day->id,
            'food_item_id' => $fish->id,
            'quantity' => 1,
            'unit' => 'serving',
            'nutrient_snapshot' => $fishSnapshot,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$plan->uuid}/save-template", [
                'name' => 'Two-item lunch',
                'goal_type' => 'renal_diet',
                'disease_stage' => 'stage_4',
            ])
            ->assertCreated();

        $template = MealPlanTemplate::where('uuid', $response->json('data.id'))->sole();
        $templateDay = $template->days()->where('day_of_week', 'Monday')->where('meal_type', 'lunch')->sole();
        $items = MealPlanTemplateItem::where('template_day_id', $templateDay->id)->orderBy('line_order')->get();

        $this->assertCount(2, $items);
        $this->assertSame(['1.25', '1.00'], $items->pluck('quantity')->all());
        $this->assertSame(['cup', 'serving'], $items->pluck('unit')->all());
        $this->assertEquals($riceSnapshot, $items[0]->nutrient_snapshot);
        $this->assertEquals($fishSnapshot, $items[1]->nutrient_snapshot);
        $this->assertSame('stage_4', $template->disease_stage);
    }

    public function test_rnd_can_list_templates(): void
    {
        MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Template A', 'goal_type' => 'renal_diet',
        ]);

        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/meal-plan-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_rnd_can_create_plan_from_template(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Template A',
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", [
                // template_id is the template's public uuid (its Resource 'id'), which is
                // what the picker submits; the endpoint resolves it server-side.
                'template_id' => $template->uuid,
                'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.generation_type', 'manual');
    }

    public function test_template_detail_delete_and_load_are_owner_scoped(): void
    {
        [$ncpRecord] = $this->makeInterventionWithNcpRecord();
        $otherRnd = User::factory()->create(['role' => 'RND']);
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $otherRnd->id,
            'name' => 'Private template',
        ]);

        $this->actingAs($this->rnd)->getJson("/api/rnd/meal-plan-templates/{$template->uuid}")->assertNotFound();
        $this->actingAs($this->rnd)->deleteJson("/api/rnd/meal-plan-templates/{$template->uuid}")->assertNotFound();
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", [
            'template_id' => $template->uuid,
            'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
        ])->assertNotFound();

        $this->assertDatabaseHas('meal_plan_templates', ['id' => $template->id]);
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_loading_template_copies_all_items_and_warns_when_goal_or_stage_differs(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();
        $intervention->update(['goal_type' => 'diabetes_management', 'disease_stage' => null]);
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'CKD template',
            'goal_type' => 'renal_diet',
            'disease_stage' => 'stage_4',
        ]);
        $templateDay = MealPlanTemplateDay::forceCreate([
            'template_id' => $template->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
        ]);
        MealPlanTemplateItem::forceCreate([
            'template_day_id' => $templateDay->id,
            'quantity' => 1.5,
            'unit' => 'cup',
            'nutrient_snapshot' => ['name' => 'Cooked rice', 'calories' => 205.0],
            'line_order' => 1,
        ]);
        MealPlanTemplateItem::forceCreate([
            'template_day_id' => $templateDay->id,
            'quantity' => 1,
            'unit' => 'serving',
            'nutrient_snapshot' => ['name' => 'Fish', 'calories' => 220.0],
            'line_order' => 2,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", [
                'template_id' => $template->uuid,
                'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('meta.template_compatibility.goal_matches', false)
            ->assertJsonPath('meta.template_compatibility.disease_stage_matches', false);

        $plan = MealPlan::where('uuid', $response->json('data.id'))->sole();
        $items = MealPlanItem::whereHas('mealPlanDay', fn ($query) => $query->where('meal_plan_id', $plan->id))
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $items);
        $this->assertSame(['1.50', '1.00'], $items->pluck('quantity')->all());
        $this->assertSame('Cooked rice', $items[0]->nutrient_snapshot['name']);
        $this->assertSame('Fish', $items[1]->nutrient_snapshot['name']);
    }

    public function test_scale_to_prescription_changes_only_existing_quantities_using_practical_increments(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $intervention->update([
            'energy_kcal' => 1000,
            'protein_g' => 40,
            'carbs_g' => 120,
            'fat_g' => 40,
        ]);
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
            'generation_type' => 'manual',
            'needs_rescaling' => true,
        ]);
        $day = MealPlanDay::factory()->create([
            'meal_plan_id' => $plan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
        ]);
        $snapshot = [
            'name' => 'Test dish',
            'calories' => 250,
            'protein' => 10,
            'carbs' => 30,
            'fat' => 10,
            'serving_size' => 100,
            'serving_unit' => 'g',
            'micronutrients' => [],
        ];
        MealPlanItem::factory()->count(2)->create([
            'meal_plan_day_id' => $day->id,
            'quantity' => 100,
            'unit' => 'g',
            'nutrient_snapshot' => $snapshot,
        ]);
        $originalIds = $day->items()->orderBy('id')->pluck('id')->all();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$plan->uuid}/scale-to-prescription")
            ->assertOk()
            ->assertJsonPath('data.scale_status', 'already_scaled')
            ->assertJsonPath('meta.scaling.inserted_items', 0)
            ->assertJsonPath('meta.scaling.substituted_items', 0);

        $this->assertSame($originalIds, $day->items()->orderBy('id')->pluck('id')->all());
        $this->assertSame(['200.00', '200.00'], $day->items()->orderBy('id')->pluck('quantity')->all());
        $this->assertFalse($plan->fresh()->needs_rescaling);
        $this->assertNotNull($plan->fresh()->scaled_at);
    }

    public function test_unchanged_auto_generated_plan_rejects_meaningless_scaling(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
            'generation_type' => 'auto',
            'needs_rescaling' => false,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$plan->uuid}/scale-to-prescription")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This automatically generated plan is already scaled to the current prescription.');
    }

    public function test_plan_from_template_fails_closed_without_partial_graph_when_audit_unavailable(): void
    {
        [$ncpRecord] = $this->makeInterventionWithNcpRecord();
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Atomic template',
        ]);
        MealPlanTemplateDay::forceCreate([
            'template_id' => $template->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'breakfast',
            'quantity' => 1,
            'unit' => 'serving',
        ]);
        config(['activitylog.enabled' => false]);

        $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", [
                'template_id' => $template->uuid,
                'week_start_date' => '2026-07-13',
            ])->assertServerError();

        $this->assertDatabaseCount('meal_plans', 0);
        $this->assertDatabaseCount('meal_plan_days', 0);
        $this->assertDatabaseCount('meal_plan_items', 0);
    }
}
