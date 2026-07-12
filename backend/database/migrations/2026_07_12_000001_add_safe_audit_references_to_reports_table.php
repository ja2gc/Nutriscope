<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->unsignedBigInteger('audit_patient_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('audit_ncp_record_id')->nullable()->after('audit_patient_id');
            $table->unsignedBigInteger('audit_owner_id')->nullable()->after('audit_ncp_record_id');
            $table->index(['audit_owner_id', 'audit_ncp_record_id'], 'reports_audit_owner_ncp_index');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropIndex('reports_audit_owner_ncp_index');
            $table->dropColumn(['audit_patient_id', 'audit_ncp_record_id', 'audit_owner_id']);
        });
    }
};
