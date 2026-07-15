<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'patients'] as $table) {
            DB::table($table)
                ->select('id')
                ->whereNull('first_name')
                ->whereNull('last_name')
                ->orderBy('id')
                ->chunkById(500, function (Collection $rows) use ($table): void {
                    DB::table($table)
                        ->whereIn('id', $rows->pluck('id'))
                        ->whereNull('first_name')
                        ->whereNull('last_name')
                        ->update(['first_name' => DB::raw('name')]);
                });
        }
    }

    public function down(): void
    {
        // The following DDL rollback drops the additive columns.
    }
};
