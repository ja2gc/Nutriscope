<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table) {
                $table->uuid('subject_public_id')->nullable()->after('subject_id');
                $table->uuid('context_public_id')->nullable()->after('context_id');
                $table->index(
                    ['subject_public_id', 'created_at', 'id'],
                    'activity_log_subject_public_created_id_index',
                );
                $table->index(
                    ['context_public_id', 'created_at', 'id'],
                    'activity_log_context_public_created_id_index',
                );
            });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table) {
                $table->dropIndex('activity_log_subject_public_created_id_index');
                $table->dropIndex('activity_log_context_public_created_id_index');
                $table->dropColumn(['subject_public_id', 'context_public_id']);
            });
    }
};
