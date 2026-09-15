<?php

namespace App\Console\Commands;

use App\Services\Reports\StoreMonthlyDemographicCensuses as CensusStore;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class StoreMonthlyDemographicCensuses extends Command
{
    protected $signature = 'reports:demographic-census-catch-up';

    protected $description = 'Store each missing completed monthly demographic census.';

    public function handle(CensusStore $store): int
    {
        $result = $store->handle(Carbon::now(config('nutriscope-reports.timezone')));
        $this->info("Monthly demographic censuses stored: {$result['stored']}; already frozen: {$result['skipped']}.");

        return self::SUCCESS;
    }
}
