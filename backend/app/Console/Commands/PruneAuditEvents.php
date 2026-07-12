<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Exceptions\AuditPruneFailed;
use App\Services\Audit\AuditHealthMonitor;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\AuditRetentionService;
use Illuminate\Console\Command;
use Throwable;

class PruneAuditEvents extends Command
{
    protected $signature = 'audit:prune {--force : Permanently delete eligible audit events}';

    protected $description = 'Prune expired audit events according to category retention policies';

    public function handle(
        AuditRetentionService $retention,
        AuditLogger $audit,
        AuditHealthMonitor $monitor,
    ): int {
        $force = (bool) $this->option('force');
        try {
            $report = $retention->prune($force);
        } catch (Throwable $exception) {
            $progress = $exception instanceof AuditPruneFailed ? $exception->progress() : [
                'eligible_count' => 0,
                'deleted_count' => 0,
                'held_category_count' => 0,
            ];
            $monitor->recordPruneMetrics($progress, false);
            $monitor->pruneFailure($exception);
            $this->recordFailure($audit, $monitor, $progress);
            $this->error('Audit pruning failed. Review the protected application logs.');

            return self::FAILURE;
        }

        foreach ($report['categories'] as $category => $counts) {
            $this->line($counts['held']
                ? "{$category}: legal hold active"
                : "{$category}: {$counts['eligible']} eligible, {$counts['deleted']} deleted");
        }

        $mode = $force ? 'Prune' : 'Dry run';
        $held = $report['held_category_count'];
        $this->info(sprintf(
            '%s complete: %d eligible, %d deleted, %d held %s.',
            $mode,
            $report['eligible_count'],
            $report['deleted_count'],
            $held,
            $held === 1 ? 'category' : 'categories',
        ));

        if ($force) {
            $monitor->recordPruneMetrics($report, true);
            $audit->record(
                AuditAction::Completed,
                AuditCategory::Operations,
                AuditDomain::System,
                outcome: AuditOutcome::Success,
                severity: AuditSeverity::Info,
                details: [
                    'deleted_count' => $report['deleted_count'],
                    'eligible_count' => $report['eligible_count'],
                    'held_category_count' => $report['held_category_count'],
                ],
                systemActor: 'Audit retention',
                includeRequestMetadata: false,
            );
        }

        return self::SUCCESS;
    }

    /** @param array{eligible_count: int, deleted_count: int, held_category_count: int} $progress */
    private function recordFailure(AuditLogger $audit, AuditHealthMonitor $monitor, array $progress): void
    {
        try {
            $audit->record(
                AuditAction::Completed,
                AuditCategory::Operations,
                AuditDomain::System,
                outcome: AuditOutcome::Failure,
                severity: AuditSeverity::Critical,
                details: [
                    'deleted_count' => $progress['deleted_count'],
                    'eligible_count' => $progress['eligible_count'],
                    'held_category_count' => $progress['held_category_count'],
                ],
                systemActor: 'Audit retention',
                includeRequestMetadata: false,
            );
        } catch (Throwable $exception) {
            $monitor->writerFailure($exception);
        }
    }
}
