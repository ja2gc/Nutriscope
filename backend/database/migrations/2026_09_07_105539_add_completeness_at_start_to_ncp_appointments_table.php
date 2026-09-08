<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ncp_appointments', function (Blueprint $table) {
            $table->json('completeness_at_start')->nullable()->after('reason_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ncp_appointments', function (Blueprint $table) {
            $table->dropColumn('completeness_at_start');
        });
    }
};
