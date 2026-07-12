<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $lastId = 0;

        while (true) {
            $ids = $this->query()
                ->where('id', '>', $lastId)
                ->whereNull('public_id')
                ->orderBy('id')
                ->limit(500)
                ->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }

            $lastId = (int) $ids->last();
            $this->query()
                ->whereBetween('id', [(int) $ids->first(), $lastId])
                ->whereNull('public_id')
                ->update(['public_id' => DB::raw('UUID()')]);
        }
    }

    public function down(): void
    {
        // Intentionally retained; the following DDL rollback drops the column.
    }

    private function query(): Builder
    {
        return DB::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'));
    }
};
