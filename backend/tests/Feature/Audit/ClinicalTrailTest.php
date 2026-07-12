<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Models\Assessment;
use App\Models\AuditActivity;
use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ScreeningDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ClinicalTrailTest extends TestCase
{
    use RefreshDatabase;

    public static function clinicalModels(): array
    {
        return [
            'patient' => [Patient::class, 'ward', 'PATIENT-CREATE-SENTINEL', 'PATIENT-UPDATE-SENTINEL'],
            'ncp record' => [NcpRecord::class, 'risk_score_manual_factors', ['NCP-CREATE-SENTINEL'], ['NCP-UPDATE-SENTINEL']],
            'assessment' => [Assessment::class, 'rnd_summary', 'ASSESSMENT-CREATE-SENTINEL', 'ASSESSMENT-UPDATE-SENTINEL'],
            'diagnosis' => [Diagnosis::class, 'extra_notes', 'DIAGNOSIS-CREATE-SENTINEL', 'DIAGNOSIS-UPDATE-SENTINEL'],
            'intervention' => [Intervention::class, 'education_notes', 'INTERVENTION-CREATE-SENTINEL', 'INTERVENTION-UPDATE-SENTINEL'],
            'meal plan' => [MealPlan::class, 'week_start_date', '2099-01-01', '2099-01-08'],
            'monitoring' => [Monitoring::class, 'clinical_summary', 'MONITORING-CREATE-SENTINEL', 'MONITORING-UPDATE-SENTINEL'],
            'screening document' => [ScreeningDocument::class, 'type', 'DOCUMENT-CREATE-SENTINEL', 'DOCUMENT-UPDATE-SENTINEL'],
        ];
    }

    #[DataProvider('clinicalModels')]
    public function test_every_clinical_model_create_update_delete_is_rooted_and_phi_free(
        string $modelClass,
        string $field,
        mixed $createValue,
        mixed $updateValue,
    ): void {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id, 'status' => 'draft']);
        $this->actingAs($rnd, 'sanctum');
        AuditActivity::query()->delete();
        $createSentinel = is_array($createValue) ? (string) reset($createValue) : (string) $createValue;
        $updateSentinel = is_array($updateValue) ? (string) reset($updateValue) : (string) $updateValue;
        $subject = $this->createClinicalSubject($modelClass, $field, $createValue, $patient, $ncp);
        if ($subject instanceof Patient) {
            $patient = $subject;
            NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        } elseif ($subject instanceof NcpRecord) {
            $ncp = $subject;
        }
        $subject->update([$field => $updateValue]);

        $route = $subject instanceof NcpRecord
            ? "/api/rnd/ncp-records/{$subject->uuid}/activity"
            : "/api/rnd/patients/{$patient->uuid}/activity";
        $response = $this->getJson($route)->assertOk();
        foreach ([$createSentinel, $updateSentinel] as $sentinel) {
            $this->assertStringNotContainsString($sentinel, AuditActivity::query()->get()->toJson());
            $this->assertStringNotContainsString($sentinel, $response->getContent());
        }
        foreach ($response->json('data') as $event) {
            foreach ($event['changes'] as $change) {
                $this->assertNull($change['old_value']);
                $this->assertNull($change['new_value']);
                $this->assertTrue($change['redacted']);
            }
        }

        $subject->delete();
        $deleted = AuditActivity::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('event', 'deleted')
            ->latest('id')->firstOrFail();
        $this->assertSame($patient->id, (int) $deleted->root_patient_id);
        $this->assertStringNotContainsString($createSentinel, $deleted->toJson());
        $this->assertStringNotContainsString($updateSentinel, $deleted->toJson());
    }

    private function createClinicalSubject(string $modelClass, string $field, mixed $value, Patient $patient, NcpRecord $ncp): Model
    {
        return match ($modelClass) {
            Patient::class => Patient::factory()->create([$field => $value]),
            NcpRecord::class => NcpRecord::factory()->create([
                'patient_id' => $patient->id, 'rnd_user_id' => auth()->id(), $field => $value,
            ]),
            Assessment::class => Assessment::create(['ncp_record_id' => $ncp->id, $field => $value]),
            Diagnosis::class => Diagnosis::create([
                'ncp_record_id' => $ncp->id, 'domain' => 'NI', 'problem' => 'safe',
                'etiology' => 'safe', 'signs_symptoms' => 'safe', 'pes_statement' => 'safe', $field => $value,
            ]),
            Intervention::class => Intervention::create(['ncp_record_id' => $ncp->id, $field => $value]),
            MealPlan::class => MealPlan::create([
                'patient_id' => $patient->id,
                'intervention_id' => Intervention::create(['ncp_record_id' => $ncp->id])->id,
                $field => $value,
            ]),
            Monitoring::class => Monitoring::create(['ncp_record_id' => $ncp->id, $field => $value]),
            ScreeningDocument::class => ScreeningDocument::create([
                'patient_id' => $patient->id, 'ncp_record_id' => $ncp->id,
                'file_path' => 'documents/ncp/missing.pdf', 'original_name' => 'safe.pdf', $field => $value,
            ]),
        };
    }

    public function test_clinical_child_appears_in_patient_and_ncp_trails_without_phi_values(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create(['name' => 'PATIENT-NAME-SENTINEL']);
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        $assessment = Assessment::factory()->create(['ncp_record_id' => $ncp->id]);
        $assessment->activities()->delete();
        $assessment->refresh();
        $this->actingAs($rnd, 'sanctum');

        $assessment->update(['rnd_summary' => 'CLINICAL-VALUE-SENTINEL']);
        $storedUpdate = AuditActivity::query()
            ->where('subject_type', $assessment->getMorphClass())
            ->where('subject_id', $assessment->id)
            ->where('event', AuditAction::Updated->value)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(['rnd_summary'], $storedUpdate->properties['details']['changed_fields']);

        $patientResponse = $this->getJson("/api/rnd/patients/{$patient->uuid}/activity")->assertOk();
        $ncpResponse = $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/activity")->assertOk();

        foreach ([$patientResponse, $ncpResponse] as $response) {
            $response->assertJsonPath('data.0.action', 'updated');
            $response->assertJsonPath('data.0.category', 'clinical');
            $response->assertJsonPath('data.0.domain', 'ncp');
            $this->assertSame(['rnd_summary'], collect($response->json('data.0.changes'))->pluck('field')->all());
            $this->assertSame([
                'id', 'category', 'domain', 'action', 'action_label', 'summary', 'severity', 'outcome',
                'actor', 'subject', 'context', 'occurred_at', 'details', 'changes',
            ], array_keys($response->json('data.0')));
            $this->assertStringNotContainsString('CLINICAL-VALUE-SENTINEL', $response->getContent());
            $this->assertStringNotContainsString('PATIENT-NAME-SENTINEL', $response->getContent());
            $this->assertArrayNotHasKey('properties', $response->json('data.0'));
        }

        $this->assertStringNotContainsString('CLINICAL-VALUE-SENTINEL', AuditActivity::query()->get(['properties'])->toJson());
    }

    public function test_trails_require_resource_access_and_ncp_cross_record_access_is_forbidden(): void
    {
        $owner = User::factory()->rnd()->create();
        $other = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $owner->id]);

        $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/activity")->assertUnauthorized();
        $this->actingAs($other, 'sanctum');
        $this->getJson("/api/rnd/patients/{$patient->uuid}/activity")->assertForbidden();
        $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/activity")->assertForbidden();
    }

    public function test_opening_another_rnds_chart_does_not_grant_patient_trail_access(): void
    {
        $owner = User::factory()->rnd()->create();
        $other = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $owner->id]);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/rnd/patients/{$patient->uuid}")
            ->assertOk();

        $this->getJson("/api/rnd/patients/{$patient->uuid}/activity")->assertForbidden();
    }

    public function test_before_id_cursor_is_stable_newest_first_without_duplicates(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        Assessment::factory()->create(['ncp_record_id' => $ncp->id]);
        AuditActivity::query()->delete();
        $this->actingAs($rnd, 'sanctum');

        foreach (['draft', 'active', 'completed'] as $status) {
            $ncp->update(['status' => $status]);
        }

        $allRows = AuditActivity::query()->orderByDesc('id')->get(['id', 'public_id']);
        $boundary = $allRows[1]->public_id;
        $response = $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/activity?before_id={$boundary}")->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertCount($allRows->filter(fn (AuditActivity $activity): bool => $activity->id < $allRows[1]->id)->count(), $ids);
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/iD', $id);
        }
        $this->assertSame($ids, array_values(array_unique($ids)));
    }

    public function test_chart_open_is_deduplicated_for_fifteen_minutes_per_actor_and_patient(): void
    {
        Cache::clear();
        CarbonImmutable::setTestNow('2026-07-12 08:00:00');
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        AuditActivity::query()->delete();
        $this->actingAs($rnd, 'sanctum');

        try {
            $this->getJson("/api/rnd/patients/{$patient->uuid}")->assertOk();
            CarbonImmutable::setTestNow('2026-07-12 08:14:59');
            $this->getJson("/api/rnd/patients/{$patient->uuid}")->assertOk();

            $this->assertSame(1, AuditActivity::query()
                ->where('event', AuditAction::Viewed->value)
                ->where('subject_type', $patient->getMorphClass())
                ->where('subject_id', $patient->id)
                ->count());

            CarbonImmutable::setTestNow('2026-07-12 08:15:00');
            $this->getJson("/api/rnd/patients/{$patient->uuid}")->assertOk();
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame(2, AuditActivity::query()
            ->where('event', AuditAction::Viewed->value)
            ->where('subject_type', $patient->getMorphClass())
            ->where('subject_id', $patient->id)
            ->count());
    }

    public function test_attachment_downloads_are_never_deduplicated(): void
    {
        Storage::fake('local');
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        Storage::put('documents/ncp/repeat.pdf', 'safe');
        $document = ScreeningDocument::create([
            'patient_id' => $patient->id, 'ncp_record_id' => $ncp->id,
            'file_path' => 'documents/ncp/repeat.pdf', 'original_name' => 'repeat.pdf',
        ]);
        AuditActivity::query()->delete();
        $this->actingAs($rnd, 'sanctum');

        $this->get("/api/rnd/screening-documents/{$document->uuid}/file")->assertOk();
        $this->get("/api/rnd/screening-documents/{$document->uuid}/file")->assertOk();

        $this->assertSame(2, AuditActivity::query()->where('event', AuditAction::Downloaded->value)->count());
    }

    public function test_attachment_upload_view_file_and_delete_emit_safe_rooted_events(): void
    {
        Storage::fake('local');
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create(['name' => 'FILE-NAME-PATIENT-SENTINEL']);
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        $this->actingAs($rnd, 'sanctum');
        AuditActivity::query()->delete();

        $upload = $this->postJson("/api/rnd/ncp-records/{$ncp->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('FILE-NAME-PATIENT-SENTINEL.pdf', 10, 'application/pdf'),
            'type' => 'laboratory',
        ])->assertCreated();
        $document = ScreeningDocument::query()->where('uuid', $upload->json('data.id'))->firstOrFail();

        $this->getJson("/api/rnd/screening-documents/{$document->uuid}")->assertOk();
        $this->get("/api/rnd/screening-documents/{$document->uuid}/file")->assertOk();
        $this->deleteJson("/api/rnd/screening-documents/{$document->uuid}")->assertOk();

        $events = AuditActivity::query()->orderBy('id')->get();
        $this->assertEqualsCanonicalizing(['created', 'uploaded', 'viewed', 'downloaded', 'deleted'], $events->pluck('event')->all());
        $this->assertTrue($events->every(fn (AuditActivity $event): bool => (int) $event->properties['details']['root_patient_id'] === $patient->id
            && (int) $event->properties['details']['ncp_record_id'] === $ncp->id
        ));
        $this->assertStringNotContainsString('FILE-NAME-PATIENT-SENTINEL', $events->toJson());
    }

    public function test_clinical_mutation_fails_closed_when_synchronous_audit_is_unavailable(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create(['ward' => 'Original ward']);
        NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        config(['activitylog.enabled' => false]);

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/patients/{$patient->uuid}", ['ward' => 'Changed without audit'])
            ->assertServerError();

        $this->assertSame('Original ward', $patient->fresh()->ward);
    }

    public function test_deleted_ncp_remains_attributable_to_its_historical_owner_in_patient_trail(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'draft',
        ]);
        AuditActivity::query()->delete();
        $this->actingAs($rnd, 'sanctum');

        $this->deleteJson("/api/rnd/ncp-records/{$ncp->uuid}")->assertNoContent();
        $response = $this->getJson("/api/rnd/patients/{$patient->uuid}/activity")->assertOk();

        $response->assertJsonPath('data.0.action', 'deleted');
        $activity = AuditActivity::query()->where('event', 'deleted')->latest('id')->firstOrFail();
        $this->assertSame($patient->id, $activity->properties['details']['root_patient_id']);
        $this->assertSame($rnd->id, $activity->audit_owner_id);
    }

    public function test_actor_agnostic_legacy_rows_do_not_grant_patient_trail_access(): void
    {
        $owner = User::factory()->rnd()->create();
        $other = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $owner->id]);
        AuditActivity::query()->delete();
        Activity::create([
            'log_name' => 'audit',
            'event' => 'updated',
            'description' => 'Updated NCP record',
            'subject_type' => NcpRecord::class,
            'subject_id' => $ncp->id,
        ]);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/rnd/patients/{$patient->uuid}/activity")
            ->assertForbidden();
    }

    public function test_non_audit_channels_and_poisoned_legacy_properties_fail_closed(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        AuditActivity::query()->delete();
        $foreign = Activity::create([
            'log_name' => 'default',
            'event' => 'updated',
            'description' => 'Updated patient',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
        ]);
        $poisoned = Activity::create([
            'log_name' => 'audit',
            'event' => 'POISON-EVENT-SENTINEL',
            'description' => 'DESCRIPTION-PHI-SENTINEL',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'properties' => [
                'details' => ['fields' => ['unexpected', 'medical_diagnosis']],
                'attributes' => ['unexpected' => 'ATTRIBUTE-PHI-SENTINEL'],
                'actor' => ['name' => 'ACTOR-PHI-SENTINEL', 'kind' => 'user'],
            ],
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/patients/{$patient->uuid}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'updated')
            ->assertJsonPath('data.0.actor', null)
            ->assertJsonPath('data.0.changes.0.field', 'medical_diagnosis')
            ->assertJsonPath('data.0.changes.0.redacted', true);

        $this->assertNotSame((string) $poisoned->id, $response->json('data.0.id'));
        foreach (['POISON-EVENT-SENTINEL', 'DESCRIPTION-PHI-SENTINEL', 'ATTRIBUTE-PHI-SENTINEL', 'ACTOR-PHI-SENTINEL', 'unexpected'] as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $response->getContent());
        }
    }

    public function test_every_authorized_audit_detail_access_is_recorded_without_deduplication(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        AuditActivity::query()->delete();
        $this->actingAs($rnd, 'sanctum');

        $this->getJson("/api/rnd/patients/{$patient->uuid}/activity")->assertOk();
        $cursor = AuditActivity::query()->where('event', AuditAction::AuditLogViewed->value)->sole()->public_id;
        $this->getJson("/api/rnd/patients/{$patient->uuid}/activity?before_id={$cursor}")->assertOk();

        $this->assertSame(2, AuditActivity::query()->where('event', AuditAction::AuditLogViewed->value)->count());
    }

    public function test_trail_query_has_root_cursor_indexes_and_bounded_queries_without_id_pluck(): void
    {
        $indexes = collect(Schema::getIndexes('activity_log'))->pluck('name');
        $this->assertTrue(Schema::hasColumns('activity_log', ['root_patient_id', 'ncp_record_id', 'audit_owner_id']));
        $this->assertContains('activity_log_root_patient_id_cursor_index', $indexes);
        $this->assertContains('activity_log_ncp_record_id_cursor_index', $indexes);
        $this->assertContains('activity_log_owner_patient_cursor_index', $indexes);

        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        NcpRecord::factory()->count(3)->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($rnd, 'sanctum')->getJson("/api/rnd/patients/{$patient->uuid}/activity")->assertOk();
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(7, $queries->count());
        $this->assertFalse($queries->contains(fn (array $query): bool => preg_match('/^select [`"]id[`"] from [`"]ncp_records/i', trim($query['query'])) === 1
            && ! str_contains($query['query'], 'patient_id')));
    }
}
