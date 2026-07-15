<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table): void {
                $table->string('module', 64)->nullable()->after('domain');
                $table->text('patient_display_name_snapshot')->nullable()->after('audit_owner_id');
                $table->index(
                    ['module', 'created_at', 'id'],
                    'activity_log_module_created_id_index',
                );
            });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table): void {
                $table->dropIndex('activity_log_module_created_id_index');
                $table->dropColumn(['module', 'patient_display_name_snapshot']);
            });
    }
};
