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
                $table->string('category', 32)->nullable()->after('event');
                $table->string('domain', 32)->nullable()->after('category');
                $table->string('severity', 16)->nullable()->after('domain');
                $table->string('outcome', 16)->nullable()->after('severity');
                $table->string('context_type')->nullable()->after('causer_id');
                $table->unsignedBigInteger('context_id')->nullable()->after('context_type');

                $table->index(
                    ['log_name', 'created_at', 'id'],
                    'activity_log_log_created_id_index',
                );
                $table->index(
                    ['category', 'created_at', 'id'],
                    'activity_log_category_created_id_index',
                );
                $table->index(
                    ['event', 'created_at', 'id'],
                    'activity_log_event_created_id_index',
                );
                $table->index(
                    ['context_type', 'context_id', 'created_at', 'id'],
                    'activity_log_context_created_id_index',
                );
            });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table) {
                $table->dropIndex('activity_log_log_created_id_index');
                $table->dropIndex('activity_log_category_created_id_index');
                $table->dropIndex('activity_log_event_created_id_index');
                $table->dropIndex('activity_log_context_created_id_index');
                $table->dropColumn([
                    'category',
                    'domain',
                    'severity',
                    'outcome',
                    'context_type',
                    'context_id',
                ]);
            });
    }
};
