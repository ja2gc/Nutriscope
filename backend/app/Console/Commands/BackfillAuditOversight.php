<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditOversightBackfill;
use App\Services\Audit\AuditRetentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAuditOversight extends Command
{
    protected $signature = 'audit:backfill-oversight {--chunk=500 : Rows processed per batch (1-5000)}';

    protected $description = 'Backfill deterministic audit modules and encrypted patient-name snapshots.';

    public function handle(AuditOversightBackfill $backfill, AuditRetentionService $retention): int
    {
        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5000],
        ]);
        if ($chunkSize === false) {
            $this->components->error('The --chunk option must be an integer from 1 to 5000.');

            return self::INVALID;
        }

        $connection = DB::connection(config('activitylog.database_connection'));
        $stats = $retention->withAuthorizedBackfill(
            $connection,
            fn (): array => $backfill->run($connection, $chunkSize),
        );

        $this->components->info(sprintf(
            'Audit oversight backfill complete: %d scanned, %d rows updated (%d modules, %d domains, %d patient snapshots).',
            $stats['scanned'],
            $stats['updated'],
            $stats['modules'],
            $stats['domains'],
            $stats['patient_snapshots'],
        ));

        return self::SUCCESS;
    }
}
