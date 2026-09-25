<?php

namespace Tests\Feature;

use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\InterventionRevisionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    private function intervention(array $attributes = []): Intervention
    {
        return Intervention::factory()->create([
            'ncp_record_id' => $this->ncpRecord->id,
            'goal_type' => 'custom',
            'disease_stage' => null,
            'energy_kcal' => 1800,
            'protein_g' => 70,
            ...$attributes,
        ]);
    }

    public function test_initial_api_save_creates_public_version_one_snapshot(): void
    {
        Diagnosis::factory()->create(['ncp_record_id' => $this->ncpRecord->id]);

        $response = $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$this->ncpRecord->uuid}/intervention", [
                'goal_type' => 'custom',
                'energy_kcal' => 1800,
                'protein_g' => 70,
                'carbs_g' => 240,
                'fat_g' => 60,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.revision.version', 1)
            ->assertJsonPath('data.revision.source', 'initial')
            ->assertJsonPath('data.revision.snapshot.energy_kcal', '1800.00');

        $revisionId = $response->json('data.revision.id');
        $this->assertTrue(Str::isUuid($revisionId));
        $this->assertDatabaseHas('intervention_revisions', [
            'intervention_id' => Intervention::firstOrFail()->id,
            'version' => 1,
            'source' => 'initial',
        ]);
    }

    public function test_edit_before_monitoring_updates_version_one_snapshot(): void
    {
        $intervention = $this->intervention();
        $service = app(InterventionRevisionService::class);
        $revision = $service->createInitial($intervention, $this->rnd);

        $service->updateInitialBeforeMonitoring($intervention, [
            'energy_kcal' => 2000,
            'education_notes' => 'Updated education plan',
        ], $this->rnd);

        $this->assertSame(1, $intervention->revisions()->count());
        $this->assertSame('2000.00', $intervention->fresh()->energy_kcal);
        $this->assertSame('2000.00', $revision->fresh()->snapshot['energy_kcal']);
        $this->assertSame('Updated education plan', $revision->fresh()->snapshot['education_notes']);
    }

    public function test_direct_api_edit_after_monitoring_is_rejected_with_workflow_message(): void
    {
        $intervention = $this->intervention();
        app(InterventionRevisionService::class)->createInitial($intervention, $this->rnd);
        Monitoring::factory()->create(['ncp_record_id' => $this->ncpRecord->id]);

        $this->actingAs($this->rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$this->ncpRecord->uuid}/intervention", [
                'energy_kcal' => 2100,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Record prescription changes through a Monitoring follow-up.');

        $this->assertSame('1800.00', $intervention->fresh()->energy_kcal);
        $this->assertSame(1, $intervention->revisions()->count());

        $this->expectException(ValidationException::class);
        $intervention->fresh()->update(['energy_kcal' => 2200]);
    }

    public function test_service_creates_sequential_immutable_versions_and_links_monitorings(): void
    {
        $intervention = $this->intervention();
        $service = app(InterventionRevisionService::class);
        $versionOne = $service->createInitial($intervention, $this->rnd);
        $firstMonitoring = Monitoring::factory()->create(['ncp_record_id' => $this->ncpRecord->id]);
        $secondMonitoring = Monitoring::factory()->create(['ncp_record_id' => $this->ncpRecord->id]);

        $versionTwo = $service->reviseFromMonitoring(
            $intervention,
            $firstMonitoring,
            ['energy_kcal' => 1900],
            $this->rnd,
            'Energy target adjusted after review',
        );
        $versionThree = $service->reviseFromMonitoring(
            $intervention,
            $secondMonitoring,
            ['protein_g' => 80],
            $this->rnd,
            'Protein target adjusted after review',
        );

        $this->assertSame([1, 2, 3], $intervention->revisions()->orderBy('version')->pluck('version')->all());
        $this->assertSame($versionTwo->id, $firstMonitoring->fresh()->intervention_revision_id);
        $this->assertSame($versionThree->id, $secondMonitoring->fresh()->intervention_revision_id);
        $this->assertSame('1900.00', $versionThree->snapshot['energy_kcal']);
        $this->assertSame('80.00', $versionThree->snapshot['protein_g']);

        $this->expectException(\LogicException::class);
        $versionOne->update(['reason' => 'Rewritten history']);
    }

    public function test_failed_revision_rolls_back_current_values_and_history(): void
    {
        $intervention = $this->intervention();
        $service = app(InterventionRevisionService::class);
        $service->createInitial($intervention, $this->rnd);
        $monitoring = Monitoring::factory()->create(['ncp_record_id' => $this->ncpRecord->id]);
        $missingActor = User::factory()->make();
        $missingActor->id = 999999;

        try {
            $service->reviseFromMonitoring(
                $intervention,
                $monitoring,
                ['energy_kcal' => 2400],
                $missingActor,
                'This transaction must fail',
            );
            $this->fail('Expected the missing actor foreign key to reject the revision.');
        } catch (QueryException) {
            // Expected: the service transaction must restore the current row.
        }

        $this->assertSame('1800.00', $intervention->fresh()->energy_kcal);
        $this->assertSame(1, $intervention->revisions()->count());
        $this->assertNull($monitoring->fresh()->intervention_revision_id);
    }

    public function test_active_for_creates_exactly_one_legacy_baseline_for_unversioned_row(): void
    {
        $intervention = $this->intervention();
        $service = app(InterventionRevisionService::class);

        $first = $service->activeFor($intervention);
        $second = $service->activeFor($intervention);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('legacy_baseline', $first->source);
        $this->assertSame(1, $intervention->revisions()->count());
    }

    public function test_revision_metadata_is_authorized_through_parent_ncp_only(): void
    {
        $intervention = $this->intervention();
        $revision = app(InterventionRevisionService::class)->createInitial($intervention, $this->rnd);

        $this->actingAs($this->rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$this->ncpRecord->uuid}/intervention")
            ->assertOk()
            ->assertJsonPath('data.revision.id', $revision->uuid)
            ->assertJsonMissingPath('data.revision.intervention_id')
            ->assertJsonMissingPath('data.revision.actor_user_id');

        $admin = User::factory()->create(['role' => 'Admin']);
        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$this->ncpRecord->uuid}/intervention")
            ->assertForbidden();
    }
}
