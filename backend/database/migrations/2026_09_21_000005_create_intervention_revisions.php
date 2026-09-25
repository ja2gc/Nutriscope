<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervention_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('intervention_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitoring_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('effective_at');
            $table->string('reason');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 40);
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['intervention_id', 'version']);
        });

        $decode = static function (mixed $value): mixed {
            if ($value === null || is_array($value)) {
                return $value;
            }

            return json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);
        };

        DB::table('interventions')
            ->join('ncp_records', 'ncp_records.id', '=', 'interventions.ncp_record_id')
            ->select('interventions.*', 'ncp_records.rnd_user_id as actor_user_id')
            ->orderBy('interventions.id')
            ->chunkById(100, function ($interventions) use ($decode): void {
                foreach ($interventions as $intervention) {
                    $snapshot = [
                        'goal_type' => $intervention->goal_type,
                        'disease_stage' => $intervention->disease_stage,
                        'displayed_nutrients' => $decode($intervention->displayed_nutrients),
                        'energy_kcal' => $intervention->energy_kcal,
                        'protein_g' => $intervention->protein_g,
                        'carbs_g' => $intervention->carbs_g,
                        'fat_g' => $intervention->fat_g,
                        'fluid_ml' => $intervention->fluid_ml,
                        'micronutrient_limits' => $decode($intervention->micronutrient_limits),
                        'education_notes' => $intervention->education_notes,
                        'counseling_goals' => $intervention->counseling_goals,
                        'barriers' => $intervention->barriers,
                        'strategies' => $intervention->strategies,
                        'session_type' => $intervention->session_type,
                        'next_followup_date' => $intervention->next_followup_date,
                    ];
                    $timestamp = $intervention->created_at ?? now();

                    DB::table('intervention_revisions')->insert([
                        'uuid' => (string) Str::uuid(),
                        'intervention_id' => $intervention->id,
                        'monitoring_id' => null,
                        'version' => 1,
                        'effective_at' => $timestamp,
                        'reason' => 'Legacy intervention baseline',
                        'actor_user_id' => $intervention->actor_user_id,
                        'source' => 'legacy_baseline',
                        'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            }, 'interventions.id', 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_revisions');
    }
};
