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
        Schema::table('interventions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->foreignId('source_monitoring_id')
                ->nullable()
                ->after('ncp_record_id')
                ->constrained('monitorings')
                ->nullOnDelete();
            $table->unique('uuid', 'interventions_uuid_unique');
            $table->index(
                ['ncp_record_id', 'created_at', 'id'],
                'interventions_ncp_created_id_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropIndex('interventions_ncp_created_id_index');
            $table->dropUnique('interventions_uuid_unique');
            $table->dropConstrainedForeignId('source_monitoring_id');
            $table->dropColumn('uuid');
        });
    }
};
