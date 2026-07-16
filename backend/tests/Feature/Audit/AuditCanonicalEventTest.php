<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Models\Assessment;
use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\FoodItem;
use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MenuCycleTemplate;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\Report;
use App\Models\ScreeningDocument;
use App\Models\User;
use App\Services\Audit\AuditEventPolicy;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\AuditPatientSnapshot;
use App\Services\Audit\AuditPseudonymousReference;
use App\Services\UsdaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class AuditCanonicalEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_is_the_authoritative_module_privacy_writer_and_detail_registry(): void
    {
        $policy = app(AuditEventPolicy::class);

        $this->assertSame([
            'module' => AuditModule::NutritionCare,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::NutritionLibrary,
            'privacy' => 'safe_operational',
            'canonical_writer' => 'explicit',
            'detail_mode' => 'changes',
            'reason_rule' => 'none',
            'revision_serializer' => null,
        ], $policy->forEvent(
            AuditAction::Imported,
            new FoodItem,
            AuditCategory::Operations,
            AuditDomain::FoodService,
        ));

        $this->assertSame([
            'module' => AuditModule::NutritionCare,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Patients,
            'privacy' => 'clinical',
            'canonical_writer' => 'automatic',
            'detail_mode' => 'field_names',
            'reason_rule' => 'none',
            'revision_serializer' => null,
        ], $policy->forEvent(
            AuditAction::Updated,
            new Patient,
            AuditCategory::Operations,
            AuditDomain::System,
        ));

        $this->assertSame(AuditModule::FoodServiceOperations, $policy->forEvent(
            AuditAction::Deleted,
            new PurchaseOrder,
            AuditCategory::Operations,
            AuditDomain::Procurement,
        )['module']);
        $this->assertSame('destructive', $policy->forEvent(
            AuditAction::Deleted,
            new PurchaseOrder,
            AuditCategory::Operations,
            AuditDomain::Procurement,
        )['reason_rule']);
        $this->assertSame('purchase_order', $policy->forEvent(
            AuditAction::Deleted,
            new PurchaseOrder,
            AuditCategory::Operations,
            AuditDomain::Procurement,
        )['revision_serializer']);
        $this->assertSame(AuditModule::Reports, $policy->forEvent(
            AuditAction::Generated,
            new Report(['audit_patient_id' => 123]),
            AuditCategory::Clinical,
            AuditDomain::Reports,
        )['module']);
        $this->assertSame(AuditDomain::FoodService, $policy->forEvent(
            AuditAction::Updated,
            new FsItem,
            AuditCategory::Operations,
            AuditDomain::System,
        )['domain']);
        $this->assertSame(AuditDomain::NutritionLibrary, $policy->forEvent(
            AuditAction::Updated,
            new Recipe,
            AuditCategory::Operations,
            AuditDomain::FoodService,
        )['domain']);
        $this->assertSame('rnd_recipe', $policy->forEvent(
            AuditAction::Updated,
            new Recipe,
            AuditCategory::Operations,
            AuditDomain::FoodService,
        )['revision_serializer']);
        $this->assertSame('explicit', $policy->forEvent(
            AuditAction::Updated,
            new FoodServiceRecipe,
            AuditCategory::Operations,
            AuditDomain::FoodService,
        )['canonical_writer']);
        $this->assertSame('food_service_recipe', $policy->forEvent(
            AuditAction::Updated,
            new FoodServiceRecipe,
            AuditCategory::Operations,
            AuditDomain::FoodService,
        )['revision_serializer']);
        $this->assertSame('menu_cycle_template', $policy->forEvent(
            AuditAction::Updated,
            new MenuCycleTemplate,
            AuditCategory::Operations,
            AuditDomain::FoodService,
        )['revision_serializer']);
        $this->assertSame(AuditModule::FoodServiceOperations, $policy->forEvent(
            AuditAction::Created,
            new Budget,
            AuditCategory::Operations,
            AuditDomain::Budget,
        )['module']);
    }

    public function test_explicit_logger_applies_canonical_taxonomy_and_actual_actor(): void
    {
        $actor = User::factory()->rnd()->create();
        $food = FoodItem::factory()->create(['usda_fdc_id' => 987654]);
        AuditFixture::delete(AuditActivity::query());
        $this->actingAs($actor, 'sanctum');

        $event = app(AuditLogger::class)->recordMutation(
            AuditAction::Imported,
            AuditDomain::FoodService,
            $food,
            ['usda_fdc_id'],
            ['source' => 'usda'],
        );

        $this->assertNotNull($event);
        $this->assertSame(AuditAction::Imported->value, $event->event);
        $this->assertSame(AuditModule::NutritionCare, $event->module);
        $this->assertSame(AuditCategory::Operations, $event->category);
        $this->assertSame(AuditDomain::NutritionLibrary, $event->domain);
        $this->assertSame($actor->id, $event->causer_id);
        $this->assertSame($actor->display_name, $event->properties['actor']['name']);
        $this->assertSame(1, AuditActivity::query()->count());
    }

    public function test_automatic_clinical_event_stores_only_encrypted_patient_name_and_pseudonymous_reference(): void
    {
        $actor = User::factory()->rnd()->create();
        $patient = Patient::factory()->create([
            'name' => 'LEGACY-PATIENT-NAME',
            'first_name' => 'Privacy',
            'last_name' => 'Sentinel',
            'hospital_number' => 'HOSPITAL-PRIVATE-123',
            'medical_diagnosis' => 'CLINICAL-PRIVATE-DIAGNOSIS',
        ]);
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $actor->id,
            'status' => 'draft',
        ]);
        AuditFixture::delete(AuditActivity::query());
        $this->actingAs($actor, 'sanctum');

        $ncp->update(['status' => 'active']);

        $event = AuditActivity::query()->sole();
        $this->assertSame(AuditModule::NutritionCare, $event->module);
        $this->assertSame(AuditCategory::Clinical, $event->category);
        $this->assertSame(AuditDomain::Ncp, $event->domain);
        $this->assertSame('Privacy Sentinel', $event->patient_display_name_snapshot);
        $this->assertSame($actor->id, $event->causer_id);
        $this->assertContains('status', $event->properties['details']['changed_fields']);
        $this->assertArrayNotHasKey('old', $event->properties);
        $this->assertArrayNotHasKey('attributes', $event->properties);

        $reference = $event->properties['details']['ncp_reference'];
        $this->assertMatchesRegularExpression('/^NCP-[A-F0-9]{16}$/D', $reference);
        $this->assertSame($reference, app(AuditPseudonymousReference::class)->resolve($ncp, $ncp->id));
        $this->assertNotSame($ncp->uuid, $reference);
        $this->assertSame('Privacy Sentinel', app(AuditPatientSnapshot::class)->resolve($ncp, $patient->id));

        $rawSnapshot = DB::table('activity_log')->where('id', $event->id)
            ->value('patient_display_name_snapshot');
        $this->assertNotSame('Privacy Sentinel', $rawSnapshot);

        $serializedModel = $event->toJson();
        $serializedProperties = $event->properties->toJson();
        foreach ([
            'Privacy Sentinel', 'LEGACY-PATIENT-NAME', 'HOSPITAL-PRIVATE-123',
            'CLINICAL-PRIVATE-DIAGNOSIS', $patient->uuid, $ncp->uuid,
        ] as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $serializedModel);
            $this->assertStringNotContainsString($sentinel, $serializedProperties);
        }
        $this->assertNull($event->revision);
    }

    public function test_patient_name_change_never_stores_previous_or_new_name_values_in_properties(): void
    {
        $actor = User::factory()->rnd()->create();
        $patient = Patient::factory()->create([
            'name' => 'Old Legacy Name',
            'first_name' => 'OldFirstSentinel',
            'last_name' => 'FamilySentinel',
        ]);
        AuditFixture::delete(AuditActivity::query());
        $this->actingAs($actor, 'sanctum');

        $patient->update(['first_name' => 'NewFirstSentinel']);

        $event = AuditActivity::query()->sole();
        $this->assertSame('NewFirstSentinel FamilySentinel', $event->patient_display_name_snapshot);
        $this->assertSame(['first_name'], $event->properties['details']['changed_fields']);
        $this->assertStringNotContainsString('OldFirstSentinel', $event->properties->toJson());
        $this->assertStringNotContainsString('NewFirstSentinel', $event->properties->toJson());
        $this->assertStringNotContainsString('FamilySentinel', $event->properties->toJson());
        $this->assertArrayNotHasKey('old', $event->properties);
        $this->assertArrayNotHasKey('attributes', $event->properties);
    }

    public function test_generic_mutation_cannot_put_clinical_identifiers_in_details(): void
    {
        $actor = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $actor->id,
        ]);
        AuditFixture::delete(AuditActivity::query());
        $this->actingAs($actor, 'sanctum');

        $event = app(AuditLogger::class)->recordMutation(
            AuditAction::Updated,
            AuditDomain::System,
            $ncp,
            ['status'],
            [
                'public_id' => $patient->uuid,
                'record_id' => $patient->id,
                'context_public_id' => $ncp->uuid,
                'root_patient_id' => 999999,
                'ncp_record_id' => 999999,
            ],
        );

        $this->assertNotNull($event);
        $this->assertSame(AuditCategory::Clinical, $event->category);
        $this->assertSame(AuditDomain::Ncp, $event->domain);
        $this->assertSame($patient->id, $event->root_patient_id);
        $this->assertSame($ncp->id, $event->ncp_record_id);
        $this->assertSame(['status'], $event->properties['details']['changed_fields']);
        $this->assertMatchesRegularExpression('/^NCP-[A-F0-9]{16}$/D', $event->properties['details']['ncp_reference']);
        foreach (['public_id', 'record_id', 'context_public_id', 'root_patient_id', 'ncp_record_id'] as $key) {
            $this->assertArrayNotHasKey($key, $event->properties['details']);
        }
    }

    public function test_upload_and_ai_approval_each_emit_only_the_canonical_business_event(): void
    {
        Storage::fake('local');
        $actor = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $actor->id,
        ]);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($actor, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$ncp->uuid}/attachments",
            ['file' => UploadedFile::fake()->create('safe.pdf', 10, 'application/pdf')],
        )->assertCreated();

        $upload = AuditActivity::query()->sole();
        $this->assertSame(AuditAction::Uploaded->value, $upload->event);
        $this->assertSame(ScreeningDocument::class, $upload->subject_type);
        $this->assertSame(AuditModule::NutritionCare, $upload->module);

        Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 70,
            'height' => 170,
        ]);
        AuditFixture::delete(AuditActivity::query());

        $this->postJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses/ai-approve", [
            'domain' => 'NI',
            'label' => 'Inadequate energy intake',
            'etiology' => 'related to poor appetite',
            'signs' => 'as evidenced by weight loss',
        ])->assertCreated();

        $approval = AuditActivity::query()->sole();
        $this->assertSame(AuditAction::Approved->value, $approval->event);
        $this->assertSame(AuditModule::NutritionCare, $approval->module);
        $this->assertStringNotContainsString('poor appetite', $approval->properties->toJson());
        $this->assertStringNotContainsString('weight loss', $approval->properties->toJson());
    }

    public function test_generated_meal_plan_emits_generated_without_generic_created_event(): void
    {
        $actor = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $actor->id,
        ]);
        Intervention::factory()->create([
            'ncp_record_id' => $ncp->id,
            'energy_kcal' => 1800,
            'protein_g' => 70,
            'carbs_g' => 250,
            'fat_g' => 55,
            'fluid_ml' => 2000,
        ]);
        Recipe::factory(15)->create(['rnd_user_id' => $actor->id]);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($actor, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/generate",
            ['week_start_date' => '2026-07-13', 'conditions' => [], 'allergens' => []],
        )->assertCreated();

        $event = AuditActivity::query()->sole();
        $this->assertSame(AuditAction::Generated->value, $event->event);
        $this->assertSame(MealPlan::class, $event->subject_type);
        $this->assertSame(AuditModule::NutritionCare, $event->module);
        $this->assertSame(0, AuditActivity::query()->where('event', AuditAction::Created->value)->count());
    }

    public function test_usda_import_uses_imported_and_failure_writes_nothing(): void
    {
        $actor = User::factory()->rnd()->create();
        $food = FoodItem::factory()->create(['usda_fdc_id' => 456]);
        AuditFixture::delete(AuditActivity::query());
        $usda = Mockery::mock(UsdaService::class);
        $usda->shouldReceive('prepareImport')->once()->with(456)->andReturn(['usda_fdc_id' => 456]);
        $usda->shouldReceive('persistImport')->once()->with(['usda_fdc_id' => 456])->andReturn($food);
        $usda->shouldReceive('prepareImport')->once()->with(457)->andThrow(new RuntimeException('upstream failed'));
        $this->app->instance(UsdaService::class, $usda);

        $this->actingAs($actor, 'sanctum')->postJson('/api/rnd/usda/import/456')->assertCreated();
        $this->postJson('/api/rnd/usda/import/457')->assertStatus(502);

        $event = AuditActivity::query()->sole();
        $this->assertSame(AuditAction::Imported->value, $event->event);
        $this->assertSame(AuditModule::NutritionCare, $event->module);
        $this->assertSame(AuditDomain::NutritionLibrary, $event->domain);
        $presented = app(AuditEventPresenter::class)
            ->present($event->load('causer'), User::factory()->admin()->create())
            ->toArray();
        $this->assertStringContainsString("food item: {$food->name}", $presented['summary']);
        $this->assertSame('usda', collect($presented['details'])->keyBy('key')['source']['value']);
        $external = collect($presented['changes'])->firstWhere('field', 'usda_fdc_id');
        $this->assertSame('reference', $external['after']['type']);
        $this->assertSame('456', $external['after']['value']);
    }
}
