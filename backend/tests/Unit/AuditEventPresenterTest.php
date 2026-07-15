<?php

namespace Tests\Unit;

use App\Data\AuditValueDto;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Http\Resources\AuditEventResource;
use App\Models\AuditActivity;
use App\Models\AuditRevision;
use App\Models\FoodItem;
use App\Models\Patient;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class AuditEventPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_typed_value_boundary_rejects_raw_nested_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported audit value payload.');

        new AuditValueDto('text', ['nested' => ['RAW-JSON-SENTINEL']]);
    }

    public function test_resource_rejects_a_raw_audit_model_instead_of_serializing_it(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('AuditEventResource requires a typed audit event.');

        (new AuditEventResource(new AuditActivity([
            'properties' => ['RAW-RESOURCE-SENTINEL'],
        ])))->resolve(Request::create('/api/admin/audit-logs'));
    }

    public function test_unknown_legacy_action_and_unclassified_module_are_preserved_safely(): void
    {
        $activity = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy event',
            'event' => 'legacy_custom_action',
            'properties' => [
                'actor' => ['kind' => 'system', 'name' => 'Legacy worker'],
                'details' => ['unsafe_nested' => ['RAW-JSON-SENTINEL']],
            ],
        ]);

        $event = app(AuditEventPresenter::class)->present($activity)->toArray();

        $this->assertSame('legacy_unclassified', $event['module']);
        $this->assertSame('legacy_custom_action', $event['action']);
        $this->assertSame('Legacy custom action', $event['action_label']);
        $this->assertSame('system_operation', $event['record_type']);
        $this->assertSame([], $event['details']);
        $this->assertNull($event['patient']);
        $this->assertNull($event['history']);
        $this->assertNull($event['current_record_url']);
        $this->assertStringNotContainsString('RAW-JSON-SENTINEL', json_encode($event, JSON_THROW_ON_ERROR));
        $this->assertNotSame('Updated record', $event['summary']);
    }

    public function test_every_known_action_keeps_its_identity_and_has_a_factual_summary(): void
    {
        foreach (AuditAction::cases() as $action) {
            $activity = new AuditActivity([
                'public_id' => (string) Str::uuid(),
                'log_name' => 'audit',
                'description' => $action->label(),
                'event' => $action->value,
                'category' => AuditCategory::Operations,
                'domain' => AuditDomain::NutritionLibrary,
                'module' => AuditModule::NutritionCare,
                'subject_type' => FoodItem::class,
                'subject_public_id' => (string) Str::uuid(),
                'properties' => ['actor' => ['kind' => 'system', 'name' => 'System']],
            ]);
            $activity->created_at = now();

            $event = app(AuditEventPresenter::class)->present($activity)->toArray();

            $this->assertSame($action->value, $event['action']);
            $this->assertSame($action->label(), $event['action_label']);
            $this->assertNotSame('', $event['summary']);
            $this->assertNotSame('Updated record', $event['summary']);
        }
    }

    public function test_clinical_contract_separates_patient_identity_actor_and_redacted_changes(): void
    {
        $actor = User::factory()->rnd()->create(['first_name' => 'Actual', 'last_name' => 'Actor']);
        $activity = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Updated NCP',
            'event' => AuditAction::Updated->value,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Ncp,
            'module' => AuditModule::NutritionCare,
            'patient_display_name_snapshot' => 'Patient Display',
            'subject_type' => 'App\\Models\\NcpRecord',
            'subject_public_id' => (string) Str::uuid(),
            'properties' => [
                'actor' => [
                    'kind' => 'user', 'public_id' => $actor->uuid,
                    'name' => $actor->display_name, 'role' => 'RND',
                ],
                'details' => [
                    'changed_fields' => ['energy_target', 'protein_target'],
                    'ncp_reference' => 'NCP-ABCDEF0123456789',
                ],
                'old' => ['energy_target' => 'CLINICAL-OLD-SENTINEL'],
                'attributes' => ['energy_target' => 'CLINICAL-NEW-SENTINEL'],
            ],
        ]);

        $event = app(AuditEventPresenter::class)->present($activity)->toArray();

        $this->assertSame(['display_name' => 'Patient Display'], $event['patient']);
        $this->assertSame('Actual Actor', $event['actor']['name']);
        $this->assertSame('NCP-ABCDEF0123456789', $event['ncp_reference']);
        $this->assertSame('field_names', $event['detail_mode']);
        $this->assertSame(['Energy Target', 'Protein Target'], array_column($event['changes'], 'label'));
        $this->assertTrue(collect($event['changes'])->every(
            fn (array $change): bool => $change['redacted']
                && $change['old_value'] === null
                && $change['new_value'] === null
                && $change['before']['type'] === 'redacted'
                && $change['after']['type'] === 'redacted',
        ));
        $encoded = json_encode($event, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString((string) $activity->subject_public_id, $encoded);
        $this->assertStringNotContainsString('CLINICAL-OLD-SENTINEL', $encoded);
        $this->assertStringNotContainsString('CLINICAL-NEW-SENTINEL', $encoded);
    }

    public function test_safe_operational_changes_are_explicit_typed_values(): void
    {
        $activity = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Updated food item',
            'event' => AuditAction::Updated->value,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::NutritionLibrary,
            'module' => AuditModule::NutritionCare,
            'subject_type' => FoodItem::class,
            'subject_public_id' => (string) Str::uuid(),
            'properties' => [
                'actor' => ['kind' => 'system', 'name' => 'Import worker'],
                'details' => [
                    'changed_fields' => ['serving_size', 'serving_unit'],
                    'reason' => 'Corrected vendor invoice',
                ],
                'old' => ['serving_size' => 100, 'serving_unit' => 'g'],
                'attributes' => ['serving_size' => 150, 'serving_unit' => 'g'],
            ],
        ]);

        $event = app(AuditEventPresenter::class)->present($activity)->toArray();

        $this->assertSame('changes', $event['detail_mode']);
        $this->assertSame('number', $event['changes'][0]['before']['type']);
        $this->assertSame(100, $event['changes'][0]['before']['value']);
        $this->assertSame(150, $event['changes'][0]['after']['value']);
        $this->assertSame('enum', $event['changes'][1]['before']['type']);
        $this->assertSame('g', $event['changes'][1]['before']['value']);
        $this->assertSame(100, $event['changes'][0]['old_value']);
        $this->assertSame(150, $event['changes'][0]['new_value']);
        $this->assertSame('Corrected vendor invoice', $event['reason']);
    }

    public function test_created_operational_record_exposes_only_curated_initial_values(): void
    {
        $activity = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Created food item',
            'event' => AuditAction::Created->value,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::NutritionLibrary,
            'module' => AuditModule::NutritionCare,
            'subject_type' => FoodItem::class,
            'subject_public_id' => (string) Str::uuid(),
            'properties' => [
                'attributes' => [
                    'name' => 'Brown Rice', 'calories' => 130,
                    'serving_unit' => 'g', 'unsafe_blob' => 'RAW-CREATED-SENTINEL',
                ],
            ],
        ]);

        $event = app(AuditEventPresenter::class)->present($activity)->toArray();

        $this->assertSame(['calories', 'name', 'serving_unit'], array_column($event['changes'], 'field'));
        $this->assertTrue(collect($event['changes'])->every(
            fn (array $change): bool => $change['before']['value'] === null && $change['after']['value'] !== null,
        ));
        $name = collect($event['changes'])->firstWhere('field', 'name');
        $this->assertSame(['type' => 'text', 'value' => null], $name['before']);
        $this->assertSame(['type' => 'text', 'value' => 'Brown Rice'], $name['after']);
        $this->assertStringNotContainsString('RAW-CREATED-SENTINEL', json_encode($event, JSON_THROW_ON_ERROR));
    }

    public function test_deleted_subject_snapshot_survives_without_a_current_record_lookup(): void
    {
        $publicId = (string) Str::uuid();
        $activity = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Deleted food item',
            'event' => AuditAction::Deleted->value,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::NutritionLibrary,
            'module' => AuditModule::NutritionCare,
            'subject_type' => FoodItem::class,
            'subject_public_id' => $publicId,
            'properties' => ['old' => ['name' => 'Deleted food']],
        ]);

        $event = app(AuditEventPresenter::class)->present($activity)->toArray();

        $this->assertSame(['type' => 'food_item', 'id' => $publicId, 'label' => 'Food item'], $event['subject']);
        $this->assertNull($event['current_record_url']);
        $this->assertSame('Deleted food', $event['changes'][0]['old_value']);
        $this->assertSame(['type' => 'text', 'value' => 'Deleted food'], $event['changes'][0]['before']);
        $this->assertSame(['type' => 'text', 'value' => null], $event['changes'][0]['after']);
    }

    public function test_revision_metadata_is_typed_without_exposing_snapshot_json(): void
    {
        $admin = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create();
        $activity = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Updated recipe',
            'event' => AuditAction::Updated->value,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::NutritionLibrary,
            'module' => AuditModule::NutritionCare,
            'subject_type' => Recipe::class,
            'subject_id' => $recipe->id,
            'subject_public_id' => $recipe->uuid,
            'properties' => ['details' => ['changed_fields' => ['ingredients']]],
        ]);
        $revision = AuditRevision::create([
            'activity_id' => $activity->id,
            'module' => AuditModule::NutritionCare,
            'domain' => AuditDomain::NutritionLibrary,
            'subject_type' => Recipe::class,
            'subject_public_id' => $recipe->uuid,
            'action' => AuditAction::Updated,
            'schema_version' => 1,
            'before' => ['name' => 'REVISION-BEFORE-SENTINEL'],
            'after' => ['name' => 'REVISION-AFTER-SENTINEL'],
            'occurred_at' => now(),
        ]);
        $activity->load('revision');

        $event = app(AuditEventPresenter::class)->present($activity, $admin)->toArray();

        $this->assertSame($revision->public_id, $event['history']['id']);
        $this->assertSame('View audited changes', $event['history']['label']);
        $this->assertSame("/api/admin/audit-logs/{$activity->public_id}/history", $event['history']['url']);
        $this->assertSame('history', $event['detail_mode']);
        $encoded = json_encode($event, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('REVISION-BEFORE-SENTINEL', $encoded);
        $this->assertStringNotContainsString('REVISION-AFTER-SENTINEL', $encoded);
    }

    public function test_current_record_url_requires_a_live_record_and_authorized_role(): void
    {
        $rnd = User::factory()->rnd()->create();
        $admin = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $activity = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Viewed patient',
            'event' => AuditAction::Viewed->value,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Patients,
            'module' => AuditModule::NutritionCare,
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'subject_public_id' => $patient->uuid,
            'patient_display_name_snapshot' => $patient->display_name,
        ]);

        $presenter = app(AuditEventPresenter::class);
        $this->assertSame(
            "/ncp/patients/{$patient->uuid}",
            $presenter->present($activity, $rnd, $patient)->toArray()['current_record_url'],
        );
        $this->assertNull($presenter->present($activity, $admin, $patient)->toArray()['current_record_url']);
        $otherPatient = Patient::factory()->create();
        $this->assertNull($presenter->present($activity, $rnd, $otherPatient)->toArray()['current_record_url']);

        $patient->delete();
        $this->assertNull($presenter->present($activity, $rnd, $patient)->toArray()['current_record_url']);
    }

    public function test_subjectless_security_event_uses_a_semantic_entity(): void
    {
        $activity = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Login failed',
            'event' => AuditAction::LoginFailed->value,
            'category' => AuditCategory::Security,
            'domain' => AuditDomain::Accounts,
            'module' => AuditModule::SecurityAdministration,
            'properties' => [
                'actor' => ['kind' => 'anonymous', 'name' => 'Anonymous'],
                'details' => ['route_name' => 'auth.login'],
            ],
        ]);

        $event = app(AuditEventPresenter::class)->present($activity)->toArray();

        $this->assertSame('admin_web_login', $event['subject']['type']);
        $this->assertSame('Admin web login', $event['subject']['label']);
        $this->assertSame('Anonymous login failed through Admin web login.', $event['summary']);
    }
}
