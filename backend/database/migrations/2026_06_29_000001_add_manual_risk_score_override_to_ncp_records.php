<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ncp_records', function (Blueprint $table) {
            $table->boolean('risk_score_manual_override')->default(false)->after('risk_score');
            $table->json('risk_score_manual_factors')->nullable()->after('risk_score_manual_override');
        });
    }

    public function down(): void
    {
        Schema::table('ncp_records', function (Blueprint $table) {
            $table->dropColumn(['risk_score_manual_override', 'risk_score_manual_factors']);
        });
    }
};
