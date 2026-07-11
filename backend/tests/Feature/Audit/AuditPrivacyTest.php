<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Events\PurchaseOrderCompleted;
use App\Http\Resources\Admin\AuditLogResource;
use App\Listeners\BudgetLedgerListener;
use App\Models\Assessment;
use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\FsItem;
use App\Models\Intervention;
use App\Models\Inventory;
use App\Models\MealPlan;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\Audit\AuditContextResolver;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Activitylog\Facades\LogBatch;
use Tests\TestCase;

class AuditPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_logger_records_canonical_metadata_subject_context_and_actor_snapshot(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $activity = app(AuditLogger::class)->record(
            AuditAction::Viewed,
            AuditCategory::Clinical,
            AuditDomain::Patients,
            subject: $patient,
            context: $patient,
            details: ['route' => 'patients.show'],
            actor: $actor,
        );

        $this->assertSame('audit', $activity->log_name);
        $this->assertSame(AuditAction::Viewed->value, $activity->event);
        $this->assertSame(AuditCategory::Clinical, $activity->category);
        $this->assertSame(AuditDomain::Patients, $activity->domain);
        $this->assertSame($patient->getMorphClass(), $activity->subject_type);
        $this->assertSame($patient->getKey(), $activity->subject_id);
        $this->assertSame($patient->getMorphClass(), $activity->context_type);
        $this->assertSame($patient->getKey(), $activity->context_id);
        $this->assertTrue($activity->causer->is($actor));
        $this->assertSame([
            'public_id' => $actor->uuid,
            'name' => $actor->name,
            'role' => $actor->role,
            'kind' => 'user',
        ], $activity->properties['actor']);
        $this->assertSame(['route' => 'patients.show'], $activity->properties['details']);
    }

    public function test_trait_subject_preserves_nondefault_manual_metadata_and_context(): void
    {
        $patient = Patient::factory()->create();
        $budget = Budget::factory()->create();

        $activity = app(AuditLogger::class)->record(
            AuditAction::AuthorizationDenied,
            AuditCategory::Security,
            AuditDomain::Accounts,
            subject: $patient,
            context: $budget,
            outcome: AuditOutcome::Blocked,
            severity: AuditSeverity::Critical,
        );

        $this->assertSame(AuditCategory::Security, $activity->category);
        $this->assertSame(AuditDomain::Accounts, $activity->domain);
        $this->assertSame(AuditOutcome::Blocked, $activity->outcome);
        $this->assertSame(AuditSeverity::Critical, $activity->severity);
        $this->assertSame($budget->getMorphClass(), $activity->context_type);
        $this->assertSame($budget->id, $activity->context_id);
    }

    public function test_nested_forbidden_keys_and_format_variants_are_removed(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::LoginFailed,
            AuditCategory::Security,
            AuditDomain::Accounts,
            details: [
                'safe' => 'retained',
                'nested' => [
                    'Access-Token' => 'FORBIDDEN-SENTINEL',
                    'verificationCode' => 'FORBIDDEN-SENTINEL',
                    'request_body' => ['safe' => 'FORBIDDEN-SENTINEL'],
                ],
            ],
        );

        $encoded = $activity->properties->toJson();

        $this->assertSame('retained', $activity->properties['details']['safe']);
        $this->assertStringNotContainsString('FORBIDDEN-SENTINEL', $encoded);
        $this->assertStringNotContainsString('Access-Token', $encoded);
        $this->assertStringNotContainsString('verificationCode', $encoded);
    }

    public function test_non_ascii_schema_keys_and_normalized_key_collisions_fail_closed(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::Updated,
            AuditCategory::Operations,
            AuditDomain::System,
            details: [
                "passw\u{043E}rd" => 'HOMOGLYPH-SENTINEL',
                'safe-key' => 'COLLISION-ONE',
                'safe_key' => 'COLLISION-TWO',
            ],
        );

        $encoded = $activity->properties->toJson();
        $this->assertStringNotContainsString('HOMOGLYPH-SENTINEL', $encoded);
        $this->assertStringNotContainsString('COLLISION-ONE', $encoded);
        $this->assertStringNotContainsString('COLLISION-TWO', $encoded);
    }

    public function test_overlength_keys_cannot_collide_after_storage_truncation(): void
    {
        $prefix = str_repeat('a', 128);
        $activity = app(AuditLogger::class)->record(
            AuditAction::Updated,
            AuditCategory::Operations,
            AuditDomain::System,
            details: [
                $prefix.'x' => 'LONG-COLLISION-ONE',
                $prefix.'y' => 'LONG-COLLISION-TWO',
            ],
        );

        $encoded = $activity->properties->toJson();
        $this->assertStringNotContainsString('LONG-COLLISION-ONE', $encoded);
        $this->assertStringNotContainsString('LONG-COLLISION-TWO', $encoded);
    }

    public function test_nonclinical_semantic_secret_and_phi_keys_are_removed_recursively(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::Updated,
            AuditCategory::Operations,
            AuditDomain::System,
            details: [
                'safe' => 'retained',
                'nested' => [
                    'payload' => 'FORBIDDEN-SENTINEL',
                    'credentials' => 'FORBIDDEN-SENTINEL',
                    'otp' => 'FORBIDDEN-SENTINEL',
                    'output' => 'FORBIDDEN-SENTINEL',
                    'file_contents' => 'FORBIDDEN-SENTINEL',
                    'medicalDiagnosis' => 'FORBIDDEN-SENTINEL',
                    'weight' => 'FORBIDDEN-SENTINEL',
                ],
            ],
        );

        $this->assertSame('retained', $activity->properties['details']['safe']);
        $this->assertStringNotContainsString('FORBIDDEN-SENTINEL', $activity->properties->toJson());
    }

    public function test_email_shaped_values_are_masked_inside_numeric_arrays(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::LoginFailed,
            AuditCategory::Security,
            AuditDomain::Accounts,
            details: ['identifiers' => [' Victim.Example@Example.COM ']],
        );

        $value = $activity->properties['details']['identifiers'][0];
        $this->assertMatchesRegularExpression('/^v\*+@e\*+\.com$/', $value);
        $this->assertStringNotContainsString('victim.example@example.com', strtolower($activity->properties->toJson()));
    }

    public function test_all_url_forms_are_scrubbed_without_userinfo_query_or_fragment(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::Viewed,
            AuditCategory::Operations,
            AuditDomain::Reports,
            details: [
                'absolute_url' => 'https://user:pass@example.test/reports/42?token=x#private',
                'relative_url' => '/reports/42?token=x#private',
                'scheme_relative_url' => '//user:pass@example.test/reports/42?token=x#private',
                'non_http_url' => 'file:///secret/report.txt?token=x#private',
            ],
        );

        $this->assertSame('https://example.test/reports/42', $activity->properties['details']['absolute_url']);
        $this->assertSame('/reports/42', $activity->properties['details']['relative_url']);
        $this->assertSame('//example.test/reports/42', $activity->properties['details']['scheme_relative_url']);
        $this->assertSame('[redacted-url]', $activity->properties['details']['non_http_url']);
    }

    public function test_clinical_scalar_strings_use_email_masking_and_url_scrubbing(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::Viewed,
            AuditCategory::Clinical,
            AuditDomain::Patients,
            details: [
                'identifier' => 'Patient.Email@Example.COM',
                'route' => '/patients/42?email=Patient.Email@example.com#private',
            ],
        );

        $this->assertMatchesRegularExpression('/^p\*+@e\*+\.com$/', $activity->properties['details']['identifier']);
        $this->assertSame('/patients/42', $activity->properties['details']['route']);
        $this->assertStringNotContainsString('Patient.Email@example.com', $activity->properties->toJson());
    }

    public function test_operational_path_is_scrubbed_as_a_relative_url(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::Viewed,
            AuditCategory::Operations,
            AuditDomain::Reports,
            details: ['path' => '/reports/42?token=PATH-SENTINEL#private'],
        );

        $this->assertSame('/reports/42', $activity->properties['details']['path']);
        $this->assertStringNotContainsString('PATH-SENTINEL', $activity->properties->toJson());
    }

    public function test_absolute_and_scheme_relative_urls_are_capped_on_every_return_path(): void
    {
        $longPath = str_repeat('a', 5000);
        $activity = app(AuditLogger::class)->record(
            AuditAction::Viewed,
            AuditCategory::Operations,
            AuditDomain::Reports,
            details: [
                'absolute_url' => "https://example.test/{$longPath}?token=x",
                'scheme_relative_url' => "//example.test/{$longPath}?token=x",
            ],
        );

        $this->assertLessThanOrEqual(1024, mb_strlen($activity->properties['details']['absolute_url']));
        $this->assertLessThanOrEqual(1024, mb_strlen($activity->properties['details']['scheme_relative_url']));
    }

    public function test_control_characters_are_removed_from_detail_values(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::Viewed,
            AuditCategory::Operations,
            AuditDomain::System,
            details: ['route' => "reports.show\r\nFORGED\0ENTRY"],
        );

        $this->assertSame('reports.showFORGEDENTRY', $activity->properties['details']['route']);
    }

    public function test_detail_values_are_capped(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::Viewed,
            AuditCategory::Operations,
            AuditDomain::System,
            details: ['reason' => str_repeat('x', 5000)],
        );

        $this->assertLessThanOrEqual(1024, mb_strlen($activity->properties['details']['reason']));
    }

    public function test_url_query_and_fragment_are_removed(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::Viewed,
            AuditCategory::Operations,
            AuditDomain::Reports,
            details: ['url' => 'https://example.test/reports/42?token=FORBIDDEN#private'],
        );

        $this->assertSame('https://example.test/reports/42', $activity->properties['details']['url']);
    }

    public function test_anonymous_login_email_is_normalized_and_partially_masked(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::LoginFailed,
            AuditCategory::Security,
            AuditDomain::Accounts,
            details: ['email' => '  Victim.Example@Example.COM  '],
        );

        $encoded = $activity->properties->toJson();

        $this->assertSame('anonymous', $activity->properties['actor']['kind']);
        $this->assertStringNotContainsString('victim.example@example.com', strtolower($encoded));
        $this->assertMatchesRegularExpression('/^v\*+@e\*+\.com$/', $activity->properties['details']['email']);
    }

    public function test_request_metadata_uses_trusted_proxy_ip_and_is_sanitized(): void
    {
        Request::setTrustedProxies(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR);
        $request = Request::create(
            'https://example.test/patients/42?secret=FORBIDDEN#fragment',
            server: [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
                'HTTP_USER_AGENT' => str_repeat('A', 600)."\r\nFORGED",
            ],
        );
        $this->app->instance('request', $request);

        try {
            $activity = app(AuditLogger::class)->record(
                AuditAction::Viewed,
                AuditCategory::Clinical,
                AuditDomain::Patients,
            );
        } finally {
            Request::setTrustedProxies([], -1);
        }

        $this->assertSame('203.0.113.9', $activity->properties['request']['ip']);
        $this->assertSame('203.0.113.9', $activity->properties['ip']);
        $this->assertSame('https://example.test/patients/42', $activity->properties['request']['url']);
        $this->assertLessThanOrEqual(512, mb_strlen($activity->properties['request']['user_agent']));
        $this->assertSame($activity->properties['request']['user_agent'], $activity->properties['user_agent']);
        $this->assertStringNotContainsString("\r", $activity->properties['request']['user_agent']);
        $this->assertStringNotContainsString('FORBIDDEN', $activity->properties->toJson());

        $resource = AuditLogResource::make($activity)->resolve($request);
        $this->assertSame('203.0.113.9', $resource['properties']['ip']);
        $this->assertSame($resource['properties']['request']['user_agent'], $resource['properties']['user_agent']);
    }

    public function test_clinical_detail_values_are_removed_but_field_names_remain(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::Updated,
            AuditCategory::Clinical,
            AuditDomain::Ncp,
            details: [
                'changed_fields' => ['weight', 'medical_diagnosis'],
                'weight' => 'CLINICAL-SENTINEL',
                'nested' => ['medical-diagnosis' => 'CLINICAL-SENTINEL'],
                'payload' => 'CLINICAL-SENTINEL',
            ],
        );

        $this->assertSame(['weight', 'medical_diagnosis'], $activity->properties['details']['changed_fields']);
        $this->assertStringNotContainsString('CLINICAL-SENTINEL', $activity->properties->toJson());
    }

    public function test_adding_a_fake_secret_to_fillable_does_not_expand_audit_attributes(): void
    {
        $patient = new Patient;
        $patient->mergeFillable(['fake_secret']);

        $this->assertNotContains('fake_secret', $patient->getActivitylogOptions()->logAttributes);
    }

    public function test_system_actor_is_sanitized_and_has_no_spatie_causer(): void
    {
        $activity = app(AuditLogger::class)->record(
            AuditAction::Generated,
            AuditCategory::Operations,
            AuditDomain::System,
            systemActor: "nightly-scheduler\r\nFORGED",
        );

        $this->assertSame('system', $activity->properties['actor']['kind']);
        $this->assertSame('nightly-schedulerFORGED', $activity->properties['actor']['name']);
        $this->assertNull($activity->causer_id);
        $this->assertNull($activity->causer_type);
    }

    public function test_deleted_actor_remains_attributable_from_snapshot(): void
    {
        $actor = User::factory()->admin()->create();
        $activity = app(AuditLogger::class)->record(
            AuditAction::AuditLogViewed,
            AuditCategory::Security,
            AuditDomain::Accounts,
            actor: $actor,
        );
        $expected = $activity->properties['actor'];

        $actor->delete();
        $activity->refresh();

        $this->assertEquals($expected, $activity->properties['actor']);
        $this->assertSame($actor->uuid, $activity->properties['actor']['public_id']);
        $this->assertArrayNotHasKey('email', $activity->properties['actor']);
    }

    public function test_explicit_user_and_system_actors_are_rejected_as_ambiguous(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AuditLogger::class)->record(
            AuditAction::Generated,
            AuditCategory::Operations,
            AuditDomain::System,
            actor: User::factory()->create(),
            systemActor: 'scheduler',
        );
    }

    public function test_blank_system_actor_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AuditLogger::class)->record(
            AuditAction::Generated,
            AuditCategory::Operations,
            AuditDomain::System,
            systemActor: " \r\n ",
        );
    }

    public function test_existing_spatie_batch_uuid_is_preserved(): void
    {
        LogBatch::startBatch();

        try {
            $expected = LogBatch::getUuid();
            $activity = app(AuditLogger::class)->record(
                AuditAction::Generated,
                AuditCategory::Operations,
                AuditDomain::System,
                systemActor: 'scheduler',
            );
        } finally {
            LogBatch::endBatch();
        }

        $this->assertNotNull($expected);
        $this->assertSame($expected, $activity->batch_uuid);
    }

    public function test_legacy_login_is_inserted_once_without_event_update(): void
    {
        $actor = User::factory()->rnd()->create();
        DB::flushQueryLog();
        DB::enableQueryLog();

        AuditActivity::create([
            'log_name' => config('audit.log_name'),
            'description' => 'Login succeeded',
            'event' => 'login',
            'category' => AuditCategory::Security,
            'domain' => AuditDomain::Accounts,
            'severity' => AuditSeverity::Info,
            'outcome' => AuditOutcome::Success,
            'causer_type' => $actor->getMorphClass(),
            'causer_id' => $actor->getKey(),
            'properties' => [],
        ]);

        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $activityWrites = $queries->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'activity_log'));

        $this->assertSame(1, $activityWrites->filter(fn (array $query): bool => str_starts_with(strtolower(trim($query['query'])), 'insert'))->count());
        $this->assertSame(0, $activityWrites->filter(fn (array $query): bool => str_starts_with(strtolower(trim($query['query'])), 'update'))->count());
        $this->assertSame(1, AuditActivity::query()->where('event', 'login')->count());
        $this->assertSame(0, AuditActivity::query()->where('event', AuditAction::LoginSucceeded->value)->count());
    }

    public function test_context_resolver_maps_clinical_child_to_ncp_without_loading_phi(): void
    {
        $ncp = NcpRecord::factory()->create();
        $assessment = Assessment::factory()->create(['ncp_record_id' => $ncp->id]);

        $activity = app(AuditLogger::class)->record(
            AuditAction::Viewed,
            AuditCategory::Clinical,
            AuditDomain::Ncp,
            subject: $assessment,
        );

        $this->assertSame($ncp->getMorphClass(), $activity->context_type);
        $this->assertSame($ncp->id, $activity->context_id);
    }

    public function test_explicit_context_rejects_unsupported_or_missing_roots(): void
    {
        $unsupported = Inventory::factory()->create(['fs_item_id' => FsItem::factory()->create()->id]);
        $missing = new Assessment(['ncp_record_id' => null]);
        $missing->id = 999999;
        $missing->exists = true;

        foreach ([$unsupported, $missing] as $context) {
            try {
                app(AuditLogger::class)->record(
                    AuditAction::Viewed,
                    AuditCategory::Operations,
                    AuditDomain::System,
                    context: $context,
                );
                $this->fail('Unsupported context was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_meal_plan_context_resolution_reuses_intervention_lookup_and_missing_reference_is_safe(): void
    {
        $ncp = NcpRecord::factory()->create();
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncp->id]);
        $plans = collect([
            new MealPlan(['intervention_id' => $intervention->id]),
            new MealPlan(['intervention_id' => $intervention->id]),
        ])->each(function (MealPlan $plan, int $index): void {
            $plan->id = 9000 + $index;
            $plan->exists = true;
        });
        $resolver = app(AuditContextResolver::class);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $contexts = $plans->map(fn (MealPlan $plan) => $resolver->resolve($plan));
        $missingPlan = new MealPlan(['intervention_id' => 999999]);
        $missingPlan->id = 999999;
        $missingPlan->exists = true;
        $this->assertNull($resolver->resolve($missingPlan));

        $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'interventions'));
        DB::disableQueryLog();

        $this->assertSame(2, $queries->count());
        $this->assertSame([$ncp->id, $ncp->id], $contexts->map(fn ($context) => $context?->getKey())->all());
    }

    public function test_audit_write_participates_in_caller_transaction(): void
    {
        $before = AuditActivity::count();

        try {
            DB::transaction(function (): void {
                app(AuditLogger::class)->record(
                    AuditAction::Generated,
                    AuditCategory::Operations,
                    AuditDomain::System,
                    systemActor: 'transaction-test',
                );

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }

        $this->assertSame($before, AuditActivity::count());
    }

    public function test_audit_logger_is_the_only_production_activity_writer(): void
    {
        $matches = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match('/\bactivity\s*\(/', file_get_contents($file->getPathname())) === 1) {
                $matches[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        $this->assertSame([str_replace('\\', '/', app_path('Services/Audit/AuditLogger.php'))], $matches);
    }

    public function test_legacy_writer_paths_never_persist_request_sentinels(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'auth-sentinel@example.com',
            'password' => 'AUTH-PASSWORD-SENTINEL',
            'platform' => 'web',
        ])->assertUnauthorized();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'Created User',
            'email' => 'created@example.com',
            'password' => 'ADMIN-PASSWORD-SENTINEL',
            'password_confirmation' => 'ADMIN-PASSWORD-SENTINEL',
            'role' => 'RND',
        ])->assertCreated();

        $resetUser = User::factory()->create([
            'recovery_email' => 'reset@example.com',
            'recovery_email_verified_at' => now(),
        ]);
        $token = Password::broker()->createToken($resetUser);
        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'RESET-PASSWORD-SENTINEL',
            'password_confirmation' => 'RESET-PASSWORD-SENTINEL',
        ])->assertOk();

        $rnd = User::factory()->rnd()->create();
        $this->actingAs($rnd, 'sanctum')->postJson('/api/fss/budgets', [
            'fiscal_year' => 2098,
            'allocated_amount' => 100000,
        ])->assertCreated();
        $this->postJson('/api/fss/budgets/adjust', [
            'fiscal_year' => 2098,
            'type' => 'manual_addition',
            'amount' => 100,
            'reason' => 'BUDGET-REASON-SENTINEL',
            'reference' => 'BUDGET-REFERENCE-SENTINEL',
        ])->assertCreated();

        $shoppingList = ShoppingList::factory()->create(['period_start' => '2098-01-01']);
        $purchaseOrder = PurchaseOrder::factory()->create([
            'shopping_list_id' => $shoppingList->id,
            'total_amount' => 50,
            'po_number' => 'PO-2098-001',
            'notes' => 'LISTENER-NOTES-SENTINEL',
        ]);
        app(BudgetLedgerListener::class)->handle(new PurchaseOrderCompleted($purchaseOrder));

        $auditOutput = AuditActivity::query()->get(['description', 'properties'])->toJson();
        foreach ([
            'auth-sentinel@example.com', 'AUTH-PASSWORD-SENTINEL', 'ADMIN-PASSWORD-SENTINEL',
            'RESET-PASSWORD-SENTINEL', 'BUDGET-REASON-SENTINEL', 'BUDGET-REFERENCE-SENTINEL',
            'LISTENER-NOTES-SENTINEL',
        ] as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $auditOutput);
        }
    }

    public function test_operational_model_values_are_sanitized_before_storage(): void
    {
        $inventory = Inventory::factory()->create(['fs_item_id' => FsItem::factory()->create()->id, 'unit' => 'kg']);

        $inventory->update(['unit' => "g\r\nFORGED"]);

        $activity = $inventory->activities()->where('event', 'updated')->latest()->firstOrFail();
        $this->assertSame('gFORGED', $activity->properties['attributes']['unit']);
        $this->assertSame('kg', $activity->properties['old']['unit']);
    }

    public function test_model_events_store_actor_snapshot_metadata_and_root_context(): void
    {
        $actor = User::factory()->rnd()->create();
        $ncp = NcpRecord::factory()->create();
        $assessment = Assessment::factory()->create(['ncp_record_id' => $ncp->id]);
        $this->actingAs($actor);
        $assessment->activities()->delete();

        $assessment->update(['weight' => 72.5]);

        $activity = $assessment->activities()->where('event', 'updated')->latest()->firstOrFail();
        $this->assertSame($actor->uuid, $activity->properties['actor']['public_id']);
        $this->assertSame(AuditCategory::Clinical, $activity->category);
        $this->assertSame(AuditDomain::Ncp, $activity->domain);
        $this->assertSame($ncp->getMorphClass(), $activity->context_type);
        $this->assertSame($ncp->id, $activity->context_id);
    }
}
