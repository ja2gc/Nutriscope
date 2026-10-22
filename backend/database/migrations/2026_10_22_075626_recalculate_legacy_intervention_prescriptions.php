<?php

use App\Models\Intervention;
use App\Models\Report;
use App\Services\InterventionCalculationContextService;
use App\Services\MealPlanService;
use App\Support\InterventionGoalCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Intervention::query()->whereNull('calculation_snapshot')->whereNotNull('goal_type')->where('goal_type', '!=', '')
            ->with(['ncpRecord.patient', 'ncpRecord.assessment', 'sourceMonitoring', 'mealPlans.days.items'])
            ->chunkById(50, function ($plans): void {
                DB::transaction(function () use ($plans): void {
                    $plans = Intervention::query()->whereKey($plans->modelKeys())->whereNull('calculation_snapshot')
                        ->with(['ncpRecord.patient', 'ncpRecord.assessment', 'sourceMonitoring', 'mealPlans.days.items'])
                        ->lockForUpdate()->get();
                    if ($plans->isEmpty()) {
                        return;
                    }
                    $ids = $plans->modelKeys();
                    $ncpIds = $plans->pluck('ncp_record_id')->unique()->values()->all();
                    $menuIds = $plans->flatMap->mealPlans->pluck('id')->all();
                    $dayIds = DB::table('meal_plan_days')->whereIn('meal_plan_id', $menuIds)->pluck('id');
                    $originals = [
                        'interventions' => DB::table('interventions')->whereIn('id', $ids)->get()->all(),
                        'meal_plans' => DB::table('meal_plans')->whereIn('id', $menuIds)->get()->all(),
                        'meal_plan_days' => DB::table('meal_plan_days')->whereIn('id', $dayIds)->get()->all(),
                        'meal_plan_items' => DB::table('meal_plan_items')->whereIn('meal_plan_day_id', $dayIds)->get()->all(),
                    ];
                    $json = json_encode($originals, JSON_THROW_ON_ERROR);
                    $backup = 'maintenance/intervention-prescriptions-'.hash('sha256', $json).'.enc';
                    $disk = Storage::disk(config('filesystems.private_uploads', 'private_uploads'));
                    if (! $disk->exists($backup) && ! $disk->put($backup, Crypt::encryptString($json))) {
                        throw new RuntimeException('Intervention conversion backup failed; no records changed.');
                    }
                    if (Crypt::decryptString($disk->get($backup)) !== $json) {
                        throw new RuntimeException('Intervention conversion backup verification failed; no records changed.');
                    }
                    foreach ($plans as $plan) {
                        $attributes = $this->selection($plan);
                        $restricted = ($plan->nutrient_operators['fluid_ml'] ?? null) === 'max' || $plan->fluid_mode === 'restricted';
                        $attributes += ['energy_adjustment_mode' => 'none', 'energy_adjustment_kcal' => 0,
                            'fluid_mode' => $restricted ? 'restricted' : 'estimated', 'fluid_ml' => $plan->fluid_ml,
                            'calculation_options' => $plan->calculation_options ?? [],
                        ];
                        if ($plan->goal_type === 'malnutrition' || ($plan->goal_type === 'weight_gain' && $plan->disease_stage === 'severe')) {
                            $attributes['calculation_options']['malnutrition_note'] = true;
                        }
                        try {
                            $result = app(InterventionCalculationContextService::class)->calculate($plan->ncpRecord, $attributes, $plan);
                        } catch (ValidationException $exception) {
                            // Missing clinical evidence must not be replaced by an inferred diagnosis or dose.
                            $attributes['goal_type'] = 'custom';
                            $attributes['disease_stage'] = null;
                            $attributes['energy_adjustment_mode'] = 'none';
                            $attributes['energy_adjustment_kcal'] = 0;
                            $result = app(InterventionCalculationContextService::class)->calculate($plan->ncpRecord, $attributes, $plan);
                        }
                        $snapshot = $result['calculation_snapshot'];
                        foreach ($snapshot['calculated'] as $field => $value) {
                            $attributes[$field] = $value ?? $plan->getAttribute($field);
                            if ($value === null && $attributes[$field] !== null) {
                                $snapshot['manual_overrides'][$field] = (float) $attributes[$field];
                            }
                        }
                        $attributes['calculation_snapshot'] = $snapshot;
                        $attributes['nutrient_operators'] = $plan->nutrient_operators ?? [];
                        if ($restricted) {
                            $attributes['nutrient_operators']['fluid_ml'] = 'max';
                        }
                        // Explicit one-time conversion includes historical rows, preserving identity and dates.
                        $plan->fill($attributes);
                        DB::table('interventions')->where('id', $plan->id)->update(
                            array_intersect_key($plan->getAttributes(), $attributes),
                        );
                        foreach ($plan->mealPlans as $menu) {
                            app(MealPlanService::class)->scaleToPrescription($menu, $plan);
                        }
                    }
                    $ncpUuids = $plans->pluck('ncpRecord.uuid')->unique()->all();
                    $planIds = [...$plans->modelKeys(), ...$plans->pluck('uuid')->all()];
                    $menuIds = [...$plans->flatMap->mealPlans->pluck('id')->all(), ...$plans->flatMap->mealPlans->pluck('uuid')->all()];
                    Report::query()->whereIn('type', ['patient_menu_plan', 'ncp_summary'])
                        ->where(function ($query) use ($ncpIds, $ncpUuids, $planIds, $menuIds): void {
                            $query->whereIn('audit_ncp_record_id', $ncpIds);
                            foreach (['ncp_record_id' => [...$ncpIds, ...$ncpUuids], 'intervention_plan_id' => $planIds, 'meal_plan_id' => $menuIds] as $key => $values) {
                                foreach ($values as $value) {
                                    $query->orWhere('parameters->'.$key, (string) $value);
                                }
                            }
                        })
                        ->where('status', '!=', 'archived')->update(['cache_expires_at' => now(), 'expires_at' => now()]);
                });
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clinical data conversion is forward-only; encrypted originals are retained privately for recovery.
    }

    private function selection(Intervention $plan): array
    {
        $goal = $plan->goal_type;
        $stage = $plan->disease_stage;
        $diagnosis = strtolower((string) $plan->ncpRecord->patient?->medical_diagnosis);
        $adjustment = [];
        if (! InterventionGoalCatalog::stageIsValid($goal, $stage)) {
            [$goal, $stage] = match ($goal) {
                'diabetic_control' => ['diabetic_control', preg_match('/type\s*(?:1|i)(?:\b|dm)|\bt1dm\b/', $diagnosis) ? 'type_1'
                    : (preg_match('/type\s*(?:2|ii)(?:\b|dm)|\bt2dm\b/', $diagnosis) || $stage === 'stage_2' ? 'type_2' : null)],
                'renal_diet' => ['renal_diet', match ($stage) {
                    'stage_1', 'stage_2' => 'ckd_1_2', 'stage_3', 'stage_4', 'stage_5_predialysis' => 'ckd_3_5_non_dialysis', default => null,
                }],
                'cardiac_diet' => ['cardiac_diet', match (true) {
                    str_contains($diagnosis, 'heart failure') => 'heart_failure', str_contains($diagnosis, 'hypertension') => 'hypertension',
                    str_contains($diagnosis, 'dyslipid') || str_contains($diagnosis, 'hyperlipid') => 'dyslipidemia', default => null,
                }],
                'high_protein' => ['high_protein', match (true) {
                    preg_match('/wound|ulcer/', $diagnosis) === 1 => 'wound_healing',
                    preg_match('/post.?operat|post.?surg/', $diagnosis) === 1 => 'postoperative_recovery', default => null,
                }],
                'weight_loss', 'weight_gain', 'malnutrition' => ['general_diet', null],
                default => ['custom', null],
            };
            if (! InterventionGoalCatalog::stageIsValid($goal, $stage)) {
                [$goal, $stage] = ['custom', null];
            }
        }
        if ($plan->goal_type === 'weight_loss' || ($plan->goal_type === 'diabetic_control' && $plan->disease_stage === 'stage_2')) {
            $adjustment = ['energy_adjustment_mode' => 'deficit', 'energy_adjustment_kcal' => [
                'overweight' => 375, 'class_1' => 500, 'class_2' => 625, 'class_3' => 875,
            ][$plan->disease_stage] ?? 500];
        } elseif ($plan->goal_type === 'weight_gain' && $plan->disease_stage !== 'severe') {
            $adjustment = ['energy_adjustment_mode' => 'surplus', 'energy_adjustment_kcal' => $plan->disease_stage === 'mild' ? 400 : 625];
        }

        return ['goal_type' => $goal, 'disease_stage' => $stage, ...$adjustment];
    }
};
