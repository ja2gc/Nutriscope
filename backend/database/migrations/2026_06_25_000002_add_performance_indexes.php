<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // patients: status filtered on list view; name used in search/order
        Schema::table('patients', function (Blueprint $table) {
            $table->index('status', 'patients_status_idx');
            $table->index('name', 'patients_name_idx');
        });

        // ncp_records: status filtered frequently in patient/dashboard queries
        Schema::table('ncp_records', function (Blueprint $table) {
            $table->index('status', 'ncp_records_status_idx');
        });

        // food_items: name ordered/searched, category filtered
        Schema::table('food_items', function (Blueprint $table) {
            $table->index('name', 'food_items_name_idx');
            $table->index('category', 'food_items_category_idx');
        });

        // notifications: composite covers the common pattern
        // WHERE user_id = ? AND read = ? ORDER BY created_at DESC
        // InnoDB already indexes user_id via FK, but the composite avoids a filesort.
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read', 'created_at'], 'notifications_user_read_date_idx');
        });

        // monitorings: composite covers ORDER BY created_at within a given ncp_record
        Schema::table('monitorings', function (Blueprint $table) {
            $table->index(['ncp_record_id', 'created_at'], 'monitorings_record_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_status_idx');
            $table->dropIndex('patients_name_idx');
        });

        Schema::table('ncp_records', function (Blueprint $table) {
            $table->dropIndex('ncp_records_status_idx');
        });

        Schema::table('food_items', function (Blueprint $table) {
            $table->dropIndex('food_items_name_idx');
            $table->dropIndex('food_items_category_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_read_date_idx');
        });

        Schema::table('monitorings', function (Blueprint $table) {
            $table->dropIndex('monitorings_record_date_idx');
        });
    }
};
