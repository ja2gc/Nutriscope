<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedSmallInteger('weight_change_period_value')->nullable()->after('weight_loss_period');
            $table->string('weight_change_period_unit', 10)->nullable()->after('weight_change_period_value');
            $table->string('primary_diagnosis_category', 40)->nullable()->index()->after('weight_change_period_unit');
            $table->string('primary_diagnosis_other', 160)->nullable()->after('primary_diagnosis_category');
        });

        DB::table('assessments')
            ->select(['id', 'weight_loss_period', 'pregnancy_lactation_status'])
            ->orderBy('id')
            ->chunkById(100, function ($assessments): void {
                foreach ($assessments as $assessment) {
                    $updates = [];
                    if (is_string($assessment->weight_loss_period)
                        && preg_match('/^\s*(\d+)\s+(week|weeks|month|months)\s*$/i', $assessment->weight_loss_period, $matches) === 1
                        && (int) $matches[1] >= 1
                        && (int) $matches[1] <= 65535) {
                        $updates['weight_change_period_value'] = (int) $matches[1];
                        $updates['weight_change_period_unit'] = str_starts_with(strtolower($matches[2]), 'week')
                            ? 'weeks'
                            : 'months';
                    }

                    if ($assessment->pregnancy_lactation_status === 'pregnant') {
                        $updates['pregnancy_lactation_status'] = 'pregnant_unspecified';
                    }

                    if ($updates !== []) {
                        DB::table('assessments')->where('id', $assessment->id)->update($updates);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex(['primary_diagnosis_category']);
            $table->dropColumn([
                'weight_change_period_value',
                'weight_change_period_unit',
                'primary_diagnosis_category',
                'primary_diagnosis_other',
            ]);
        });
    }
};
