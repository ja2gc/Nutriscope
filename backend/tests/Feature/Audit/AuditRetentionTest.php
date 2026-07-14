<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Exceptions\AuditLoggingUnavailable;
use App\Exceptions\AuditPruneFailed;
use App\Http\Controllers\Controller;
use App\Models\AuditActivity;
use App\Models\AuditSetting;
use App\Models\FsItem;
use App\Models\User;
use App\Services\Audit\AuditHealthMonitor;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\AuditRetentionService;
use App\Services\Audit\AuditRetentionState;
use App\Services\Audit\SecurityAuditDeduplicator;
use Closure;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PDOException;
use RuntimeException;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class AuditRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_state_uses_config_only_until_a_database_row_exists(): void
    {
        config(['audit.features.retention' => false]);

        $fallback = app(AuditRetentionState::class)->current();

        $this->assertFalse($fallback['enabled']);
        $this->assertSame('config', $fallback['source']);

        config(['audit.features.retention' => true]);
        $this->assertTrue(app(AuditRetentionState::class)->enabled());

        AuditSetting::query()->create([
            'key' => AuditSetting::RETENTION_ENABLED,
            'enabled' => false,
        ]);

        $persisted = app(AuditRetentionState::class)->current();

        $this->assertFalse($persisted['enabled']);
        $this->assertSame('database', $persisted['source']);
    }

    public function test_dry_run_reports_category_counts_without_deleting_rows(): void
    {
        config([
            'audit.retention.security' => ['days' => 30, 'legal_hold' => false],
            'audit.retention.clinical' => ['days' => 60, 'legal_hold' => true],
            'audit.retention.operations' => ['days' => 90, 'legal_hold' => false],
            'audit.retention.legacy' => ['days' => 10, 'legal_hold' => false],
        ]);

        $security = $this->activity('security', now()->subDays(31));
        $clinical = $this->activity('clinical', now()->subDays(61));
        $legacy = $this->activity(null, now()->subDays(11));
        $recent = $this->activity('operations', now()->subDay());

        $this->artisan('audit:prune')
            ->expectsOutputToContain('security: 1 eligible')
            ->expectsOutputToContain('clinical: legal hold active')
            ->expectsOutputToContain('operations: 0 eligible')
            ->expectsOutputToContain('legacy: 1 eligible')
            ->expectsOutputToContain('Dry run complete: 2 eligible, 0 deleted, 1 held category.')
            ->assertSuccessful();

        foreach ([$security, $clinical, $legacy, $recent] as $activity) {
            $this->assertModelExists($activity);
        }
    }

    public function test_malformed_retention_config_fails_closed_before_counts_or_deletes(): void
    {
        $activity = $this->activity('security', now()->subYears(10));
        $valid = [
            'security' => ['days' => 365, 'legal_hold' => false],
            'clinical' => ['days' => 2190, 'legal_hold' => false],
            'operations' => ['days' => 1095, 'legal_hold' => false],
            'legacy' => ['days' => 90, 'legal_hold' => false],
        ];
        $invalidPolicies = [
            'missing category' => array_diff_key($valid, ['operations' => true]),
            'missing days' => [...$valid, 'operations' => ['legal_hold' => false]],
            'missing legal hold' => [...$valid, 'operations' => ['days' => 30]],
            'zero days' => [...$valid, 'operations' => ['days' => 0, 'legal_hold' => false]],
            'negative days' => [...$valid, 'operations' => ['days' => -1, 'legal_hold' => false]],
            'string days' => [...$valid, 'operations' => ['days' => '30', 'legal_hold' => false]],
            'float days' => [...$valid, 'operations' => ['days' => 30.0, 'legal_hold' => false]],
            'string legal hold' => [...$valid, 'operations' => ['days' => 30, 'legal_hold' => 'false']],
            'numeric legal hold' => [...$valid, 'operations' => ['days' => 30, 'legal_hold' => 0]],
        ];

        foreach ($invalidPolicies as $label => $retention) {
            config(['audit.retention' => $retention]);
            DB::flushQueryLog();
            DB::enableQueryLog();

            try {
                app(AuditRetentionService::class)->prune(true);
                $this->fail("Malformed retention policy was accepted: {$label}");
            } catch (AuditPruneFailed $exception) {
                $this->assertSame('Audit pruning failed.', $exception->getMessage(), $label);
                $this->assertSame([
                    'eligible_count' => 0,
                    'deleted_count' => 0,
                    'held_category_count' => 0,
                ], $exception->progress(), $label);
            } finally {
                $queries = DB::getQueryLog();
                DB::disableQueryLog();
            }

            $auditQueries = collect($queries)->filter(
                fn (array $query): bool => str_contains(strtolower($query['query']), 'activity_log'),
            );
            $this->assertCount(0, $auditQueries, "Malformed policy queried audit rows: {$label}");
            $this->assertModelExists($activity);
        }
    }

    public function test_force_prunes_expired_rows_in_chunks_and_emits_counts_only(): void
    {
        config([
            'audit.pruning.chunk_size' => 2,
            'audit.retention.security' => ['days' => 30, 'legal_hold' => false],
            'audit.retention.clinical' => ['days' => 60, 'legal_hold' => true],
            'audit.retention.operations' => ['days' => 90, 'legal_hold' => false],
            'audit.retention.legacy' => ['days' => 10, 'legal_hold' => false],
        ]);

        $expiredSecurity = collect(range(1, 5))->map(
            fn (): AuditActivity => $this->activity('security', now()->subDays(31)),
        );
        $heldClinical = $this->activity('clinical', now()->subDays(61));
        $legacy = $this->activity(null, now()->subDays(11));
        $recent = $this->activity('security', now()->subDay());

        $this->artisan('audit:prune --force')
            ->expectsOutputToContain('Prune complete: 6 eligible, 6 deleted, 1 held category.')
            ->assertSuccessful();

        $expiredSecurity->each(fn (AuditActivity $activity) => $this->assertModelMissing($activity));
        $this->assertModelMissing($legacy);
        $this->assertModelExists($heldClinical);
        $this->assertModelExists($recent);

        $completion = AuditActivity::query()
            ->where('event', AuditAction::Completed->value)
            ->where('domain', AuditDomain::System->value)
            ->where('outcome', AuditOutcome::Success->value)
            ->sole();
        $details = $completion->properties->get('details');
        $this->assertSame([
            'deleted_count' => 6,
            'eligible_count' => 6,
            'held_category_count' => 1,
        ], $details);
        $this->assertStringNotContainsString('clinical', json_encode($completion->properties, JSON_THROW_ON_ERROR));
    }

    public function test_audit_rows_refuse_application_updates_and_deletes(): void
    {
        Log::spy();
        $updated = $this->activity('operations', now());
        $deleted = $this->activity('operations', now());

        try {
            $updated->update(['description' => 'Tampered']);
            $this->fail('Audit row update was not refused.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit events are immutable.', $exception->getMessage());
        }

        try {
            $deleted->delete();
            $this->fail('Audit row deletion was not refused.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit events may only be deleted by the retention service.', $exception->getMessage());
        }

        $this->assertModelExists($updated);
        $this->assertModelExists($deleted);

        $bulkMutations = [
            'update' => fn () => AuditActivity::query()->whereKey($updated->id)->update(['description' => 'Bulk tamper']),
            'delete' => fn () => AuditActivity::query()->whereKey($updated->id)->delete(),
            'increment' => fn () => AuditActivity::query()->whereKey($updated->id)->increment('context_id'),
            'decrement' => fn () => AuditActivity::query()->whereKey($updated->id)->decrement('context_id'),
            'incrementEach' => fn () => AuditActivity::query()->whereKey($updated->id)->incrementEach(['context_id' => 1]),
            'decrementEach' => fn () => AuditActivity::query()->whereKey($updated->id)->decrementEach(['context_id' => 1]),
            'upsert' => fn () => AuditActivity::query()->upsert([['id' => $updated->id, 'description' => 'Bulk tamper']], ['id'], ['description']),
            'updateOrInsert' => fn () => AuditActivity::query()->updateOrInsert(['id' => $updated->id], ['description' => 'Bulk tamper']),
        ];
        foreach ($bulkMutations as $operation => $mutation) {
            try {
                $mutation();
                $this->fail("Bulk audit row {$operation} was not refused.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Audit event', $exception->getMessage());
            }
        }

        $this->assertModelExists($updated);
        Log::shouldHaveReceived('critical')->times(10);
    }

    public function test_lowest_database_boundary_blocks_all_audit_update_delete_and_truncate_bypasses(): void
    {
        Log::spy();
        $activity = $this->activity('operations', now());
        $table = (new AuditActivity)->getTable();
        $connection = DB::connection(config('activitylog.database_connection'));
        $attempts = [
            'raw replace' => fn () => DB::statement(
                "REPLACE INTO `{$table}` (`id`, `public_id`, `log_name`, `description`) VALUES (?, ?, ?, ?)",
                [$activity->id, $activity->public_id, config('audit.log_name'), 'replaced'],
            ),
            'insert on duplicate update' => fn () => DB::statement(
                "INSERT INTO `{$table}` (`id`, `public_id`, `log_name`, `description`) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `description` = VALUES(`description`)",
                [$activity->id, $activity->public_id, config('audit.log_name'), 'upserted'],
            ),
            'eloquent toBase update' => fn () => AuditActivity::query()->toBase()->where('id', $activity->id)->update(['description' => 'tampered']),
            'eloquent getQuery delete' => fn () => AuditActivity::query()->getQuery()->where('id', $activity->id)->delete(),
            'direct table update' => fn () => DB::table($table)->where('id', $activity->id)->update(['description' => 'tampered']),
            'direct table delete' => fn () => DB::table($table)->where('id', $activity->id)->delete(),
            'raw update' => fn () => DB::statement("UPDATE `{$table}` SET description = 'tampered' WHERE id = {$activity->id}"),
            'raw delete' => fn () => DB::unprepared("DELETE FROM `{$table}` WHERE id = {$activity->id}"),
            'forwarded truncate' => fn () => $connection->pretend(fn () => DB::table($table)->truncate()),
            'raw truncate' => fn () => $connection->pretend(fn () => DB::statement("TRUNCATE TABLE `{$table}`")),
        ];

        foreach ($attempts as $label => $attempt) {
            try {
                $attempt();
                $this->fail("{$label} was not blocked.");
            } catch (RuntimeException $exception) {
                $this->assertSame('Audit events may only be mutated by the retention service.', $exception->getMessage(), $label);
            }
        }

        $this->assertSame('Retention fixture', $activity->fresh()->description);
        $this->assertSame(1, AuditActivity::query()->whereKey($activity->id)->count(), 'Ordinary audit INSERT must remain allowed.');
        Log::shouldHaveReceived('critical')->times(count($attempts));
    }

    public function test_retention_deletion_scope_is_cleared_when_the_database_delete_throws(): void
    {
        config([
            'audit.retention.security' => ['days' => 30, 'legal_hold' => true],
            'audit.retention.clinical' => ['days' => 60, 'legal_hold' => true],
            'audit.retention.operations' => ['days' => 1, 'legal_hold' => false],
            'audit.retention.legacy' => ['days' => 10, 'legal_hold' => true],
        ]);
        $activity = $this->activity('operations', now()->subDays(2));
        $table = (new AuditActivity)->getTable();
        $connection = DB::connection(config('activitylog.database_connection'));
        $failOnce = true;
        $connection->beforeExecuting(function (string $query) use (&$failOnce, $table): void {
            if ($failOnce && str_contains(strtolower($query), 'delete') && str_contains($query, $table)) {
                $failOnce = false;
                throw new RuntimeException('Forced prune delete failure.');
            }
        });

        try {
            app(AuditRetentionService::class)->prune(true);
            $this->fail('Forced prune delete failure was not raised.');
        } catch (AuditPruneFailed $exception) {
            $this->assertSame('Audit pruning failed.', $exception->getMessage());
            $this->assertSame([
                'eligible_count' => 1,
                'deleted_count' => 0,
                'held_category_count' => 3,
            ], $exception->progress());
        }

        try {
            $connection->table($table)->where('id', $activity->id)->delete();
            $this->fail('Retention authorization leaked after the exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit events may only be mutated by the retention service.', $exception->getMessage());
        }
        $this->assertModelExists($activity);
    }

    public function test_prune_failure_emits_a_safe_failure_event_and_returns_failure(): void
    {
        $this->app->instance(AuditRetentionService::class, new class(app(AuditHealthMonitor::class)) extends AuditRetentionService
        {
            public function prune(bool $force): array
            {
                throw new RuntimeException('DATABASE-PASSWORD-SENTINEL');
            }
        });

        $this->artisan('audit:prune --force')->assertFailed();

        $failure = AuditActivity::query()
            ->where('event', AuditAction::Completed->value)
            ->where('domain', AuditDomain::System->value)
            ->where('outcome', AuditOutcome::Failure->value)
            ->where('severity', AuditSeverity::Critical->value)
            ->sole();
        $this->assertSame([
            'deleted_count' => 0,
            'eligible_count' => 0,
            'held_category_count' => 0,
        ], $failure->properties->get('details'));
        $this->assertStringNotContainsString(
            'DATABASE-PASSWORD-SENTINEL',
            json_encode($failure->properties, JSON_THROW_ON_ERROR),
        );
    }

    public function test_later_prune_chunk_failure_preserves_actual_progress_in_event_and_metrics(): void
    {
        $this->travelTo('2026-07-31 23:00:00');
        config([
            'audit.pruning.chunk_size' => 2,
            'audit.retention.security' => ['days' => 1, 'legal_hold' => false],
            'audit.retention.clinical' => ['days' => 60, 'legal_hold' => true],
            'audit.retention.operations' => ['days' => 90, 'legal_hold' => true],
            'audit.retention.legacy' => ['days' => 10, 'legal_hold' => true],
        ]);
        $expired = collect(range(1, 3))->map(
            fn (): AuditActivity => $this->activity('security', now()->subDays(2)),
        );
        $table = (new AuditActivity)->getTable();
        $deleteAttempt = 0;
        DB::connection(config('activitylog.database_connection'))
            ->beforeExecuting(function (string $query) use (&$deleteAttempt, $table): void {
                if (str_contains(strtolower($query), 'delete') && str_contains($query, $table)) {
                    $deleteAttempt++;
                    if ($deleteAttempt === 2) {
                        throw new RuntimeException('DATABASE-PASSWORD-SENTINEL');
                    }
                }
            });

        $this->artisan('audit:prune --force')->assertFailed();

        $this->assertModelMissing($expired[0]);
        $this->assertModelMissing($expired[1]);
        $this->assertModelExists($expired[2]);
        $failure = AuditActivity::query()
            ->where('event', AuditAction::Completed->value)
            ->where('outcome', AuditOutcome::Failure->value)
            ->sole();
        $this->assertSame([
            'deleted_count' => 2,
            'eligible_count' => 3,
            'held_category_count' => 3,
        ], $failure->properties->get('details'));
        $this->assertStringNotContainsString('DATABASE-PASSWORD-SENTINEL', $failure->properties->toJson());

        Log::spy();
        $this->travelTo('2026-08-01 00:30:00');
        app(AuditHealthMonitor::class)->emitMonthlyMetrics();
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            $this->assertSame('Monthly audit metrics.', $message);
            $this->assertSame(1, $context['prune_runs']);
            $this->assertSame(1, $context['prune_failures']);
            $this->assertSame(3, $context['prune_eligible_rows']);
            $this->assertSame(2, $context['prune_deleted_rows']);

            return true;
        })->once();
    }

    public function test_writer_failures_are_deduplicated_and_reported_without_event_contents(): void
    {
        Log::spy();

        (new AuditLoggingUnavailable('PASSWORD-SENTINEL'))->report();
        (new AuditLoggingUnavailable('TOKEN-SENTINEL'))->report();

        Log::shouldHaveReceived('critical')->withArgs(fn (string $message, array $context): bool => $message === 'Audit writer failure detected.'
            && $context === ['exception_class' => AuditLoggingUnavailable::class]
        )->once();
    }

    public function test_writer_failure_alert_survives_metrics_cache_outage(): void
    {
        Cache::shouldReceive('add')->twice()->andThrow(new RuntimeException('CACHE-PASSWORD-SENTINEL'));
        Log::spy();

        app(AuditHealthMonitor::class)->writerFailure(new AuditLoggingUnavailable('TOKEN-SENTINEL'));

        Log::shouldHaveReceived('critical')->with(
            'Audit writer failure detected.',
            ['exception_class' => AuditLoggingUnavailable::class],
        )->once();
    }

    public function test_cache_outage_uses_bounded_local_writer_alert_window(): void
    {
        config(['audit.monitoring.writer_alert_dedup_seconds' => 60]);
        Cache::shouldReceive('add')->andThrow(new RuntimeException('CACHE-PASSWORD-SENTINEL'));
        Log::spy();
        $monitor = app(AuditHealthMonitor::class);

        $monitor->writerFailure(new AuditLoggingUnavailable('FIRST-TOKEN-SENTINEL'));
        $monitor->writerFailure(new AuditLoggingUnavailable('SECOND-TOKEN-SENTINEL'));
        Log::shouldHaveReceived('critical')->with(
            'Audit writer failure detected.',
            ['exception_class' => AuditLoggingUnavailable::class],
        )->once();

        $this->travel(61)->seconds();
        $monitor->writerFailure(new AuditLoggingUnavailable('THIRD-TOKEN-SENTINEL'));
        Log::shouldHaveReceived('critical')->with(
            'Audit writer failure detected.',
            ['exception_class' => AuditLoggingUnavailable::class],
        )->twice();
    }

    public function test_configured_writer_alert_window_controls_shared_cache_ttl(): void
    {
        config(['audit.monitoring.writer_alert_dedup_seconds' => 10]);
        Log::spy();
        $monitor = app(AuditHealthMonitor::class);

        $monitor->writerFailure(new AuditLoggingUnavailable('FIRST-TOKEN-SENTINEL'));
        $this->travel(11)->seconds();
        $monitor->writerFailure(new AuditLoggingUnavailable('SECOND-TOKEN-SENTINEL'));

        Log::shouldHaveReceived('critical')->with(
            'Audit writer failure detected.',
            ['exception_class' => AuditLoggingUnavailable::class],
        )->twice();
    }

    public function test_manual_audit_insert_failure_rolls_back_required_mutation_and_counts_once(): void
    {
        $this->travelTo('2026-07-31 23:00:00');
        $duplicatePublicId = '00000000-0000-4000-8000-000000000013';
        AuditActivity::create([
            'public_id' => $duplicatePublicId,
            'log_name' => config('audit.log_name'),
            'description' => 'Persistence fixture',
            'event' => AuditAction::Created->value,
            'category' => AuditCategory::Operations->value,
            'domain' => AuditDomain::System->value,
            'properties' => [],
        ]);
        $armed = true;
        AuditActivity::creating(function (AuditActivity $activity) use (&$armed, $duplicatePublicId): void {
            if ($armed) {
                $armed = false;
                $activity->public_id = $duplicatePublicId;
            }
        });
        Log::spy();
        $before = User::query()->count();
        $controller = new class extends Controller
        {
            public function run(Closure $mutation): mixed
            {
                return $this->audited($mutation);
            }
        };

        try {
            $controller->run(function (): void {
                User::factory()->create();
                app(AuditLogger::class)->record(
                    AuditAction::Updated,
                    AuditCategory::Operations,
                    AuditDomain::System,
                    details: ['changed_fields' => ['status']],
                    systemActor: 'Persistence test',
                    includeRequestMetadata: false,
                );
            });
            $this->fail('Required mutation succeeded without its audit event.');
        } catch (AuditLoggingUnavailable $exception) {
            $this->assertSame('The audit event could not be persisted.', $exception->getMessage());
            $exception->report();
            $exception->report();
        }

        $this->assertSame($before, User::query()->count());
        Log::shouldHaveReceived('critical')->with(
            'Audit writer failure detected.',
            ['exception_class' => AuditLoggingUnavailable::class],
        )->once();

        $this->travelTo('2026-08-01 00:30:00');
        app(AuditHealthMonitor::class)->emitMonthlyMetrics();
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            $this->assertSame('Monthly audit metrics.', $message);
            $this->assertSame(1, $context['write_failure_count']);
            $this->assertStringNotContainsString('SENTINEL', json_encode($context, JSON_THROW_ON_ERROR));

            return true;
        })->once();
    }

    public function test_model_spatie_audit_insert_failure_rolls_back_and_reports_once(): void
    {
        $item = FsItem::factory()->create(['category' => 'Original']);
        AuditFixture::delete(AuditActivity::query());
        $this->failNextAuditInsert(new RuntimeException('DATABASE-TOKEN-SENTINEL'));
        Log::spy();

        try {
            DB::transaction(fn () => $item->update(['category' => 'Changed']));
            $this->fail('Model mutation succeeded without its Spatie audit event.');
        } catch (AuditLoggingUnavailable $exception) {
            $exception->report();
            $exception->report();
        }

        $this->assertSame('Original', $item->fresh()->category);
        $this->assertSame(0, AuditActivity::query()->auditOnly()->count());
        Log::shouldHaveReceived('critical')->with(
            'Audit writer failure detected.',
            ['exception_class' => AuditLoggingUnavailable::class],
        )->once();
    }

    public function test_exception_reporter_monitors_only_activity_insert_query_failures_without_duplicates(): void
    {
        $this->travelTo('2026-07-31 23:00:00');
        Log::spy();
        $auditFailure = new QueryException(
            'mysql',
            'insert into `activity_log` (`description`) values (?)',
            ['PHI-BINDING-SENTINEL'],
            new PDOException('DATABASE-PASSWORD-SENTINEL'),
        );
        $unrelatedFailure = new QueryException(
            'mysql',
            'update `users` set `name` = ? where `id` = ?',
            ['UNRELATED-SENTINEL', 1],
            new PDOException('UNRELATED-DATABASE-SENTINEL'),
        );
        $handler = app(ExceptionHandler::class);

        $handler->report($auditFailure);
        $handler->report($auditFailure);
        $handler->report($unrelatedFailure);

        Log::shouldHaveReceived('critical')->with(
            'Audit writer failure detected.',
            ['exception_class' => QueryException::class],
        )->once();
        $this->travelTo('2026-08-01 00:30:00');
        app(AuditHealthMonitor::class)->emitMonthlyMetrics();
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            $this->assertSame('Monthly audit metrics.', $message);
            $this->assertSame(1, $context['write_failure_count']);

            return true;
        })->once();
    }

    public function test_daily_monitor_compares_yesterday_to_trailing_average_and_checks_storage_thresholds(): void
    {
        $this->travelTo('2026-07-13 12:00:00');
        config([
            'audit.monitoring.volume.trailing_days' => 30,
            'audit.monitoring.volume.spike_multiplier' => 3,
            'audit.monitoring.table_bytes_threshold' => 1,
            'audit.monitoring.database_disk_used_percent' => 71,
            'audit.monitoring.database_disk_percent_threshold' => 70,
        ]);
        foreach (range(2, 31) as $daysAgo) {
            $this->activity('operations', now()->subDays($daysAgo)->setTime(12, 0));
        }
        foreach (range(1, 10) as $offset) {
            $this->activity('security', now()->subDay()->setTime(0, $offset));
        }
        Log::spy();

        app(AuditHealthMonitor::class)->inspectDaily();

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Daily audit event volume spike detected.'
            && $context === [
                'event_count' => 10,
                'trailing_daily_average' => 1.0,
                'spike_multiplier' => 3.0,
                'trailing_days' => 30,
            ]
        )->once();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Audit table size threshold exceeded.'
            && is_int($context['retained_bytes'] ?? null)
            && $context['retained_bytes'] >= 1
            && ($context['threshold_bytes'] ?? null) === 1
        )->once();
        Log::shouldHaveReceived('warning')->with(
            'Database disk usage threshold exceeded.',
            ['used_percent' => 71.0, 'threshold_percent' => 70.0],
        )->once();
    }

    public function test_monthly_metrics_are_content_free_aggregates_with_operational_counters(): void
    {
        $this->travelTo('2026-07-31 23:00:00');
        config(['audit.monitoring.slow_query_ms' => 0]);
        $this->activity('operations', now());
        AuditActivity::create([
            'log_name' => config('audit.log_name'),
            'description' => 'PRIVATE-DESCRIPTION-SENTINEL',
            'event' => AuditAction::Created->value,
            'category' => AuditCategory::Clinical->value,
            'domain' => AuditDomain::System->value,
            'properties' => [],
        ]);
        $monitor = app(AuditHealthMonitor::class);
        $monitor->recordPruneMetrics([
            'eligible_count' => 7,
            'deleted_count' => 5,
            'held_category_count' => 1,
            'categories' => [],
        ], true);
        $monitor->writerFailure(new AuditLoggingUnavailable('PASSWORD-SENTINEL'));
        AuditActivity::query()->auditOnly()->count();
        Log::spy();
        $this->travelTo('2026-08-01 00:30:00');

        $monitor->emitMonthlyMetrics();

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            $this->assertSame('Monthly audit metrics.', $message);
            $this->assertSame('2026-07', $context['period']);
            $this->assertSame(['clinical' => 1, 'operations' => 1], $context['rows_by_category']);
            $this->assertSame(['created' => 1, 'updated' => 1], $context['rows_by_action']);
            $this->assertIsInt($context['retained_bytes']);
            $this->assertSame(1, $context['prune_runs']);
            $this->assertSame(0, $context['prune_failures']);
            $this->assertSame(7, $context['prune_eligible_rows']);
            $this->assertSame(5, $context['prune_deleted_rows']);
            $this->assertSame(1, $context['write_failure_count']);
            $this->assertGreaterThanOrEqual(1, $context['slow_audit_query_count']);
            $this->assertStringNotContainsString('PRIVATE-DESCRIPTION-SENTINEL', json_encode($context, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('PASSWORD-SENTINEL', json_encode($context, JSON_THROW_ON_ERROR));

            return true;
        })->once();
    }

    public function test_retention_and_volume_monitoring_are_scheduled_with_cluster_locks(): void
    {
        Artisan::call('schedule:list');
        $events = collect(app(Schedule::class)->events());
        $prune = $events->first(fn ($event): bool => str_contains((string) ($event->command ?? ''), 'audit:prune --force'));
        $monitor = $events->first(fn ($event): bool => $event->description === 'audit:monitor-health');
        $monthly = $events->first(fn ($event): bool => $event->description === 'audit:emit-monthly-metrics');

        $this->assertNotNull($prune);
        $this->assertNotNull($monitor);
        $this->assertNotNull($monthly);
        $this->assertSame('0 0 * * *', $prune->expression);
        $this->assertTrue($prune->withoutOverlapping);
        $this->assertTrue($prune->onOneServer);
        $this->assertFalse(config('audit.features.retention'));
        $this->assertFalse($prune->filtersPass(app()));
        config(['audit.features.retention' => true]);
        $this->assertTrue($prune->filtersPass(app()));
        AuditSetting::query()->create([
            'key' => AuditSetting::RETENTION_ENABLED,
            'enabled' => false,
        ]);
        $this->assertFalse($prune->filtersPass(app()));
        $this->assertTrue($monitor->withoutOverlapping);
        $this->assertTrue($monitor->onOneServer);
        $this->assertSame('10 0 * * *', $monitor->expression);
        $this->assertTrue($monthly->withoutOverlapping);
        $this->assertTrue($monthly->onOneServer);
        $this->assertSame('30 0 1 * *', $monthly->expression);
    }

    public function test_no_http_route_can_mutate_audit_events(): void
    {
        $mutationRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'audit-log'))
            ->filter(fn ($route): bool => collect($route->methods())->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])->isNotEmpty());

        $this->assertCount(0, $mutationRoutes);
    }

    public function test_required_audit_unavailability_fails_and_rolls_back_the_mutation(): void
    {
        config(['activitylog.enabled' => false]);
        $before = User::query()->count();
        $controller = new class extends Controller
        {
            public function run(Closure $mutation): mixed
            {
                return $this->audited($mutation);
            }
        };

        try {
            $controller->run(fn () => User::factory()->create());
            $this->fail('Required audited mutation unexpectedly succeeded.');
        } catch (AuditLoggingUnavailable) {
            $this->assertSame($before, User::query()->count());
        }
    }

    public function test_noncritical_security_telemetry_failure_preserves_the_http_response(): void
    {
        Log::spy();
        $deduplicator = $this->createMock(SecurityAuditDeduplicator::class);
        $deduplicator->method('record')->willThrowException(new AuditLoggingUnavailable('TOKEN-SENTINEL'));
        $this->app->instance(SecurityAuditDeduplicator::class, $deduplicator);

        $this->getJson('/api/admin/audit-logs')->assertUnauthorized();

        Log::shouldHaveReceived('critical')->withArgs(fn (string $message, array $context): bool => $message === 'Audit writer failure detected.'
            && $context === ['exception_class' => AuditLoggingUnavailable::class]
        )->once();
    }

    public function test_one_hundred_thousand_event_queries_use_indexes_and_meet_the_staging_p95_budget(): void
    {
        AuditFixture::delete(AuditActivity::query());
        $table = (new AuditActivity)->getTable();
        $now = now();

        foreach (range(0, 99) as $batch) {
            $rows = [];
            foreach (range(1, 1000) as $offset) {
                $number = ($batch * 1000) + $offset;
                $rows[] = [
                    'log_name' => config('audit.log_name'),
                    'description' => 'Volume fixture',
                    'event' => AuditAction::Updated->value,
                    'category' => $number % 3 === 0 ? 'clinical' : 'operations',
                    'domain' => AuditDomain::System->value,
                    'severity' => AuditSeverity::Info->value,
                    'outcome' => AuditOutcome::Success->value,
                    'context_type' => $number % 500 === 0 ? 'patient' : null,
                    'context_id' => $number % 500 === 0 ? 42 : null,
                    'properties' => '{}',
                    'created_at' => $now->copy()->subSeconds($number),
                    'updated_at' => $now,
                ];
            }
            DB::table($table)->insert($rows);
        }

        DB::statement("ANALYZE TABLE `{$table}`");
        $queries = [
            ['activity_log_log_created_id_index', AuditActivity::query()
                ->auditOnly()->latest('created_at')->latest('id')->limit(50)],
            ['activity_log_log_created_id_index', AuditActivity::query()
                ->auditOnly()->fromDate(now()->subDay())->latest('created_at')->latest('id')->limit(50)],
            ['activity_log_context_created_id_index', AuditActivity::query()
                ->where('context_type', 'patient')->where('context_id', 42)
                ->latest('created_at')->latest('id')->limit(50)],
        ];

        $durations = [];
        foreach ($queries as [$expectedIndex, $query]) {
            $plan = DB::select('EXPLAIN '.$query->toSql(), $query->getBindings());
            $this->assertNotEmpty($plan);
            $this->assertSame($expectedIndex, $plan[0]->key ?? null);
            $this->assertNotSame('ALL', $plan[0]->type ?? null);

            foreach (range(1, 10) as $_) {
                $started = hrtime(true);
                (clone $query)->get();
                $durations[] = (hrtime(true) - $started) / 1_000_000;
            }
        }

        sort($durations);
        $p95 = $durations[(int) ceil(count($durations) * 0.95) - 1];
        $this->assertLessThanOrEqual(250.0, $p95, "Audit query p95 was {$p95} ms.");
    }

    public function test_security_runbook_documents_retention_integrity_monitoring_and_failure_policy(): void
    {
        $runbook = file_get_contents(base_path('../docs/security/security.md'));

        foreach ([
            'legal hold',
            'maintenance window',
            'OPTIMIZE TABLE',
            'never runs automatically',
            'append-only',
            'hash chain',
            'unauthorized audit-row mutation or deletion',
            'audit writer failure',
            'event-volume spike',
            'trailing 30-day daily average',
            'three times',
            '1 GiB',
            '70%',
            'monthly metrics',
            'slow audit-query count',
            'activity_log INSERT',
            'SQL or bindings',
            'partial prune progress',
            'fail and roll back',
            'non-critical security telemetry',
            '100,000',
            'p95',
            '250 ms',
        ] as $required) {
            $this->assertStringContainsStringIgnoringCase($required, $runbook);
        }

        $productionSource = collect([
            ...File::allFiles(app_path()),
            ...File::allFiles(base_path('bootstrap')),
        ])->map(fn ($file): string => $file->getContents())->implode("\n");
        $this->assertStringNotContainsString('OPTIMIZE TABLE', $productionSource);
    }

    private function activity(?string $category, mixed $createdAt): AuditActivity
    {
        return AuditActivity::create([
            'log_name' => config('audit.log_name'),
            'description' => 'Retention fixture',
            'event' => AuditAction::Updated->value,
            'category' => $category,
            'domain' => AuditDomain::System->value,
            'properties' => [],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function failNextAuditInsert(\Throwable $failure): void
    {
        $table = strtolower((new AuditActivity)->getTable());
        $armed = true;
        DB::connection(config('activitylog.database_connection'))
            ->beforeExecuting(function (string $query) use (&$armed, $failure, $table): void {
                $normalized = strtolower(str_replace(['`', '"', '[', ']'], '', $query));
                if ($armed && str_contains($normalized, 'insert') && str_contains($normalized, $table)) {
                    $armed = false;
                    throw $failure;
                }
            });
    }
}
