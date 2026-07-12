<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\AuditActivity;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\AuditPolicy;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\AuditQuery;
use App\Services\Audit\SecurityAuditDeduplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class StructuredAuditApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'Admin']);
    }

    public function test_admin_list_returns_only_the_structured_public_contract(): void
    {
        $actor = User::factory()->create(['role' => 'RND', 'email' => 'private@example.test']);
        $patient = Patient::factory()->create();
        $activity = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Updated patient',
            'event' => AuditAction::Updated->value,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Patients,
            'severity' => AuditSeverity::Notice,
            'outcome' => AuditOutcome::Success,
            'causer_type' => $actor->getMorphClass(),
            'causer_id' => $actor->id,
            'subject_type' => $patient->getMorphClass(),
            'subject_id' => $patient->id,
            'properties' => [
                'actor' => ['kind' => 'user', 'public_id' => $actor->uuid, 'name' => $actor->name, 'role' => 'RND'],
                'details' => [
                    'public_id' => $patient->uuid,
                    'changed_fields' => ['medical_diagnosis'],
                    'medical_diagnosis' => 'PHI-SENTINEL',
                ],
                'request' => ['ip' => '203.0.113.25', 'url' => 'https://example.test/patients?token=SECRET'],
                'arbitrary' => 'RAW-SENTINEL',
            ],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson(
            "/api/admin/audit-logs?causer_id={$actor->uuid}&event=updated",
        );

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonStructure([
            'data' => [[
                'id', 'category', 'domain', 'action', 'action_label', 'summary', 'severity', 'outcome',
                'actor' => ['id', 'kind', 'name', 'role'],
                'subject' => ['type', 'id', 'label'],
                'context', 'occurred_at', 'details',
                'changes' => [['field', 'label', 'old_value', 'new_value', 'redacted']],
            ]],
            'links', 'meta',
        ])->assertJsonPath('data.0.category', 'clinical')
            ->assertJsonPath('data.0.id', $activity->public_id)
            ->assertJsonPath('data.0.actor.id', $actor->uuid)
            ->assertJsonPath('data.0.subject.id', $patient->uuid)
            ->assertJsonPath('data.0.changes.0.old_value', null)
            ->assertJsonPath('data.0.changes.0.new_value', null)
            ->assertJsonPath('data.0.changes.0.redacted', true);

        $payload = $response->getContent();
        foreach (['properties', 'subject_type', 'subject_id', 'causer_id', 'updated_at', Patient::class,
            'private@example.test', 'PHI-SENTINEL', 'RAW-SENTINEL', '203.0.113.25', 'token=SECRET'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $payload);
        }
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $response->json('data.0.id'),
        );
    }

    public function test_filters_use_enum_allow_lists_and_public_identifiers(): void
    {
        $actor = User::factory()->create(['role' => 'FSS']);
        $patient = Patient::factory()->create();
        AuditActivity::create([
            'log_name' => 'audit', 'description' => 'Match', 'event' => AuditAction::Created,
            'category' => AuditCategory::Clinical, 'domain' => AuditDomain::Patients,
            'severity' => AuditSeverity::Info, 'outcome' => AuditOutcome::Success,
            'causer_type' => $actor->getMorphClass(), 'causer_id' => $actor->id,
            'subject_type' => $patient->getMorphClass(), 'subject_id' => $patient->id,
            'subject_public_id' => $patient->uuid,
            'created_at' => '2026-07-10 08:00:00',
        ]);
        AuditActivity::create([
            'log_name' => 'audit', 'description' => 'Other', 'event' => AuditAction::Deleted,
            'category' => AuditCategory::Operations, 'domain' => AuditDomain::System,
            'severity' => AuditSeverity::Warning, 'outcome' => AuditOutcome::Failure,
        ]);

        $query = http_build_query([
            'category' => 'clinical', 'domain' => 'patients', 'action' => 'created', 'severity' => 'info',
            'outcome' => 'success', 'actor_id' => $actor->uuid, 'subject_id' => $patient->uuid,
            'start' => '2026-07-10', 'end' => '2026-07-10', 'page' => 1, 'per_page' => 10,
        ]);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/audit-logs?'.$query)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.summary', 'Created patient');

        foreach (['category=nope', 'domain=nope', 'action=nope', 'severity=nope', 'outcome=nope',
            'actor_id=1', 'subject_id=1', 'context_id=1', 'page=0', 'per_page=101',
            'start=2026-07-11&end=2026-07-10'] as $invalid) {
            $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/audit-logs?'.$invalid)->assertUnprocessable();
        }
    }

    public function test_global_list_is_admin_only_audit_channel_only_and_view_logging_is_deduplicated(): void
    {
        AuditActivity::create(['log_name' => 'audit', 'description' => 'Visible']);
        AuditActivity::create(['log_name' => 'default', 'description' => 'Hidden']);

        $rnd = User::factory()->create(['role' => 'RND']);
        $this->actingAs($rnd, 'sanctum')->getJson('/api/admin/audit-logs')->assertForbidden();

        $expectedAuditRows = AuditActivity::query()->auditOnly()->count();
        $first = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/audit-logs')->assertOk();
        $first->assertJsonPath('meta.total', $expectedAuditRows);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/audit-logs?page=2')->assertOk();

        $this->assertSame(1, AuditActivity::query()->auditOnly()
            ->where('event', AuditAction::AuditLogViewed->value)
            ->where('causer_id', $this->admin->id)->count());
    }

    public function test_export_is_disabled_by_default_and_when_enabled_streams_safe_authorized_csv_and_is_always_audited(): void
    {
        AuditActivity::create([
            'log_name' => 'audit', 'description' => 'Clinical event', 'event' => AuditAction::Viewed,
            'category' => AuditCategory::Clinical, 'domain' => AuditDomain::Patients,
            'properties' => [
                'actor' => [
                    'kind' => 'user', 'public_id' => $this->admin->uuid,
                    'name' => '=FORMULA-SENTINEL', 'role' => 'Admin',
                ],
                'request' => ['ip' => '203.0.113.25'],
                'secret' => 'EXPORT-PHI-SENTINEL',
            ],
        ]);

        $this->actingAs($this->admin, 'sanctum')->get('/api/admin/audit-logs/export')->assertNotFound();

        config(['audit.features.export' => true]);
        config(['audit.export.max_rows' => 1]);
        $response = $this->actingAs($this->admin, 'sanctum')->get('/api/admin/audit-logs/export?category=clinical');
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('event_reference,category,domain,action', $csv);
        $this->assertSame(2, count(array_filter(preg_split('/\r?\n/', trim($csv)))));
        $this->assertStringNotContainsString(',=FORMULA-SENTINEL,', $csv);
        $this->assertStringContainsString("'=FORMULA-SENTINEL", $csv);
        foreach (['properties', 'updated_at', '203.0.113.25', 'EXPORT-PHI-SENTINEL'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $csv);
        }

        $this->actingAs($this->admin, 'sanctum')->get('/api/admin/audit-logs/export?category=clinical')->assertOk();
        $this->assertSame(2, AuditActivity::query()->auditOnly()->where('event', AuditAction::Exported->value)->count());
    }

    public function test_dashboard_counts_only_retained_audit_rows_and_rolling_seven_day_activity(): void
    {
        AuditActivity::create(['log_name' => 'audit', 'description' => 'Recent', 'created_at' => now()->subDay()]);
        AuditActivity::create(['log_name' => 'audit', 'description' => 'Old', 'created_at' => now()->subDays(8)]);
        AuditActivity::create(['log_name' => 'default', 'description' => 'Not audit', 'created_at' => now()]);

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/dashboard')
            ->assertOk()->assertJsonPath('data.audit_logs.total', 2)
            ->assertJsonPath('data.audit_logs.last_7_days', 1);
    }

    public function test_common_audit_queries_have_explainable_indexed_plans(): void
    {
        $actor = User::factory()->create();
        $patient = Patient::factory()->create();
        AuditFixture::delete(AuditActivity::query());
        collect(range(1, 3000))->chunk(500)->each(function ($numbers): void {
            DB::table((new AuditActivity)->getTable())->insert($numbers->map(fn (int $number): array => [
                'log_name' => $number % 10 === 0 ? 'audit' : 'default',
                'description' => "Representative operation {$number}",
                'event' => 'updated',
                'category' => 'operations',
                'domain' => 'system',
                'severity' => 'info',
                'outcome' => 'success',
                'properties' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => now()->subMinutes($number),
                'updated_at' => now()->subMinutes($number),
            ])->all());
        });
        AuditActivity::create([
            'log_name' => 'audit', 'description' => 'Plan seed', 'event' => AuditAction::Created,
            'category' => AuditCategory::Clinical, 'causer_type' => $actor->getMorphClass(),
            'causer_id' => $actor->id, 'context_type' => $patient->getMorphClass(), 'context_id' => $patient->id,
            'subject_public_id' => $patient->uuid, 'context_public_id' => $patient->uuid,
        ]);

        $queries = [
            'activity_log_log_created_id_index' => AuditActivity::query()->auditOnly()->orderByDesc('created_at')->orderByDesc('id')->limit(25),
            'activity_log_category_created_id_index' => AuditActivity::query()->auditOnly()->where('category', 'clinical')->fromDate(now()->subDay())->orderByDesc('created_at')->orderByDesc('id')->limit(25),
            'activity_log_event_created_id_index' => AuditActivity::query()->auditOnly()->where('event', 'created')->fromDate(now()->subDay())->orderByDesc('created_at')->orderByDesc('id')->limit(25),
            'activity_log_actor_created_id_index' => AuditActivity::query()->auditOnly()->where('causer_type', $actor->getMorphClass())->where('causer_id', $actor->id)->fromDate(now()->subDay())->orderByDesc('created_at')->orderByDesc('id')->limit(25),
            'activity_log_context_public_created_id_index' => AuditActivity::query()->auditOnly()->where('context_public_id', $patient->uuid)->fromDate(now()->subDay())->orderByDesc('created_at')->orderByDesc('id')->limit(25),
            'activity_log_subject_public_created_id_index' => AuditActivity::query()->auditOnly()->where('subject_public_id', $patient->uuid)->fromDate(now()->subDay())->orderByDesc('created_at')->orderByDesc('id')->limit(25),
        ];
        $table = (new AuditActivity)->getTable();
        DB::statement("ANALYZE TABLE `{$table}`");

        foreach ($queries as $expectedKey => $query) {
            $bindings = $query->getBindings();
            $plan = DB::select('EXPLAIN '.$query->toSql(), $bindings);
            $this->assertNotEmpty($plan);
            $this->assertSame($expectedKey, $plan[0]->key ?? null, $query->toSql());
        }
    }

    public function test_policy_separates_global_category_export_and_owned_trail_abilities(): void
    {
        $policy = app(AuditPolicy::class);
        $rnd = User::factory()->create(['role' => 'RND']);
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);

        $this->assertTrue($policy->viewAny($this->admin));
        $this->assertTrue($policy->viewClinical($this->admin));
        $this->assertTrue($policy->viewSecurity($this->admin));
        $this->assertTrue($policy->export($this->admin));
        $this->assertFalse($policy->viewAny($rnd));
        $this->assertFalse($policy->viewClinical($rnd));
        $this->assertFalse($policy->viewSecurity($rnd));
        $this->assertFalse($policy->export($rnd));
        $this->assertTrue($policy->viewTrail($rnd, $patient));
        $this->assertTrue($policy->viewTrail($rnd, $ncp));
    }

    public function test_public_subject_and_context_snapshots_survive_record_deletion_and_filter_without_model_lookups(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        $this->actingAs($rnd, 'sanctum');

        $activity = app(AuditLogger::class)->record(
            AuditAction::Updated,
            AuditCategory::Clinical,
            AuditDomain::Ncp,
            subject: $ncp,
            context: $patient,
            details: ['changed_fields' => ['status']],
        );

        $this->assertSame($ncp->uuid, $activity->subject_public_id);
        $this->assertSame($patient->uuid, $activity->context_public_id);
        $ncp->delete();
        $patient->delete();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $matches = app(AuditQuery::class)->build([
            'subject_id' => $ncp->uuid,
            'context_id' => $patient->uuid,
        ])->get();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertTrue($matches->contains('id', $activity->id));
        $this->assertLessThanOrEqual(2, $queryCount, 'Public reference filters must not scan model tables.');
    }

    public function test_presenter_uses_domain_allowlists_for_safe_operations_changes_and_security_details(): void
    {
        $operations = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Updated purchase order',
            'event' => AuditAction::Updated->value,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::Procurement,
            'severity' => AuditSeverity::Notice,
            'outcome' => AuditOutcome::Success,
            'properties' => [
                'details' => ['changed_fields' => ['status', 'supplier_name', 'email']],
                'old' => ['status' => 'draft', 'supplier_name' => 'PRIVATE-SUPPLIER', 'email' => 'private@example.test'],
                'attributes' => ['status' => 'approved', 'supplier_name' => 'PRIVATE-SUPPLIER'],
            ],
        ]);
        $security = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Rate limit exceeded',
            'event' => AuditAction::RateLimitExceeded->value,
            'category' => AuditCategory::Security,
            'domain' => AuditDomain::Accounts,
            'severity' => AuditSeverity::Warning,
            'outcome' => AuditOutcome::Blocked,
            'properties' => [
                'details' => [
                    'route_name' => 'auth.login',
                    'limiter' => 'login-ip',
                    'retry_after_seconds' => 60,
                    'email' => 'private@example.test',
                    'url' => 'https://example.test/login?token=SECRET',
                    'ip' => '203.0.113.9',
                    'secret' => 'SECURITY-SECRET',
                ],
            ],
        ]);

        $operationsDto = app(AuditEventPresenter::class)->present($operations)->toArray();
        $this->assertSame([[
            'field' => 'status',
            'label' => 'Status',
            'old_value' => 'draft',
            'new_value' => 'approved',
            'redacted' => false,
        ]], $operationsDto['changes']);

        $securityDto = app(AuditEventPresenter::class)->present($security)->toArray();
        $this->assertSame(
            ['limiter', 'retry_after_seconds', 'route_name'],
            collect($securityDto['details'])->pluck('key')->sort()->values()->all(),
        );
        $encoded = json_encode([$operationsDto, $securityDto], JSON_THROW_ON_ERROR);
        foreach (['PRIVATE-SUPPLIER', 'private@example.test', 'token=SECRET', '203.0.113.9', 'SECURITY-SECRET'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded);
        }
    }

    public function test_clinical_changes_remain_redacted_even_when_safe_status_values_exist(): void
    {
        $activity = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Updated NCP record',
            'event' => AuditAction::Updated->value,
            'category' => AuditCategory::Clinical,
            'domain' => AuditDomain::Ncp,
            'properties' => [
                'details' => ['changed_fields' => ['status']],
                'old' => ['status' => 'draft'],
                'attributes' => ['status' => 'active'],
            ],
        ]);

        $changes = app(AuditEventPresenter::class)->present($activity)->toArray()['changes'];
        $this->assertSame([[
            'field' => 'status', 'label' => 'Status', 'old_value' => null,
            'new_value' => null, 'redacted' => true,
        ]], $changes);
    }

    public function test_presenter_merges_actual_allowlisted_operations_change_keys_without_extras(): void
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft', 'po_number' => 'PO-OLD']);
        AuditFixture::delete(AuditActivity::query());

        $po->update(['status' => 'ordered', 'po_number' => 'PO-PRIVATE-SENTINEL']);
        $activity = AuditActivity::query()->where('event', 'updated')->sole();
        $dto = app(AuditEventPresenter::class)->present($activity)->toArray();

        $this->assertSame([[
            'field' => 'status', 'label' => 'Status', 'old_value' => 'draft',
            'new_value' => 'ordered', 'redacted' => false,
        ]], $dto['changes']);
        $this->assertStringNotContainsString('PO-PRIVATE-SENTINEL', json_encode($dto, JSON_THROW_ON_ERROR));
    }

    public function test_unnamed_parameterized_security_route_is_safely_presented_and_exported(): void
    {
        $request = Request::create('/api/admin/users/target?token=ROUTE-SECRET', 'GET');
        $request->setRouteResolver(fn () => new Route('GET', 'api/admin/users/{user}', fn () => null));
        app(SecurityAuditDeduplicator::class)->record(
            AuditAction::AuthorizationDenied,
            'authorization',
            $request,
            status: 403,
            actor: $this->admin,
        );

        $activity = AuditActivity::query()->where('event', AuditAction::AuthorizationDenied->value)->sole();
        $dto = app(AuditEventPresenter::class)->present($activity)->toArray();
        $this->assertSame('api/admin/users/{user}', collect($dto['details'])->firstWhere('key', 'route_name')['value']);
        $this->assertSame(0, collect($dto['details'])->firstWhere('key', 'previous_recurrence_count')['value']);

        config(['audit.features.export' => true]);
        $csv = $this->actingAs($this->admin, 'sanctum')
            ->get('/api/admin/audit-logs/export?action=authorization_denied')
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('api/admin/users/{user}', $csv);
        $this->assertStringNotContainsString('ROUTE-SECRET', $csv);
        $this->assertStringNotContainsString('?token=', $csv);
    }

    public function test_legacy_presented_defaults_and_action_aliases_are_filterable_without_unrelated_rows(): void
    {
        AuditFixture::delete(AuditActivity::query());
        $legacy = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Legacy login',
            'event' => 'login',
            'category' => null,
            'domain' => null,
            'severity' => null,
            'outcome' => null,
            'properties' => [],
        ]);
        $unrelated = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Unrelated failure',
            'event' => AuditAction::LoginFailed->value,
            'category' => AuditCategory::Security,
            'domain' => AuditDomain::Accounts,
            'severity' => AuditSeverity::Warning,
            'outcome' => AuditOutcome::Failure,
            'properties' => [],
        ]);
        $nullAction = AuditActivity::create([
            'log_name' => 'audit', 'description' => 'Legacy null action', 'event' => null,
            'category' => AuditCategory::Security, 'domain' => AuditDomain::Accounts,
            'severity' => AuditSeverity::Warning, 'outcome' => AuditOutcome::Failure,
            'properties' => [],
        ]);
        $unknownAction = AuditActivity::create([
            'log_name' => 'audit', 'description' => 'Legacy unknown action', 'event' => 'legacy_unknown',
            'category' => AuditCategory::Security, 'domain' => AuditDomain::Accounts,
            'severity' => AuditSeverity::Warning, 'outcome' => AuditOutcome::Failure,
            'properties' => [],
        ]);

        foreach ([
            ['category' => 'operations'],
            ['domain' => 'system'],
            ['severity' => 'info'],
            ['outcome' => 'success'],
            ['action' => 'login_succeeded'],
        ] as $filter) {
            $this->assertSame([$legacy->id], app(AuditQuery::class)->build($filter)->pluck('id')->all());
        }
        $this->assertEqualsCanonicalizing(
            [$nullAction->id, $unknownAction->id],
            app(AuditQuery::class)->build(['action' => 'updated'])->pluck('id')->all(),
        );

        $response = $this->actingAs($this->admin, 'sanctum')->getJson(
            '/api/admin/audit-logs?category=operations&domain=system&severity=info&outcome=success&action=login_succeeded',
        );
        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'login_succeeded');

        config(['audit.features.export' => true]);
        $csv = $this->actingAs($this->admin, 'sanctum')
            ->get('/api/admin/audit-logs/export?action=login_succeeded')
            ->assertOk()->streamedContent();
        $this->assertStringContainsString(app(AuditEventPresenter::class)->present($legacy)->id, $csv);
        $this->assertStringNotContainsString(app(AuditEventPresenter::class)->present($unrelated)->id, $csv);
    }

    public function test_deleted_actor_public_uuid_remains_filterable_in_list_and_export(): void
    {
        $actor = User::factory()->create(['role' => 'FSS', 'name' => 'Former Auditor']);
        $activity = app(AuditLogger::class)->record(
            AuditAction::Updated,
            AuditCategory::Operations,
            AuditDomain::System,
            details: ['changed_fields' => ['status']],
            actor: $actor,
        );
        $actor->delete();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/audit-logs?actor_id={$actor->uuid}&action=updated");
        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activity->public_id)
            ->assertJsonPath('data.0.actor.name', 'Former Auditor');

        config(['audit.features.export' => true]);
        $csv = $this->actingAs($this->admin, 'sanctum')
            ->get("/api/admin/audit-logs/export?actor_id={$actor->uuid}&action=updated")
            ->assertOk()->streamedContent();
        $this->assertStringContainsString($activity->public_id, $csv);
    }
}
