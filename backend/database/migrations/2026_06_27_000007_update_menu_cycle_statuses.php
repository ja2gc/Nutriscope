<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->string('status')->default('upcoming')->change();
        });

        // Map: archived → completed, draft → upcoming, active stays active
        DB::table('menu_cycles')
            ->where('status', 'archived')
            ->update(['status' => 'completed']);

        DB::table('menu_cycles')
            ->where('status', 'draft')
            ->update(['status' => 'upcoming']);
    }

    public function down(): void
    {
        // Reverse mapping: completed → archived, upcoming → draft
        DB::table('menu_cycles')
            ->where('status', 'completed')
            ->update(['status' => 'archived']);

        DB::table('menu_cycles')
            ->where('status', 'upcoming')
            ->update(['status' => 'draft']);
    }
};
