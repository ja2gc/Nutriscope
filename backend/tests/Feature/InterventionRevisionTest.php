<?php

namespace Tests\Feature;

use App\Exceptions\AuditLoggingUnavailable;
use App\Models\MealPlan;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\InterventionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InterventionRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    private NcpRecord $ncpRecord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rnd = User::factory()->create(['role' => 'RND']);
        $patient = Patient::factory()->create();
        $this->ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        $this->actingAs($this->rnd, 'sanctum');
    }

    public function test_owner_creates_complete_public_plan_from_monitoring(): void
    {
        $monitoring = Monitoring::factory()->create(['ncp_record_id' => $this->ncpRecord->id]);

        $plan = app(InterventionPlanService::class)->create(
            $this->ncpRecord,
            $this->attributes(),
            $this->rnd,
            $monitoring,
        );

        $this->assertTrue(Str::isUuid($plan->uuid));
        $this->assertSame($this->ncpRecord->id, $plan->ncp_record_id);
        $this->assertSame($monitoring->id, $plan->source_monitoring_id);
        $this->assertTrue($monitoring->interventionPlans->contains($plan));
        $this->assertSame('1800.00', $plan->energy_kcal);
        $this->assertDatabaseCount('intervention_revisions', 0);
    }

    public function test_non_owner_cannot_create_plan(): void
    {
        $otherRnd = User::factory()->create(['role' => 'RND']);

        try {
            app(InterventionPlanService::class)->create(
                $this->ncpRecord,
                $this->attributes(),
                $otherRnd,
            );
            $this->fail('Expected plan ownership validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'The NCP record does not belong to this RND.',
                $exception->errors()['ncp_record'][0],
            );
        }

        $this->assertDatabaseCount('interventions', 0);
    }

    public function test_source_monitoring_must_belong_to_same_ncp_record(): void
    {
        $otherMonitoring = Monitoring::factory()->create();

        try {
            app(InterventionPlanService::class)->create(
                $this->ncpRecord,
                $this->attributes(),
                $this->rnd,
                $otherMonitoring,
            );
            $this->fail('Expected source Monitoring validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'The monitoring record does not belong to this NCP record.',
                $exception->errors()['source_monitoring'][0],
            );
        }

        $this->assertDatabaseCount('interventions', 0);
    }

    public function test_new_plan_never_clones_previous_menu_and_same_timestamp_order_is_stable(): void
    {
        Carbon::setTestNow('2026-09-29 10:00:00');
        $service = app(InterventionPlanService::class);
        $first = $service->create($this->ncpRecord, $this->attributes(), $this->rnd);
        MealPlan::factory()->create([
            'intervention_id' => $first->id,
            'patient_id' => $this->ncpRecord->patient_id,
        ]);

        $second = $service->create($this->ncpRecord, [
            ...$this->attributes(),
            'energy_kcal' => 1900,
        ], $this->rnd);

        $this->assertNull($second->mealPlan);
        $this->assertTrue($this->ncpRecord->fresh()->latestIntervention->is($second));
        $this->assertSame([$second->id, $first->id], $this->ncpRecord->interventions()->latest('created_at')->latest('id')->pluck('id')->all());
    }

    public function test_audit_unavailable_rolls_back_new_plan(): void
    {
        config(['activitylog.enabled' => false]);

        $this->expectException(AuditLoggingUnavailable::class);

        try {
            app(InterventionPlanService::class)->create(
                $this->ncpRecord,
                $this->attributes(),
                $this->rnd,
            );
        } finally {
            $this->assertDatabaseCount('interventions', 0);
        }
    }

    /** @return array<string, mixed> */
    private function attributes(): array
    {
        return [
            'goal_type' => 'custom',
            'disease_stage' => null,
            'displayed_nutrients' => ['energy', 'protein'],
            'energy_kcal' => 1800,
            'protein_g' => 70,
            'carbs_g' => 240,
            'fat_g' => 60,
            'fluid_ml' => 1800,
            'micronutrient_limits' => [],
            'education_notes' => 'Education',
            'counseling_goals' => 'Goals',
            'barriers' => 'Barriers',
            'strategies' => 'Strategies',
            'session_type' => 'follow-up',
            'next_followup_date' => '2026-10-15',
        ];
    }
}
