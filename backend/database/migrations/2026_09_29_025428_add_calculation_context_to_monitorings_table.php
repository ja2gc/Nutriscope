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
        Schema::table('monitorings', function (Blueprint $table) {
            $table->date('observed_at')->nullable()->after('intervention_revision_id');
            $table->string('visit_type', 40)->nullable()->after('observed_at');
            $table->decimal('height', 6, 2)->nullable()->after('weight');
            $table->boolean('edema_present')->nullable()->after('height');
            $table->decimal('dry_weight_kg', 6, 2)->nullable()->after('edema_present');
            $table->string('physical_activity_level', 40)->nullable()->after('dry_weight_kg');
            $table->string('pregnancy_lactation_status', 40)->nullable()->after('physical_activity_level');
            $table->json('allergies')->nullable()->after('pregnancy_lactation_status');
            $table->text('dietary_restrictions')->nullable()->after('allergies');
            $table->json('food_dislikes')->nullable()->after('dietary_restrictions');
            $table->index(['ncp_record_id', 'observed_at', 'id'], 'monitorings_ncp_observed_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitorings', function (Blueprint $table) {
            $table->dropIndex('monitorings_ncp_observed_id_index');
            $table->dropColumn([
                'observed_at',
                'visit_type',
                'height',
                'edema_present',
                'dry_weight_kg',
                'physical_activity_level',
                'pregnancy_lactation_status',
                'allergies',
                'dietary_restrictions',
                'food_dislikes',
            ]);
        });
    }
};
