<?php

namespace App\Services\Audit;

use App\Exceptions\AuditPruneFailed;
use App\Models\AuditActivity;
use App\Models\AuditRevision;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

class AuditRetentionService
{
    /** @var array<int, int> */
    private array $authorizedConnections = [];

    /** @var array<int, true> */
    private array $registeredConnections = [];

    public function __construct(private readonly AuditHealthMonitor $monitor) {}

    public function registerMutationBoundary(Connection $connection): void
    {
        $connectionId = spl_object_id($connection);
        if (isset($this->registeredConnections[$connectionId])) {
            return;
        }

        $this->registeredConnections[$connectionId] = true;
        $connection->beforeExecuting(function (string $query) use ($connectionId): void {
            $operation = $this->auditMutationOperation($query);
            if ($operation === null || ($this->authorizedConnections[$connectionId] ?? 0) > 0) {
                return;
            }

            try {
                $this->monitor->unauthorizedRowMutation($operation);
            } catch (Throwable) {
            }

            throw new RuntimeException('Audit events may only be mutated by the retention service.');
        });
    }

    public function withAuthorizedMigration(Connection $connection, Closure $mutation): mixed
    {
        $command = $_SERVER['argv'][1] ?? null;
        $isMigrationRuntime = app()->runningInConsole()
            && (app()->runningUnitTests() || (is_string($command) && Str::startsWith($command, 'migrate')));
        if (! $isMigrationRuntime) {
            throw new LogicException('Audit migration mutation scope is unavailable outside migrations.');
        }

        return $this->withAuthorizedDeletion($connection, $mutation);
    }

    /**
     * @return array{eligible_count: int, deleted_count: int, held_category_count: int, categories: array<string, array{eligible: int, deleted: int, held: bool}>}
     */
    public function prune(bool $force): array
    {
        $policies = $this->validatedPolicies();
        $report = [
            'eligible_count' => 0,
            'deleted_count' => 0,
            'held_category_count' => 0,
            'categories' => [],
        ];
        $queries = [];

        try {
            foreach ($policies as $category => $policy) {
                $held = $policy['legal_hold'];
                if ($held) {
                    $report['held_category_count']++;
                    $report['categories'][$category] = ['eligible' => 0, 'deleted' => 0, 'held' => true];

                    continue;
                }

                $query = $this->expiredQuery($category, $policy['days']);
                $eligible = (clone $query)->count();
                $report['eligible_count'] += $eligible;
                $report['categories'][$category] = ['eligible' => $eligible, 'deleted' => 0, 'held' => false];
                $queries[$category] = $query;
            }

            if ($force) {
                foreach ($queries as $category => $query) {
                    $this->deleteInChunks($query, function (int $deleted) use (&$report, $category): void {
                        $report['deleted_count'] += $deleted;
                        $report['categories'][$category]['deleted'] += $deleted;
                    });
                }
            }
        } catch (Throwable) {
            throw new AuditPruneFailed([
                'eligible_count' => $report['eligible_count'],
                'deleted_count' => $report['deleted_count'],
                'held_category_count' => $report['held_category_count'],
            ]);
        }

        return $report;
    }

    /** @return array<string, array{days: int, legal_hold: bool}> */
    private function validatedPolicies(): array
    {
        $policies = config('audit.retention');
        $invalid = ! is_array($policies);
        foreach (['security', 'clinical', 'operations', 'legacy'] as $requiredCategory) {
            $invalid = $invalid || ! is_array($policies) || ! array_key_exists($requiredCategory, $policies);
        }

        if (! $invalid) {
            foreach ($policies as $policy) {
                if (! is_array($policy)
                    || ! array_key_exists('days', $policy)
                    || ! is_int($policy['days'])
                    || $policy['days'] <= 0
                    || ! array_key_exists('legal_hold', $policy)
                    || ! is_bool($policy['legal_hold'])) {
                    $invalid = true;
                    break;
                }
            }
        }

        if ($invalid) {
            throw new AuditPruneFailed([
                'eligible_count' => 0,
                'deleted_count' => 0,
                'held_category_count' => 0,
            ]);
        }

        /** @var array<string, array{days: int, legal_hold: bool}> $policies */
        return $policies;
    }

    private function expiredQuery(string $category, int $days): Builder
    {
        $query = AuditActivity::query()
            ->auditOnly()
            ->where('created_at', '<', CarbonImmutable::now()->subDays(max(0, $days)));

        return $category === 'legacy'
            ? $query->whereNull('category')
            : $query->where('category', $category);
    }

    private function deleteInChunks(Builder $query, Closure $recordDeleted): void
    {
        $chunkSize = max(1, (int) config('audit.pruning.chunk_size', 1000));
        $table = (new AuditActivity)->getTable();

        do {
            $ids = (clone $query)
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');
            $connection = DB::connection(config('activitylog.database_connection'));
            $chunkDeleted = $ids->isEmpty() ? 0 : $this->withAuthorizedDeletion(
                $connection,
                fn (): int => $connection->table($table)->whereIn('id', $ids)->delete(),
            );
            if ($chunkDeleted > 0) {
                $recordDeleted($chunkDeleted);
            }
        } while ($chunkDeleted > 0);
    }

    private function withAuthorizedDeletion(Connection $connection, Closure $deletion): mixed
    {
        $connectionId = spl_object_id($connection);
        $this->authorizedConnections[$connectionId] = ($this->authorizedConnections[$connectionId] ?? 0) + 1;

        try {
            return $deletion();
        } finally {
            $this->authorizedConnections[$connectionId]--;
            if ($this->authorizedConnections[$connectionId] === 0) {
                unset($this->authorizedConnections[$connectionId]);
            }
        }
    }

    private function auditMutationOperation(string $query): ?string
    {
        $normalized = strtolower(str_replace(['`', '"', '[', ']'], '', $query));
        $isAuditTable = collect([
            (new AuditActivity)->getTable(),
            (new AuditRevision)->getTable(),
        ])->contains(fn (string $table): bool => preg_match(
            '/(?<![a-z0-9_])'.preg_quote(strtolower($table), '/').'(?![a-z0-9_])/',
            $normalized,
        ) === 1);
        if (! $isAuditTable) {
            return null;
        }

        if (preg_match('/^\s*insert\b.*\bon\s+duplicate\s+key\s+update\b/s', $normalized) === 1) {
            return 'update';
        }

        preg_match('/^\s*(replace|truncate|delete|update)\b/', $normalized, $match);

        return $match[1] ?? null;
    }
}
