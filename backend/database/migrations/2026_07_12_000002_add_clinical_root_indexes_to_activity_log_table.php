<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table): void {
            $table->unsignedBigInteger('root_patient_id')->nullable()->after('context_id');
            $table->unsignedBigInteger('ncp_record_id')->nullable()->after('root_patient_id');
            $table->unsignedBigInteger('audit_owner_id')->nullable()->after('ncp_record_id');
            $table->index(['log_name', 'root_patient_id', 'id'], 'activity_log_root_patient_id_cursor_index');
            $table->index(['log_name', 'ncp_record_id', 'id'], 'activity_log_ncp_record_id_cursor_index');
            $table->index(['log_name', 'audit_owner_id', 'root_patient_id', 'id'], 'activity_log_owner_patient_cursor_index');
            $table->index(['log_name', 'context_type', 'context_id', 'id'], 'activity_log_context_id_cursor_index');
        });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table): void {
            $table->dropIndex('activity_log_root_patient_id_cursor_index');
            $table->dropIndex('activity_log_ncp_record_id_cursor_index');
            $table->dropIndex('activity_log_owner_patient_cursor_index');
            $table->dropIndex('activity_log_context_id_cursor_index');
            $table->dropColumn(['root_patient_id', 'ncp_record_id', 'audit_owner_id']);
        });
    }
};
