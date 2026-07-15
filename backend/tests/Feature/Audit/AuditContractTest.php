<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\AuditActivity;
use App\Services\Audit\AuditEventPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Tests\TestCase;

class AuditContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_taxonomy_exposes_the_canonical_values_and_labels(): void
    {
        $actions = [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'viewed' => 'Viewed',
            'downloaded' => 'Downloaded',
            'exported' => 'Exported',
            'approved' => 'Approved',
            'ordered' => 'Ordered',
            'received' => 'Received',
            'reversed' => 'Reversed',
            'archived' => 'Archived',
            'adjusted' => 'Adjusted',
            'uploaded' => 'Uploaded',
            'imported' => 'Imported',
            'generated' => 'Generated',
            'completed' => 'Completed',
            'price_corrected' => 'Price corrected',
            'profile_changed' => 'Profile changed',
            'settings_changed' => 'Settings changed',
            'login_succeeded' => 'Login succeeded',
            'login_failed' => 'Login failed',
            'authentication_failed' => 'Authentication failed',
            'logout' => 'Logout',
            'password_changed' => 'Password changed',
            'password_reset' => 'Password reset',
            'recovery_email_changed' => 'Recovery email changed',
            'recovery_email_verified' => 'Recovery email verified',
            'rate_limit_exceeded' => 'Rate limit exceeded',
            'authorization_denied' => 'Authorization denied',
            'audit_log_viewed' => 'Audit log viewed',
            'account_blocked' => 'Account blocked',
            'account_unblocked' => 'Account unblocked',
        ];

        $this->assertSame(array_keys($actions), array_column(AuditAction::cases(), 'value'));
        $this->assertSame($actions, collect(AuditAction::cases())->mapWithKeys(
            fn (AuditAction $action): array => [$action->value => $action->label()],
        )->all());

        $this->assertSame(['security', 'clinical', 'operations'], array_column(AuditCategory::cases(), 'value'));
        $this->assertSame([
            'security' => 'Security',
            'clinical' => 'Clinical',
            'operations' => 'Operations',
        ], collect(AuditCategory::cases())->mapWithKeys(
            fn (AuditCategory $category): array => [$category->value => $category->label()],
        )->all());
        $this->assertSame([
            'accounts', 'patients', 'ncp', 'reports', 'budget', 'procurement',
            'food_service', 'nutrition_library', 'system',
        ], array_column(AuditDomain::cases(), 'value'));
        $this->assertSame([
            'accounts' => 'Accounts',
            'patients' => 'Patients',
            'ncp' => 'NCP',
            'reports' => 'Reports',
            'budget' => 'Budget',
            'procurement' => 'Procurement',
            'food_service' => 'Food service',
            'nutrition_library' => 'Nutrition library',
            'system' => 'System',
        ], collect(AuditDomain::cases())->mapWithKeys(
            fn (AuditDomain $domain): array => [$domain->value => $domain->label()],
        )->all());
        $this->assertSame([
            'security_administration' => 'Security & Administration',
            'nutrition_care' => 'Nutrition Care',
            'food_service_operations' => 'Food Service Operations',
            'reports' => 'Reports',
        ], collect(AuditModule::cases())->mapWithKeys(
            fn (AuditModule $module): array => [$module->value => $module->label()],
        )->all());
        $this->assertSame(['success', 'failure', 'blocked'], array_column(AuditOutcome::cases(), 'value'));
        $this->assertSame([
            'success' => 'Success',
            'failure' => 'Failure',
            'blocked' => 'Blocked',
        ], collect(AuditOutcome::cases())->mapWithKeys(
            fn (AuditOutcome $outcome): array => [$outcome->value => $outcome->label()],
        )->all());
        $this->assertSame(['info', 'notice', 'warning', 'critical'], array_column(AuditSeverity::cases(), 'value'));
        $this->assertSame([
            'info' => 'Info',
            'notice' => 'Notice',
            'warning' => 'Warning',
            'critical' => 'Critical',
        ], collect(AuditSeverity::cases())->mapWithKeys(
            fn (AuditSeverity $severity): array => [$severity->value => $severity->label()],
        )->all());

        foreach ([AuditAction::cases(), AuditCategory::cases(), AuditDomain::cases(), AuditModule::cases(), AuditOutcome::cases(), AuditSeverity::cases()] as $cases) {
            foreach ($cases as $case) {
                $this->assertNotSame('', $case->label());
            }
        }

        $this->assertSame('audit', AuditCategory::Security->logName());
    }

    public function test_activity_log_has_nullable_metadata_and_query_indexes_without_an_extra_correlation_uuid(): void
    {
        $this->assertTrue(Schema::hasColumns('activity_log', [
            'public_id', 'subject_public_id', 'context_public_id', 'category', 'domain',
            'module', 'patient_display_name_snapshot', 'severity', 'outcome',
            'context_type', 'context_id', 'batch_uuid',
        ]));
        $this->assertFalse(Schema::hasColumn('activity_log', 'correlation_uuid'));

        $schemaIndexes = collect(Schema::getIndexes('activity_log'));
        $indexes = $schemaIndexes
            ->map(fn (array $index): array => $index['columns'])
            ->values()
            ->all();

        $this->assertContains(['log_name', 'created_at', 'id'], $indexes);
        $this->assertContains(['category', 'created_at', 'id'], $indexes);
        $this->assertContains(['event', 'created_at', 'id'], $indexes);
        $this->assertContains(['context_type', 'context_id', 'created_at', 'id'], $indexes);
        $this->assertContains(['causer_type', 'causer_id', 'created_at', 'id'], $indexes);
        $this->assertContains(['subject_public_id', 'created_at', 'id'], $indexes);
        $this->assertContains(['context_public_id', 'created_at', 'id'], $indexes);
        $this->assertTrue((bool) $schemaIndexes->firstWhere('name', 'activity_log_public_id_unique')['unique']);

        $columns = collect(Schema::getColumns('activity_log'))->keyBy('name');

        foreach (['category', 'domain', 'severity', 'outcome', 'context_type', 'context_id'] as $column) {
            $this->assertTrue($columns->get($column)['nullable']);
        }
    }

    public function test_spatie_uses_the_custom_activity_model_and_legacy_rows_remain_readable(): void
    {
        $this->assertSame(AuditActivity::class, config('activitylog.activity_model'));
        $this->assertSame('activity_log', config('activitylog.table_name'));
        $this->assertNull(config('activitylog.delete_records_older_than_days'));
        $this->assertFalse(config('audit.features.export'));

        $id = DB::table('activity_log')->insertGetId([
            'log_name' => 'default',
            'description' => 'Legacy activity',
            'properties' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activity = AuditActivity::findOrFail($id);

        $this->assertInstanceOf(ActivityContract::class, $activity);
        $this->assertNull($activity->category);
        $this->assertNull($activity->domain);
        $this->assertNull($activity->severity);
        $this->assertNull($activity->outcome);
        $this->assertSame('Legacy activity', $activity->description);
    }

    public function test_generic_spatie_cleanup_cannot_bypass_category_retention_or_legal_holds(): void
    {
        $activity = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Held audit activity',
            'event' => AuditAction::Updated->value,
            'category' => AuditCategory::Clinical,
            'created_at' => now()->subYears(10),
        ]);

        $this->artisan('activitylog:clean', ['--days' => 1, '--force' => true])
            ->expectsOutputToContain('disabled')
            ->assertExitCode(1);

        $this->assertModelExists($activity);
    }

    public function test_legacy_metadata_defaults_are_added_only_when_presented(): void
    {
        $activity = AuditActivity::create([
            'log_name' => 'default',
            'description' => 'Legacy activity',
            'event' => null,
        ]);

        $payload = app(AuditEventPresenter::class)->present($activity)->toArray();

        $this->assertSame('updated', $payload['action']);
        $this->assertSame('operations', $payload['category']);
        $this->assertSame('system', $payload['domain']);
        $this->assertSame('info', $payload['severity']);
        $this->assertSame('success', $payload['outcome']);
        $this->assertSame('Updated audit event', $payload['summary']);

        $activity->refresh();
        $this->assertNull($activity->category);
        $this->assertNull($activity->domain);
        $this->assertNull($activity->severity);
        $this->assertNull($activity->outcome);
        $this->assertNull($activity->event);
    }

    public function test_audit_only_scope_filters_the_audit_channel(): void
    {
        $audit = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Audit',
        ]);
        AuditActivity::create([
            'log_name' => 'default',
            'description' => 'Default',
        ]);

        $this->assertSame([$audit->id], AuditActivity::query()->auditOnly()->pluck('id')->all());
    }

    public function test_for_category_scope_accepts_an_enum_and_filters_the_category(): void
    {
        $clinical = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Clinical',
            'category' => AuditCategory::Clinical,
        ]);
        AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Security',
            'category' => AuditCategory::Security,
        ]);

        $this->assertSame(
            [$clinical->id],
            AuditActivity::query()->forCategory(AuditCategory::Clinical)->pluck('id')->all(),
        );
    }

    public function test_for_context_scope_filters_by_morph_type_and_key(): void
    {
        $context = new class extends Model
        {
            protected $table = 'users';
        };
        $context->setAttribute('id', 42);
        $context->exists = true;

        $matching = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Matching context',
            'context_type' => $context->getMorphClass(),
            'context_id' => 42,
        ]);
        AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Other context',
            'context_type' => $context->getMorphClass(),
            'context_id' => 43,
        ]);

        $this->assertSame([$matching->id], AuditActivity::query()->forContext($context)->pluck('id')->all());
        $this->assertSame(
            [$matching->id],
            AuditActivity::query()->forContext($context->getMorphClass(), '42')->pluck('id')->all(),
        );
    }

    public function test_for_context_scope_rejects_ambiguous_or_invalid_context_identifiers(): void
    {
        $savedContext = new class extends Model
        {
            protected $table = 'users';
        };
        $savedContext->setAttribute('id', 42);
        $savedContext->exists = true;

        $unsavedContext = new class extends Model
        {
            protected $table = 'users';
        };

        $invalidCalls = [
            'unsaved model' => fn () => AuditActivity::query()->forContext($unsavedContext),
            'model with extra identifier' => fn () => AuditActivity::query()->forContext($savedContext, 42),
            'missing raw identifier' => fn () => AuditActivity::query()->forContext('users'),
            'empty context type' => fn () => AuditActivity::query()->forContext('', 42),
            'zero identifier' => fn () => AuditActivity::query()->forContext('users', 0),
            'negative identifier' => fn () => AuditActivity::query()->forContext('users', -1),
            'non-numeric identifier' => fn () => AuditActivity::query()->forContext('users', 'abc'),
            'decimal identifier' => fn () => AuditActivity::query()->forContext('users', '1.5'),
            'leading-zero identifier' => fn () => AuditActivity::query()->forContext('users', '042'),
            'identifier above unsigned bigint' => fn () => AuditActivity::query()->forContext('users', '18446744073709551616'),
        ];

        foreach ($invalidCalls as $label => $call) {
            try {
                $call();
                $this->fail("The {$label} context should be rejected.");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_from_date_scope_includes_the_start_boundary_and_uses_a_range_comparison(): void
    {
        $boundary = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Start boundary',
            'created_at' => CarbonImmutable::parse('2026-07-10 00:00:00'),
        ]);
        AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Before start',
            'created_at' => CarbonImmutable::parse('2026-07-09 23:59:59'),
        ]);

        $query = AuditActivity::query()->fromDate('2026-07-10');

        $this->assertSame([$boundary->id], $query->pluck('id')->all());
        $this->assertStringContainsString('`created_at` >= ?', $query->toSql());
        $this->assertStringNotContainsString('date(', strtolower($query->toSql()));
    }

    public function test_to_date_scope_includes_the_end_boundary_and_uses_a_range_comparison(): void
    {
        $boundary = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'End boundary',
            'created_at' => CarbonImmutable::parse('2026-07-10 23:59:59'),
        ]);
        AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'After end',
            'created_at' => CarbonImmutable::parse('2026-07-11 00:00:00'),
        ]);

        $query = AuditActivity::query()->toDate('2026-07-10');

        $this->assertSame([$boundary->id], $query->pluck('id')->all());
        $this->assertStringContainsString('`created_at` <= ?', $query->toSql());
        $this->assertStringNotContainsString('date(', strtolower($query->toSql()));
    }

    public function test_between_dates_scope_includes_both_boundaries_and_uses_range_comparisons(): void
    {
        $start = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Start boundary',
            'created_at' => CarbonImmutable::parse('2026-07-10 00:00:00'),
        ]);
        $end = AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'End boundary',
            'created_at' => CarbonImmutable::parse('2026-07-10 23:59:59'),
        ]);
        AuditActivity::create([
            'log_name' => 'audit',
            'description' => 'Outside range',
            'created_at' => CarbonImmutable::parse('2026-07-11 00:00:00'),
        ]);

        $query = AuditActivity::query()->betweenDates('2026-07-10', '2026-07-10');

        $this->assertSame([$start->id, $end->id], $query->pluck('id')->all());
        $this->assertStringContainsString('`created_at` >= ?', $query->toSql());
        $this->assertStringContainsString('`created_at` <= ?', $query->toSql());
        $this->assertStringNotContainsString('date(', strtolower($query->toSql()));
    }

    public function test_metadata_migration_is_reversible(): void
    {
        $database = 'nutriscope_audit_contract_'.Str::lower(Str::random(12));
        $adminConnection = 'audit_contract_admin';
        $testConnection = 'audit_contract_migration';
        $originalActivityConnection = config('activitylog.database_connection');
        $simulatedInterruptionWasHandled = false;
        $connectionConfig = config('database.connections.mysql');
        $connectionConfig['url'] = 'mysql://root@127.0.0.1/nutriscope_test';

        config([
            "database.connections.{$adminConnection}" => [
                ...$connectionConfig,
                'url' => null,
                'database' => 'information_schema',
            ],
            "database.connections.{$testConnection}" => [
                ...$connectionConfig,
                'url' => null,
                'database' => $database,
            ],
        ]);

        $this->assertNull(config("database.connections.{$adminConnection}.url"));
        $this->assertNull(config("database.connections.{$testConnection}.url"));

        try {
            DB::connection($adminConnection)->statement(
                "CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
            );

            config(['activitylog.database_connection' => $testConnection]);
            $schema = Schema::connection($testConnection);
            $schema->create('activity_log', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('log_name')->nullable();
                $table->text('description');
                $table->nullableMorphs('subject', 'subject');
                $table->string('event')->nullable();
                $table->nullableMorphs('causer', 'causer');
                $table->json('properties')->nullable();
                $table->uuid('batch_uuid')->nullable();
                $table->timestamps();
            });

            $migration = require database_path('migrations/2026_07_11_000001_add_metadata_and_indexes_to_activity_log_table.php');
            $migration->up();

            $actorIndexMigration = require database_path('migrations/2026_07_12_090108_add_actor_query_index_to_activity_log_table.php');
            $actorIndexMigration->up();

            DB::connection($testConnection)->table('activity_log')->insert([
                [
                    'log_name' => 'audit',
                    'description' => 'Legacy audit row one',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'log_name' => 'audit',
                    'description' => 'Legacy audit row two',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            $legacyRows = collect(range(3, 1001))->map(fn (int $id): array => [
                'log_name' => 'audit',
                'description' => "Legacy audit row {$id}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $legacyRows->chunk(250)->each(
                fn ($rows) => DB::connection($testConnection)->table('activity_log')->insert($rows->all()),
            );
            $publicIdMigration = require database_path('migrations/2026_07_12_092936_add_public_id_to_activity_log_table.php');
            $publicIdBackfill = require database_path('migrations/2026_07_12_092937_backfill_activity_log_public_ids.php');
            $publicIdMigration->up();
            $retainedPublicId = (string) Str::uuid();
            DB::connection($testConnection)->table('activity_log')->where('id', 1)->update(['public_id' => $retainedPublicId]);
            DB::connection($testConnection)->flushQueryLog();
            DB::connection($testConnection)->enableQueryLog();
            $publicIdBackfill->up();
            $publicIdUpdates = collect(DB::connection($testConnection)->getQueryLog())
                ->filter(fn (array $query): bool => str_starts_with(strtolower($query['query']), 'update '))
                ->count();
            DB::connection($testConnection)->disableQueryLog();
            $this->assertSame(2, $publicIdUpdates, 'The UUID backfill must use one update per 500-ID range.');
            $this->assertSame($retainedPublicId, DB::connection($testConnection)->table('activity_log')->where('id', 1)->value('public_id'));
            $this->assertTrue(Uuid::isValid(DB::connection($testConnection)->table('activity_log')->value('public_id')));
            $this->assertSame(1001, DB::connection($testConnection)->table('activity_log')->distinct()->count('public_id'));
            $publicIdBackfill->down();
            $this->assertSame(1001, DB::connection($testConnection)->table('activity_log')->whereNotNull('public_id')->count());
            $publicIdBackfill->up();

            $subjectPublicId = (string) Str::uuid();
            $contextPublicId = (string) Str::uuid();
            DB::connection($testConnection)->table('activity_log')->update([
                'properties' => json_encode(['details' => [
                    'public_id' => $subjectPublicId,
                    'context_public_id' => $contextPublicId,
                ]], JSON_THROW_ON_ERROR),
            ]);
            $publicReferenceMigration = require database_path('migrations/2026_07_12_095710_add_public_references_to_activity_log_table.php');
            $publicReferenceBackfill = require database_path('migrations/2026_07_12_095711_backfill_activity_log_public_references.php');
            $publicReferenceMigration->up();
            $retainedSubjectPublicId = (string) Str::uuid();
            DB::connection($testConnection)->table('activity_log')->where('id', 1)->update([
                'subject_public_id' => $retainedSubjectPublicId,
            ]);
            DB::connection($testConnection)->flushQueryLog();
            DB::connection($testConnection)->enableQueryLog();
            $publicReferenceBackfill->up();
            $publicReferenceUpdates = collect(DB::connection($testConnection)->getQueryLog())
                ->filter(fn (array $query): bool => str_starts_with(strtolower($query['query']), 'update '))
                ->count();
            DB::connection($testConnection)->disableQueryLog();
            $this->assertSame(3, $publicReferenceUpdates, 'The reference backfill must use one CASE update per 500-row chunk.');
            $this->assertSame($retainedSubjectPublicId, DB::connection($testConnection)->table('activity_log')->where('id', 1)->value('subject_public_id'));
            $this->assertSame($contextPublicId, DB::connection($testConnection)->table('activity_log')->where('id', 1)->value('context_public_id'));
            $this->assertSame($subjectPublicId, DB::connection($testConnection)->table('activity_log')->where('id', 2)->value('subject_public_id'));
            $publicReferenceBackfill->down();
            DB::connection($testConnection)->flushQueryLog();
            DB::connection($testConnection)->enableQueryLog();
            $publicReferenceBackfill->up();
            $this->assertSame(0, collect(DB::connection($testConnection)->getQueryLog())
                ->filter(fn (array $query): bool => str_starts_with(strtolower($query['query']), 'update '))
                ->count());
            DB::connection($testConnection)->disableQueryLog();
            $publicReferenceMigration->down();
            $this->assertFalse($schema->hasColumn('activity_log', 'subject_public_id'));
            $publicIdMigration->down();
            $this->assertFalse($schema->hasColumn('activity_log', 'public_id'));
            $publicIdMigration->up();
            $publicIdBackfill->up();
            $publicReferenceMigration->up();
            $publicReferenceBackfill->up();
            $this->assertContains(
                ['causer_type', 'causer_id', 'created_at', 'id'],
                collect($schema->getIndexes('activity_log'))->pluck('columns')->all(),
            );
            $actorIndexMigration->down();
            $this->assertNotContains(
                ['causer_type', 'causer_id', 'created_at', 'id'],
                collect($schema->getIndexes('activity_log'))->pluck('columns')->all(),
            );
            $actorIndexMigration->up();

            try {
                try {
                    $migration->down();

                    $this->assertFalse($schema->hasColumn('activity_log', 'category'));
                    $this->assertTrue($schema->hasColumn('activity_log', 'batch_uuid'));

                    throw new \RuntimeException('Simulated interrupted rollback verification.');
                } finally {
                    if (! $schema->hasColumn('activity_log', 'category')) {
                        $migration->up();
                    }
                }
            } catch (\RuntimeException $exception) {
                $this->assertSame('Simulated interrupted rollback verification.', $exception->getMessage());
                $simulatedInterruptionWasHandled = true;
            }

            $this->assertTrue($simulatedInterruptionWasHandled);
            $this->assertTrue($schema->hasColumn('activity_log', 'category'));
        } finally {
            config(['activitylog.database_connection' => $originalActivityConnection]);
            DB::purge($testConnection);
            DB::connection($adminConnection)->statement("DROP DATABASE IF EXISTS `{$database}`");
            DB::purge($adminConnection);
        }
    }
}
