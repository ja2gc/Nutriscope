<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Models\AiUsageLimit;
use App\Models\AuditActivity;
use App\Models\AuditSetting;
use App\Models\FoodItem;
use App\Models\FoodServiceSetting;
use App\Models\MealPlan;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\Recipe;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\AuditQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLegacyCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_retired_category_and_domain_list_parameters_are_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?category=operations')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?domain=reports')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain');
    }

    public function test_filter_metadata_exposes_only_active_module_taxonomy(): void
    {
        config()->set('audit.features.export', false);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs')
            ->assertOk();

        $filters = $response->json('meta.filters');
        $this->assertIsArray($filters);
        $this->assertArrayNotHasKey('categories', $filters);
        $this->assertArrayNotHasKey('domains', $filters);
        $this->assertArrayNotHasKey('category_actions', $filters);
        $this->assertNull(AuditAction::tryFrom('exported'));
        $this->assertNotContains('exported', collect($filters['actions'])->pluck('value')->all());
        $this->assertArrayNotHasKey('export', $response->json('meta.capabilities'));
    }

    public function test_report_access_visibility_does_not_require_a_legacy_domain_value(): void
    {
        $reportView = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy report view without domain',
            'event' => 'viewed',
            'subject_type' => (new Report)->getMorphClass(),
            'properties' => ['report_type' => 'ncp_summary'],
        ]);
        $reportCreation = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Report creation is not a report-access event',
            'event' => 'created',
            'subject_type' => (new Report)->getMorphClass(),
            'properties' => ['report_type' => 'ncp_summary'],
        ]);
        $patientView = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Patient view is not a report-access event',
            'event' => 'viewed',
            'subject_type' => Patient::class,
            'properties' => [],
        ]);

        $visibleIds = app(AuditQuery::class)->build([])->pluck('id')->all();

        $this->assertContains($reportView->id, $visibleIds);
        $this->assertNotContains($reportCreation->id, $visibleIds);
        $this->assertNotContains($patientView->id, $visibleIds);
    }

    public function test_backfill_restores_report_domain_and_module_for_access_rows(): void
    {
        $nutritionReport = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy nutrition report access',
            'event' => 'viewed',
            'module' => AuditModule::Reports,
            'subject_type' => (new Report)->getMorphClass(),
            'properties' => ['report_type' => 'ncp_summary'],
        ]);
        $foodServiceReport = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy food-service report access',
            'event' => 'downloaded',
            'module' => AuditModule::Reports,
            'subject_type' => (new Report)->getMorphClass(),
            'properties' => ['report_type' => 'procurement_pack'],
        ]);

        $this->artisan('audit:backfill-oversight')->assertSuccessful();

        $this->assertSame(AuditDomain::Reports, $nutritionReport->refresh()->domain);
        $this->assertSame(AuditModule::NutritionCare, $nutritionReport->module);
        $this->assertSame(AuditDomain::Reports, $foodServiceReport->refresh()->domain);
        $this->assertSame(AuditModule::FoodServiceOperations, $foodServiceReport->module);
    }

    public function test_mixed_current_and_legacy_rows_use_only_canonical_actions(): void
    {
        $legacy = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Historical custom event',
            'event' => 'Legacy.Custom-Action',
            'properties' => [],
        ]);
        $current = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Current report event',
            'event' => 'generated',
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::Reports,
            'module' => AuditModule::NutritionCare,
            'properties' => [],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-logs')
            ->assertOk();

        $events = collect($response->json('data'));
        $legacyDto = $events->firstWhere('id', $legacy->public_id);
        $currentDto = $events->firstWhere('id', $current->public_id);

        $this->assertSame('legacy_unclassified', $legacyDto['module']);
        $this->assertSame('legacy_unclassified', $legacyDto['category']);
        $this->assertSame('legacy_unclassified', $legacyDto['domain']);
        $this->assertSame('updated', $legacyDto['action']);
        $this->assertSame('Updated', $legacyDto['action_label']);
        $this->assertNull($currentDto);
    }

    public function test_account_deactivation_and_activation_use_suspended_actions_and_deletion_stays_deleted(): void
    {
        $user = User::factory()->create(['role' => 'RND', 'is_active' => true]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->uuid}", ['is_active' => false])
            ->assertOk();
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'event' => AuditAction::Suspended->value,
        ]);
        $suspension = AuditActivity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', AuditAction::Suspended->value)
            ->sole();
        $this->assertSame('127.0.0.1', $suspension->properties['ip']);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->uuid}", ['is_active' => true])
            ->assertOk();
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'event' => AuditAction::Unsuspended->value,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$user->uuid}")
            ->assertNoContent();
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'event' => AuditAction::Deleted->value,
        ]);
    }

    public function test_backfill_is_chunked_idempotent_and_preserves_privacy_and_existing_history(): void
    {
        $patient = Patient::factory()->create([
            'first_name' => 'Maria Luisa',
            'last_name' => 'Santos-Cruz',
            'name' => 'Maria Luisa Santos-Cruz',
        ]);
        $patientEvent = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy patient event',
            'event' => AuditAction::Updated,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Patients,
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'properties' => ['details' => ['ncp_reference' => 'NCP-AB12CD34']],
        ]);
        $unresolved = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Unresolved legacy patient event',
            'event' => AuditAction::Updated,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Ncp,
            'subject_type' => Patient::class,
            'subject_id' => 999999999,
            'properties' => ['details' => ['ncp_reference' => 'NCP-A1B2C3D4E5F60708']],
        ]);
        $food = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy RND food event',
            'event' => AuditAction::Created,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::FoodService,
            'module' => AuditModule::FoodServiceOperations,
            'subject_type' => FoodItem::class,
            'subject_id' => 1001,
            'properties' => [],
        ]);
        $recipe = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy RND recipe event',
            'event' => AuditAction::Created,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::FoodService,
            'subject_type' => Recipe::class,
            'subject_id' => 1002,
            'properties' => [],
        ]);
        $ambiguous = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Ambiguous legacy event',
            'event' => 'legacy_unknown',
            'properties' => [],
        ]);
        $existingSnapshot = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Already snapshotted event',
            'event' => AuditAction::Updated,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Patients,
            'module' => AuditModule::NutritionCare,
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'patient_display_name_snapshot' => 'Historical Patient',
            'properties' => [],
        ]);

        $this->artisan('audit:backfill-oversight', ['--chunk' => 1])->assertSuccessful();
        $firstState = DB::table('activity_log')
            ->whereIn('id', [$patientEvent->id, $unresolved->id, $food->id, $recipe->id, $ambiguous->id, $existingSnapshot->id])
            ->orderBy('id')
            ->get(['id', 'module', 'domain', 'patient_code_snapshot', 'patient_display_name_snapshot', 'properties'])
            ->map(fn (object $row): array => (array) $row)
            ->all();
        $this->artisan('audit:backfill-oversight', ['--chunk' => 2])->assertSuccessful();
        $secondState = DB::table('activity_log')
            ->whereIn('id', [$patientEvent->id, $unresolved->id, $food->id, $recipe->id, $ambiguous->id, $existingSnapshot->id])
            ->orderBy('id')
            ->get(['id', 'module', 'domain', 'patient_code_snapshot', 'patient_display_name_snapshot', 'properties'])
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $this->assertSame($firstState, $secondState);
        $this->assertSame(AuditModule::NutritionCare, $patientEvent->refresh()->module);
        $this->assertSame($patient->patient_code, $patientEvent->patient_code_snapshot);
        $this->assertNull($patientEvent->patient_display_name_snapshot);
        $this->assertSame($patient->patient_code, DB::table('activity_log')->where('id', $patientEvent->id)->value('patient_code_snapshot'));
        $this->assertNull($unresolved->refresh()->patient_code_snapshot);
        $this->assertSame('NCP-A1B2C3D4E5F60708', $unresolved->properties['details']['ncp_reference']);
        $this->assertSame(
            'NCP-A1B2C3D4E5F60708',
            app(AuditEventPresenter::class)->present($unresolved)->ncpReference,
        );
        $this->assertSame(AuditModule::NutritionCare, $food->refresh()->module);
        $this->assertSame(AuditDomain::NutritionLibrary, $food->domain);
        $this->assertSame(AuditModule::NutritionCare, $recipe->refresh()->module);
        $this->assertSame(AuditDomain::NutritionLibrary, $recipe->domain);
        $this->assertNull($ambiguous->refresh()->module);
        $this->assertSame('legacy_unclassified', app(AuditEventPresenter::class)->present($ambiguous)->module);
        $this->assertSame('Historical Patient', $existingSnapshot->refresh()->patient_display_name_snapshot);
        $this->assertSame($patient->patient_code, $existingSnapshot->patient_code_snapshot);
        $this->assertDatabaseCount('audit_revisions', 0);
        $this->assertStringNotContainsString($patient->display_name, json_encode($secondState, JSON_THROW_ON_ERROR));
    }

    public function test_legacy_action_mapping_and_revision_actions_match_canonical_events(): void
    {
        $cases = [
            ['key' => 'ordered', 'event' => 'ordered', 'subject_type' => PurchaseOrder::class, 'action' => 'updated'],
            ['key' => 'approved', 'event' => 'approved', 'subject_type' => PurchaseOrder::class, 'action' => 'created'],
            ['key' => 'uploaded', 'event' => 'uploaded', 'subject_type' => PurchaseOrderAttachment::class, 'action' => 'created'],
            ['key' => 'imported', 'event' => 'imported', 'subject_type' => FoodItem::class, 'action' => 'created'],
            ['key' => 'logout', 'event' => 'logout', 'subject_type' => User::class, 'action' => 'deleted'],
            ['key' => 'suspended', 'event' => 'account_suspended', 'subject_type' => User::class, 'action' => 'suspended'],
            ['key' => 'unsuspended', 'event' => 'account_unsuspended', 'subject_type' => User::class, 'action' => 'unsuspended'],
            ['key' => 'meal_plan', 'event' => 'generated', 'subject_type' => MealPlan::class, 'action' => 'created'],
            ['key' => 'shopping_list', 'event' => 'generated', 'subject_type' => ShoppingList::class, 'action' => 'created'],
            ['key' => 'other_generated', 'event' => 'generated', 'subject_type' => User::class, 'action' => 'updated'],
        ];
        $activities = [];

        foreach ($cases as $case) {
            $attributes = [
                'log_name' => 'audit',
                'description' => 'Legacy '.$case['event'],
                'event' => $case['event'],
                'subject_type' => $case['subject_type'],
                'properties' => [],
            ];
            if ($case['key'] === 'ordered') {
                $attributes['module'] = AuditModule::FoodServiceOperations;
                $attributes['domain'] = AuditDomain::FoodService;
                $attributes['subject_public_id'] = (string) Str::uuid();
            }
            $activity = AuditActivity::create($attributes);
            $activities[$case['key']] = $activity;

            $this->assertSame($case['action'], app(AuditEventPresenter::class)->present($activity)->action);
        }

        $ordered = $activities['ordered'];
        DB::table('audit_revisions')->insert([
            'public_id' => (string) Str::uuid(),
            'activity_id' => $ordered->id,
            'module' => AuditModule::FoodServiceOperations->value,
            'domain' => AuditDomain::FoodService->value,
            'subject_type' => PurchaseOrder::class,
            'subject_public_id' => $ordered->subject_public_id,
            'action' => 'ordered',
            'schema_version' => 1,
            'before' => json_encode(['status' => 'draft'], JSON_THROW_ON_ERROR),
            'after' => json_encode(['status' => 'ordered'], JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $this->artisan('audit:backfill-oversight')->assertSuccessful();

        foreach ($cases as $case) {
            $this->assertSame($case['action'], $activities[$case['key']]->refresh()->event);
        }
        $this->assertDatabaseHas('audit_revisions', [
            'activity_id' => $ordered->id,
            'action' => 'updated',
        ]);
    }

    public function test_backfill_corrects_purchase_order_transitions_normalized_by_prior_cleanup(): void
    {
        $ordered = AuditActivity::create([
            'log_name' => 'audit',
            'event' => 'created',
            'description' => 'Created',
            'subject_type' => PurchaseOrder::class,
            'properties' => ['details' => ['changed_fields' => ['status']]],
        ]);
        $created = AuditActivity::create([
            'log_name' => 'audit',
            'event' => 'created',
            'description' => 'Created',
            'subject_type' => PurchaseOrder::class,
            'properties' => ['details' => ['source' => 'purchase request']],
        ]);

        $this->artisan('audit:backfill-oversight')->assertSuccessful();

        $this->assertSame('updated', $ordered->refresh()->event);
        $this->assertSame('Purchase order ordered', $ordered->properties['details']['subject_label']);
        $this->assertSame('created', $created->refresh()->event);
    }

    public function test_backfill_removes_the_retired_blocked_outcome_from_retained_events(): void
    {
        $activityId = DB::table(config('activitylog.table_name'))->insertGetId([
            'public_id' => (string) Str::uuid(),
            'log_name' => 'audit',
            'description' => 'Legacy rate limit event',
            'event' => 'rate_limit_exceeded',
            'outcome' => 'blocked',
            'properties' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('audit:backfill-oversight')->assertSuccessful();

        $row = DB::table(config('activitylog.table_name'))->where('id', $activityId)->first();
        $this->assertSame('failed', $row->event);
        $this->assertSame('failure', $row->outcome);
    }

    public function test_legacy_actions_are_presented_canonically_and_backfilled(): void
    {
        $priceCorrection = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy price correction',
            'event' => 'price_corrected',
            'properties' => ['details' => ['changed_fields' => ['unit_price']]],
        ]);
        $loginFailure = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy login failure',
            'event' => 'login_failed',
            'properties' => [],
        ]);
        $accountBlocked = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy account block',
            'event' => 'account_blocked',
            'properties' => ['details' => ['subject_label' => 'Account blocked']],
        ]);
        $accountUnblocked = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy account unblock',
            'event' => 'account_unblocked',
            'properties' => [],
        ]);
        $authorizationDenied = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy authorization denial',
            'event' => 'authorization_denied',
            'properties' => [],
        ]);
        $accessDenied = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy access denial',
            'event' => 'access_denied',
            'properties' => [],
        ]);
        $denied = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy denied action',
            'event' => 'denied',
            'properties' => [],
        ]);
        $blocked = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy blocked action',
            'event' => 'blocked',
            'properties' => [],
        ]);
        $ipBlocked = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Retired IP blocking feature',
            'event' => 'ip_blocked',
            'properties' => [],
        ]);
        $auditLogView = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Retired audit log view',
            'event' => 'audit_log_viewed',
            'properties' => [],
        ]);
        $exported = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Retired audit export',
            'event' => 'exported',
            'properties' => [],
        ]);
        $settingsChange = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Retired settings event',
            'event' => 'settings_changed',
            'properties' => [],
        ]);
        $profileChanged = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Retired profile event',
            'event' => 'profile_changed',
            'subject_type' => User::class,
            'properties' => [],
        ]);
        $profileUpdated = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Previously normalized profile event',
            'event' => 'updated',
            'subject_type' => User::class,
            'properties' => ['details' => ['subject_label' => 'Profile', 'changed_fields' => ['first_name']]],
        ]);
        $unknown = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Unknown historical event',
            'event' => 'legacy_custom',
            'properties' => [],
        ]);
        $foodServiceReport = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy menu calendar report view',
            'event' => 'viewed',
            'domain' => AuditDomain::Reports,
            'module' => AuditModule::NutritionCare,
            'subject_type' => Report::class,
            'properties' => ['report_type' => 'menu_calendar'],
        ]);
        $nutritionCareReport = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy NCP summary report view',
            'event' => 'viewed',
            'domain' => AuditDomain::Reports,
            'module' => AuditModule::FoodServiceOperations,
            'subject_type' => Report::class,
            'properties' => ['report_type' => 'ncp_summary'],
        ]);
        $reportCreated = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy report generation event',
            'event' => 'created',
            'domain' => AuditDomain::Reports,
            'module' => AuditModule::Reports,
            'subject_type' => Report::class,
            'properties' => ['report_type' => 'procurement_pack'],
        ]);
        $reportTemplateEdit = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy report template edit',
            'event' => 'updated',
            'domain' => AuditDomain::Reports,
            'module' => AuditModule::Reports,
            'subject_type' => ReportTemplate::class,
            'properties' => ['details' => ['subject_label' => 'Report template']],
        ]);
        $retiredConfigurationEvents = [];
        foreach ([
            [ReportBranding::class, AuditDomain::Reports],
            [AuditSetting::class, AuditDomain::System],
            [FoodServiceSetting::class, AuditDomain::FoodService],
            [AiUsageLimit::class, AuditDomain::System],
        ] as [$subjectType, $domain]) {
            $retiredConfigurationEvents[] = AuditActivity::create([
                'log_name' => 'audit',
                'description' => 'Retired configuration event',
                'event' => 'updated',
                'domain' => $domain,
                'subject_type' => (new $subjectType)->getMorphClass(),
                'properties' => [],
            ]);
        }
        $nonReportViewed = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy non-report view',
            'event' => 'viewed',
            'domain' => AuditDomain::Patients,
            'module' => AuditModule::NutritionCare,
            'subject_type' => Patient::class,
            'properties' => [],
        ]);

        $presenter = app(AuditEventPresenter::class);
        $this->assertSame(AuditAction::Updated->value, $presenter->present($priceCorrection)->action);
        $this->assertSame(AuditAction::Failed->value, $presenter->present($loginFailure)->action);
        $this->assertSame(AuditAction::Suspended->value, $presenter->present($accountBlocked)->action);
        $this->assertSame(AuditAction::Unsuspended->value, $presenter->present($accountUnblocked)->action);
        $visibleIds = app(AuditQuery::class)->build([])->pluck('id')->all();
        foreach ([
            $auditLogView, $settingsChange, $profileChanged, $profileUpdated,
            $ipBlocked, $blocked, $authorizationDenied, $accessDenied, $denied,
            $exported, $nonReportViewed, $reportTemplateEdit,
            ...$retiredConfigurationEvents,
        ] as $retiredEvent) {
            $this->assertNotContains($retiredEvent->id, $visibleIds);
        }
        $this->assertNotContains($reportCreated->id, $visibleIds);

        $this->artisan('audit:backfill-oversight')->assertSuccessful();

        $this->assertSame(AuditAction::Updated->value, $priceCorrection->refresh()->event);
        $this->assertSame('Price', $priceCorrection->properties['details']['subject_label']);
        $this->assertSame(AuditAction::Failed->value, $loginFailure->refresh()->event);
        $this->assertSame('Login attempt', $loginFailure->properties['details']['subject_label']);
        $this->assertSame(AuditAction::Suspended->value, $accountBlocked->refresh()->event);
        $this->assertSame('User account', $accountBlocked->properties['details']['subject_label']);
        $this->assertSame(AuditAction::Unsuspended->value, $accountUnblocked->refresh()->event);
        $this->assertSame('User account', $accountUnblocked->properties['details']['subject_label']);
        $this->assertDatabaseMissing('activity_log', ['id' => $ipBlocked->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $blocked->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $authorizationDenied->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $accessDenied->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $denied->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $exported->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $reportCreated->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $nonReportViewed->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $profileChanged->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $profileUpdated->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $reportTemplateEdit->id]);
        foreach ($retiredConfigurationEvents as $retiredEvent) {
            $this->assertDatabaseMissing('activity_log', ['id' => $retiredEvent->id]);
        }
        $this->assertSame(AuditModule::FoodServiceOperations, $foodServiceReport->refresh()->module);
        $this->assertSame(AuditModule::NutritionCare, $nutritionCareReport->refresh()->module);
        $this->assertDatabaseMissing('activity_log', ['id' => $auditLogView->id]);
        $this->assertDatabaseMissing('activity_log', ['id' => $settingsChange->id]);
        $this->assertSame(AuditAction::Updated->value, $unknown->refresh()->event);
        $this->assertSame('Record', $unknown->properties['details']['subject_label']);
    }
}
