<?php

namespace Tests\Feature\Audit;

use App\Models\Assessment;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ScreeningDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedRndClinicalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_rnd_can_write_every_clinical_section_of_another_rnds_ncp(): void
    {
        Storage::fake('local');
        $creator = User::factory()->rnd()->create();
        $actor = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $creator->id,
        ]);
        Assessment::factory()->create([
            'ncp_record_id' => $ncp->id,
            'physical_activity_level' => 'sedentary',
        ]);
        $intervention = Intervention::factory()->create([
            'ncp_record_id' => $ncp->id,
            'goal_type' => 'custom',
            'disease_stage' => null,
            'education_notes' => 'Original education notes',
        ]);
        $mealPlan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
            'status' => 'draft',
        ]);
        $monitoring = Monitoring::factory()->create([
            'ncp_record_id' => $ncp->id,
            'weight' => 60,
        ]);
        $day = MealPlanDay::factory()->create([
            'meal_plan_id' => $mealPlan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'breakfast',
        ]);
        $item = MealPlanItem::factory()->create([
            'meal_plan_day_id' => $day->id,
            'quantity' => 100,
            'unit' => 'g',
        ]);
        $upload = $this->actingAs($creator, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/attachments", [
                'file' => UploadedFile::fake()->create('handover.pdf', 10, 'application/pdf'),
            ])->assertCreated();
        $document = ScreeningDocument::query()->where('uuid', $upload->json('data.id'))->firstOrFail();

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment")
            ->assertOk();
        $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/intervention")->assertOk();
        $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans")->assertOk();
        $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/{$mealPlan->uuid}")->assertOk();
        $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings")->assertOk();
        $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/{$mealPlan->uuid}/days/{$day->uuid}/items")
            ->assertOk();
        $this->getJson("/api/rnd/screening-documents/{$document->uuid}")->assertOk();
        $this->get("/api/rnd/screening-documents/{$document->uuid}/file")->assertOk();

        $this->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
            'physical_activity_level' => 'light',
        ])->assertOk();

        $this->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/intervention", [
            'goal_type' => 'custom',
            'disease_stage' => null,
            'education_notes' => 'Updated by the covering RND',
        ])->assertOk();

        $this->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/{$mealPlan->uuid}", [
            'status' => 'active',
        ])->assertOk();
        $this->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/{$mealPlan->uuid}/days/{$day->uuid}/items/{$item->uuid}", [
            'quantity' => 125,
        ])->assertOk();

        $this->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings/{$monitoring->uuid}", [
            'weight' => 61,
        ])->assertOk();

        $this->deleteJson("/api/rnd/screening-documents/{$document->uuid}")->assertOk();

        $this->assertSame('active', $mealPlan->fresh()->status);
        $this->assertSame(125.0, (float) $item->fresh()->quantity);
        $this->assertSame(61.0, (float) $monitoring->fresh()->weight);

        $this->deleteJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/{$mealPlan->uuid}/days/{$day->uuid}/items/{$item->uuid}")
            ->assertNoContent();
        $this->deleteJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings/{$monitoring->uuid}")
            ->assertNoContent();
        $this->deleteJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/{$mealPlan->uuid}")
            ->assertNoContent();

        $this->assertSame('light', $ncp->assessment->fresh()->physical_activity_level);
        $this->assertSame('Updated by the covering RND', $intervention->fresh()->education_notes);
        $this->assertModelMissing($document);
        $this->assertModelMissing($item);
        $this->assertModelMissing($monitoring);
        $this->assertModelMissing($mealPlan);

        $this->getJson("/api/rnd/patients/{$patient->uuid}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.actor.id', $actor->uuid);
        $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/activity")->assertOk();

        $this->assertSame($creator->id, $ncp->fresh()->rnd_user_id);
    }

    public function test_an_rnd_can_delete_another_rnds_draft_ncp(): void
    {
        $creator = User::factory()->rnd()->create();
        $actor = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $creator->id,
            'status' => 'draft',
        ]);

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/rnd/ncp-records/{$ncp->uuid}")
            ->assertNoContent();

        $this->assertModelMissing($ncp);
    }

    public function test_patient_rows_and_ncp_cards_identify_creator_and_latest_clinical_actor(): void
    {
        $creator = User::factory()->rnd()->create([
            'first_name' => 'Cycle',
            'last_name' => 'Creator',
            'name' => 'Cycle Creator',
            'email' => 'creator-private@example.test',
        ]);
        $actor = User::factory()->rnd()->create([
            'first_name' => 'Latest',
            'last_name' => 'Clinician',
            'name' => 'Latest Clinician',
            'email' => 'actor-private@example.test',
        ]);
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $creator->id,
        ]);
        Assessment::factory()->create([
            'ncp_record_id' => $ncp->id,
            'physical_activity_level' => 'sedentary',
        ]);

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'physical_activity_level' => 'light',
            ])->assertOk();
        $creator->delete();

        $patients = $this->getJson('/api/rnd/patients?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.latest_ncp_created_by.id', $creator->uuid)
            ->assertJsonPath('data.0.latest_ncp_created_by.name', 'Cycle Creator')
            ->assertJsonPath('data.0.latest_ncp_created_by.kind', 'user')
            ->assertJsonPath('data.0.latest_ncp_created_by.role', 'RND')
            ->assertJsonPath('data.0.last_clinical_action.actor.id', $actor->uuid)
            ->assertJsonPath('data.0.last_clinical_action.actor.name', 'Latest Clinician')
            ->assertJsonPath('data.0.last_clinical_action.actor.kind', 'user')
            ->assertJsonPath('data.0.last_clinical_action.actor.role', 'RND')
            ->assertJsonStructure(['data' => [['last_clinical_action' => ['occurred_at']]]]);

        $records = $this->getJson("/api/rnd/patients/{$patient->uuid}/ncp-records")
            ->assertOk()
            ->assertJsonPath('data.0.created_by.id', $creator->uuid)
            ->assertJsonPath('data.0.created_by.name', 'Cycle Creator')
            ->assertJsonPath('data.0.created_by.kind', 'user')
            ->assertJsonPath('data.0.created_by.role', 'RND')
            ->assertJsonPath('data.0.last_clinical_action.actor.id', $actor->uuid)
            ->assertJsonPath('data.0.last_clinical_action.actor.name', 'Latest Clinician')
            ->assertJsonPath('data.0.last_clinical_action.actor.kind', 'user')
            ->assertJsonPath('data.0.last_clinical_action.actor.role', 'RND')
            ->assertJsonStructure(['data' => [['last_clinical_action' => ['occurred_at']]]]);

        $this->assertStringNotContainsString('creator-private@example.test', $patients->getContent());
        $this->assertStringNotContainsString('actor-private@example.test', $patients->getContent());
        $this->assertStringNotContainsString('creator-private@example.test', $records->getContent());
        $this->assertStringNotContainsString('actor-private@example.test', $records->getContent());
    }
}
