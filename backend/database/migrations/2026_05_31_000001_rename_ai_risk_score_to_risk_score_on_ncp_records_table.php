<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ncp_records', 'ai_risk_score') || Schema::hasColumn('ncp_records', 'risk_score')) {
            return;
        }

        Schema::table('ncp_records', function (Blueprint $table) {
            $table->renameColumn('ai_risk_score', 'risk_score');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ncp_records', 'risk_score') || Schema::hasColumn('ncp_records', 'ai_risk_score')) {
            return;
        }

        Schema::table('ncp_records', function (Blueprint $table) {
            $table->renameColumn('risk_score', 'ai_risk_score');
        });
    }
};
