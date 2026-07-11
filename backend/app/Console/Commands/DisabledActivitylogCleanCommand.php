<?php

namespace App\Console\Commands;

use Spatie\Activitylog\CleanActivitylogCommand;

class DisabledActivitylogCleanCommand extends CleanActivitylogCommand
{
    public function handle(): int
    {
        $this->error('Generic activity cleanup is disabled. Use the hold-aware audit pruning command.');

        return self::FAILURE;
    }
}
