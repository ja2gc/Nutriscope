<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('fs_items')
            ->where('kind', 'ready_to_eat')
            ->update(['kind' => 'ingredient']);
    }

    public function down(): void
    {
        // No-op: the Food Service workflow no longer uses ready_to_eat as a kind.
    }
};
