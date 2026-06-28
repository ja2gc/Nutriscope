<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlan;
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
            'role'     => 'RND',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeInterventionWithNcpRecord(): array
    {
        $patient       = Patient::factory()->create();
        $ncpRecord     = NcpRecord::factory()->create([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        $intervention  = Intervention::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'energy_kcal'   => 1800,
            'protein_g'     => 70,
            'carbs_g'       => 250,
            'fat_g'         => 55,
            'fluid_ml'      => 2000,
        ]);
        return [$ncpRecord, $intervention, $patient];
    }

    private function seedRecipes(int $count = 10): void
    {
        Recipe::factory($count)->create(['rnd_user_id' => $this->rnd->id]);
    }

    // --- MealPlan CRUD ---

    public function test_store_meal_plan_manually(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans", [
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
            'patient_id'      => $patient->id,
        ]);

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans/{$mealPlan->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $mealPlan->id)
            ->assertJsonStructure(['data' => ['id', 'week_start_date', 'generation_type', 'days']]);
    }

    public function test_index_meal_plans_for_ncp_record(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        MealPlan::factory(3)->create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $patient->id,
        ]);

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_update_meal_plan_status(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $mealPlan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $patient->id,
            'status'          => 'draft',
        ]);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans/{$mealPlan->id}", [
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
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans", [
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
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans/generate", [
                'week_start_date' => '2026-06-09',
                'conditions'      => ['DM'],
                'allergens'       => [],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.generation_type', 'auto')
            ->assertJsonStructure(['data' => ['id', 'days']]);

        $days = $response->json('data.days');
        $this->assertIsArray($days);
        $this->assertCount(35, $days); // 7 days × 5 meal types

        $mealPlan = MealPlan::where('intervention_id', '!=', null)->first();
        $this->assertNotNull($mealPlan);
    }

    public function test_generate_meal_plan_requires_week_start_date(): void
    {
        [$ncpRecord] = $this->makeInterventionWithNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans/generate", [
                'conditions' => ['DM'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['week_start_date']);
    }

    public function test_cannot_generate_meal_plan_without_intervention(): void
    {
        $patient   = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans/generate", [
                'week_start_date' => '2026-06-09',
                'conditions'      => ['DM'],
            ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_generate_meal_plan_without_complete_prescription(): void
    {
        $patient   = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        // Intervention exists but has no prescription targets.
        Intervention::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'goal_type'     => 'renal_diet',
            'energy_kcal'   => null,
            'protein_g'     => null,
            'carbs_g'       => null,
            'fat_g'         => null,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans/generate", [
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
            'patient_id'      => $patient->id,
        ]);

        $this->actingAs($this->rnd)
            ->deleteJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans/{$plan->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('meal_plans', ['id' => $plan->id]);
    }

    // ─── Template tests ────────────────────────────────────────────────────────

    public function test_rnd_can_save_meal_plan_as_template(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $patient->id,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans/{$plan->id}/save-template", [
                'name'      => 'CKD Stage 4 — Week A',
                'goal_type' => 'renal_diet',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'CKD Stage 4 — Week A');

        $this->assertDatabaseHas('meal_plan_templates', ['name' => 'CKD Stage 4 — Week A']);
    }

    public function test_rnd_can_list_templates(): void
    {
        \App\Models\MealPlanTemplate::forceCreate([
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
        $template = \App\Models\MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Template A',
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->id}/meal-plans/from-template", [
                'template_id'     => $template->id,
                'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.generation_type', 'manual');
    }
}
