<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_prep_logs', function (Blueprint $table) {
            $table->timestamp('served_locked_at')->nullable()->after('served_population');
            $table->foreignId('served_locked_by')->nullable()->constrained('users')->nullOnDelete()->after('served_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('meal_prep_logs', function (Blueprint $table) {
            $table->dropForeignIdFor('served_locked_by');
            $table->dropColumn(['served_locked_at', 'served_locked_by']);
        });
    }
};
