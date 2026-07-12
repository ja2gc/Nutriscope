<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Models\AuditActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use WeakMap;

class AuditHealthMonitor
{
    /** @var WeakMap<Throwable, true> */
    private WeakMap $reportedWriterFailures;

    /** @var array<class-string<Throwable>, int> */
    private array $localWriterAlertExpiries = [];

    public function __construct()
    {
        $this->reportedWriterFailures = new WeakMap;
    }

    public function unauthorizedRowMutation(string $operation): void
    {
        Log::critical('Unauthorized audit row mutation blocked.', [
            'operation' => $operation,
        ]);
    }

    public function writerFailure(Throwable $exception): void
    {
        if (isset($this->reportedWriterFailures[$exception])) {
            return;
        }
        $this->reportedWriterFailures[$exception] = true;
        $this->incrementMetric('write_failure_count');
        $dedupSeconds = max(1, (int) config('audit.monitoring.writer_alert_dedup_seconds', 60));
        $key = 'audit:monitor:writer-failure:'.hash('sha256', $exception::class);
        try {
            $shouldAlert = Cache::add($key, true, $dedupSeconds);
        } catch (Throwable) {
            $shouldAlert = $this->acquireLocalWriterAlert($exception::class, $dedupSeconds);
        }
        if (! $shouldAlert) {
            return;
        }

        Log::critical('Audit writer failure detected.', [
            'exception_class' => $exception::class,
        ]);
    }

    /** @param class-string<Throwable> $exceptionClass */
    private function acquireLocalWriterAlert(string $exceptionClass, int $dedupSeconds): bool
    {
        $now = now()->getTimestamp();
        foreach ($this->localWriterAlertExpiries as $class => $expiresAt) {
            if ($expiresAt <= $now) {
                unset($this->localWriterAlertExpiries[$class]);
            }
        }

        if (isset($this->localWriterAlertExpiries[$exceptionClass])) {
            return false;
        }

        $limit = max(1, (int) config('audit.monitoring.writer_alert_local_max_classes', 256));
        if (count($this->localWriterAlertExpiries) >= $limit) {
            asort($this->localWriterAlertExpiries);
            $oldest = array_key_first($this->localWriterAlertExpiries);
            if ($oldest !== null) {
                unset($this->localWriterAlertExpiries[$oldest]);
            }
        }

        $this->localWriterAlertExpiries[$exceptionClass] = $now + $dedupSeconds;

        return true;
    }

    public function pruneFailure(Throwable $exception): void
    {
        Log::critical('Audit retention failure detected.', [
            'exception_class' => $exception::class,
        ]);
    }

    /** @param array{eligible_count: int, deleted_count: int} $report */
    public function recordPruneMetrics(array $report, bool $successful): void
    {
        $this->incrementMetric('prune_runs');
        $this->incrementMetric('prune_eligible_rows', (int) $report['eligible_count']);
        $this->incrementMetric('prune_deleted_rows', (int) $report['deleted_count']);
        if (! $successful) {
            $this->incrementMetric('prune_failures');
        }
    }

    public function recordSlowAuditQuery(): void
    {
        $this->incrementMetric('slow_audit_query_count');
    }

    public function isAuditQuery(string $query): bool
    {
        $table = strtolower((new AuditActivity)->getTable());
        $normalized = strtolower(str_replace(['`', '"', '[', ']'], '', $query));

        return preg_match('/(?<![a-z0-9_])'.preg_quote($table, '/').'(?![a-z0-9_])/', $normalized) === 1;
    }

    public function isAuditInsertQuery(string $query): bool
    {
        $normalized = strtolower(str_replace(['`', '"', '[', ']'], '', $query));

        return $this->isAuditQuery($query) && preg_match('/\binsert\b/', $normalized) === 1;
    }

    public function inspectDaily(): void
    {
        $trailingDays = max(1, (int) config('audit.monitoring.volume.trailing_days', 30));
        $multiplier = max(1.0, (float) config('audit.monitoring.volume.spike_multiplier', 3));
        $observedEnd = CarbonImmutable::today();
        $observedStart = $observedEnd->subDay();
        $trailingStart = $observedStart->subDays($trailingDays);
        $observedCount = AuditActivity::query()
            ->auditOnly()
            ->where('created_at', '>=', $observedStart)
            ->where('created_at', '<', $observedEnd)
            ->count();
        $trailingCount = AuditActivity::query()
            ->auditOnly()
            ->where('created_at', '>=', $trailingStart)
            ->where('created_at', '<', $observedStart)
            ->count();
        $dailyAverage = $trailingCount / $trailingDays;

        if ($observedCount > $dailyAverage * $multiplier) {
            Log::warning('Daily audit event volume spike detected.', [
                'event_count' => $observedCount,
                'trailing_daily_average' => round($dailyAverage, 2),
                'spike_multiplier' => $multiplier,
                'trailing_days' => $trailingDays,
            ]);
        }

        $retainedBytes = $this->retainedBytes();
        $tableThreshold = max(1, (int) config('audit.monitoring.table_bytes_threshold', 1_073_741_824));
        if ($retainedBytes > $tableThreshold) {
            Log::warning('Audit table size threshold exceeded.', [
                'retained_bytes' => $retainedBytes,
                'threshold_bytes' => $tableThreshold,
            ]);
        }

        $usedPercent = config('audit.monitoring.database_disk_used_percent');
        $diskThreshold = (float) config('audit.monitoring.database_disk_percent_threshold', 70);
        if (is_numeric($usedPercent) && (float) $usedPercent > $diskThreshold) {
            Log::warning('Database disk usage threshold exceeded.', [
                'used_percent' => (float) $usedPercent,
                'threshold_percent' => $diskThreshold,
            ]);
        }
    }

    public function emitMonthlyMetrics(): void
    {
        $period = now()->subMonthNoOverflow()->format('Y-m');
        $categories = AuditActivity::query()->auditOnly()
            ->selectRaw('category, COUNT(*) AS aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');
        $actions = AuditActivity::query()->auditOnly()
            ->selectRaw('event, COUNT(*) AS aggregate')
            ->groupBy('event')
            ->pluck('aggregate', 'event');
        $categoryAllowList = array_column(AuditCategory::cases(), 'value');
        $actionAllowList = array_column(AuditAction::cases(), 'value');
        $rowsByCategory = [];
        foreach ($categories as $category => $count) {
            $key = is_string($category) && in_array($category, $categoryAllowList, true) ? $category : 'legacy';
            $rowsByCategory[$key] = ($rowsByCategory[$key] ?? 0) + (int) $count;
        }
        $rowsByAction = [];
        foreach ($actions as $action => $count) {
            $key = is_string($action) && in_array($action, $actionAllowList, true) ? $action : 'legacy';
            $rowsByAction[$key] = ($rowsByAction[$key] ?? 0) + (int) $count;
        }
        ksort($rowsByCategory);
        ksort($rowsByAction);

        Log::info('Monthly audit metrics.', [
            'period' => $period,
            'rows_by_category' => $rowsByCategory,
            'rows_by_action' => $rowsByAction,
            'retained_bytes' => $this->retainedBytes(),
            'prune_runs' => $this->metric('prune_runs', $period),
            'prune_failures' => $this->metric('prune_failures', $period),
            'prune_eligible_rows' => $this->metric('prune_eligible_rows', $period),
            'prune_deleted_rows' => $this->metric('prune_deleted_rows', $period),
            'write_failure_count' => $this->metric('write_failure_count', $period),
            'slow_audit_query_count' => $this->metric('slow_audit_query_count', $period),
        ]);
    }

    private function retainedBytes(): int
    {
        $connection = DB::connection(config('activitylog.database_connection'));
        if ($connection->getDriverName() !== 'mysql') {
            return 0;
        }

        $row = $connection->selectOne(
            'SELECT COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) AS retained_bytes
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$connection->getDatabaseName(), (new AuditActivity)->getTable()],
        );

        return max(0, (int) ($row->retained_bytes ?? 0));
    }

    private function incrementMetric(string $metric, int $amount = 1): void
    {
        try {
            $key = $this->metricKey($metric);
            Cache::add($key, 0, now()->addDays(62));
            Cache::increment($key, $amount);
        } catch (Throwable) {
        }
    }

    private function metric(string $metric, string $period): int
    {
        try {
            return max(0, (int) Cache::get($this->metricKey($metric, $period), 0));
        } catch (Throwable) {
            return 0;
        }
    }

    private function metricKey(string $metric, ?string $period = null): string
    {
        return 'audit:metrics:'.($period ?? now()->format('Y-m')).':'.$metric;
    }
}
