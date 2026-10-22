<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class InterventionCalculationContextService
{
    /** @return array<string, mixed> */
    public function for(NcpRecord $ncpRecord): array
    {
        return $this->build($ncpRecord);
    }

    /** @return array<string, mixed> */
    public function prefillForMonitoring(NcpRecord $ncpRecord): array
    {
        return $this->build($ncpRecord);
    }

    /** @return array<string, mixed> */
    private function build(NcpRecord $ncpRecord, ?Monitoring $monitoring = null, bool $useLatest = true): array
    {
        $ncpRecord->loadMissing(['assessment', 'patient']);
        $assessment = $ncpRecord->assessment;
        $monitoring = $useLatest ? $ncpRecord->monitorings()
            ->orderByDesc('observed_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first() : $monitoring;
        $fields = [
            'weight',
            'height',
            'edema_present',
            'dry_weight_kg',
            'physical_activity_level',
            'pregnancy_lactation_status',
            'dietary_restrictions',
            'allergies',
            'food_dislikes',
        ];
        $context = [];

        foreach ($fields as $field) {
            $monitoringValue = $monitoring?->getAttribute($field);
            // Empty legacy visit lists do not withdraw assessment food restrictions.
            if (in_array($field, ['allergies', 'food_dislikes'], true) && empty($monitoringValue)) {
                $monitoringValue = null;
            }
            $context[$field] = $monitoringValue ?? $assessment?->getAttribute($field);
            $context['field_sources'][$field] = $monitoringValue !== null
                ? ($monitoring->observed_at ?? $monitoring->created_at)?->toDateString()
                : $assessment?->created_at?->toDateString();
        }
        $context['allergies'] ??= [];
        $context['food_dislikes'] ??= [];

        $context['source_type'] = $monitoring === null ? 'assessment' : 'monitoring';
        $context['source_monitoring_id'] = $monitoring?->uuid;
        $context['source_monitoring_date'] = $monitoring?->observed_at?->toDateString()
            ?? $monitoring?->created_at?->toDateString();
        $context['measurement_date'] = $context['field_sources']['weight'] ?? now()->toDateString();
        $context['age_years'] = $ncpRecord->patient?->dob
            ? Carbon::parse($ncpRecord->patient->dob)->diffInYears(Carbon::parse($context['measurement_date']), false)
            : null;
        $context['age_months'] = $ncpRecord->patient?->dob
            ? Carbon::parse($ncpRecord->patient->dob)->diffInMonths(Carbon::parse($context['measurement_date']), false)
            : null;
        $context['sex'] = $ncpRecord->patient?->sex;
        $context['lab_values'] = $monitoring?->lab_values ?? $assessment?->biochemicalData?->toArray() ?? [];

        return $context;
    }

    public function calculate(NcpRecord $ncpRecord, array $attributes, ?Intervention $savedPlan = null): array
    {
        $context = $savedPlan === null ? $this->for($ncpRecord) : $this->build($ncpRecord, $savedPlan->sourceMonitoring, false);
        if (($attributes['goal_type'] ?? null) === 'custom') {
            if (! in_array($attributes['energy_adjustment_mode'] ?? 'none', [null, 'none'], true)) {
                throw ValidationException::withMessages(['energy_adjustment_mode' => ['Custom Plan uses an individually entered final prescription.']]);
            }
            $restricted = ($attributes['fluid_mode'] ?? null) === 'restricted';
            $calculated = ['energy_kcal' => null, 'protein_g' => null, 'carbs_g' => null, 'fat_g' => null,
                'fluid_ml' => $restricted ? (float) $attributes['fluid_ml'] : null];

            return [...$calculated, 'calculation_status' => 'manual_targets_required',
                'source_type' => $context['source_type'], 'source_monitoring_id' => $context['source_monitoring_id'],
                'source_monitoring_date' => $context['source_monitoring_date'],
                'fluid_mode' => $restricted ? 'restricted' : 'estimated',
                'nutrient_operators' => $restricted ? ['fluid_ml' => 'max'] : [],
                'calculation_snapshot' => [
                    'version' => 2, 'method' => 'Clinician-entered prescription', 'source' => 'RND individual prescription',
                    'inputs' => array_filter(['ageYears' => $context['age_years'], 'sex' => $context['sex'],
                        'ageMonths' => $context['age_months'], 'pregnancyLactationStatus' => $context['pregnancy_lactation_status'],
                        'weightKg' => isset($context['weight']) ? (float) $context['weight'] : null,
                        'heightCm' => isset($context['height']) ? (float) $context['height'] : null], fn ($value) => $value !== null),
                    'measurement_date' => $context['measurement_date'], 'baseline_energy_kcal' => null,
                    'energy_adjustment_mode' => 'none', 'energy_adjustment_kcal' => 0, 'signed_adjustment_kcal' => 0,
                    'fluid_mode' => $restricted ? 'restricted' : 'estimated', 'calculated' => $calculated,
                ]];
        }
        $missing = [];
        foreach (['weight', 'height', 'age_years', 'sex'] as $field) {
            if ($context[$field] === null || $context[$field] === '') {
                $missing[$field] = ['Confirm '.str_replace('_', ' ', $field).' before calculation.'];
            }
        }
        if ($context['edema_present'] && ! $context['dry_weight_kg']) {
            $missing['dry_weight_kg'] = ['Confirm dry weight before calculation.'];
        }
        if (($context['age_years'] ?? 0) >= 18
            && in_array($attributes['goal_type'] ?? null, ['general_diet', 'diabetic_control', 'cardiac_diet'], true)
            && blank($context['physical_activity_level'])) {
            $missing['physical_activity_level'] = ['Confirm an adult activity estimate before calculation.'];
        }
        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
        if ($context['pregnancy_lactation_status'] === 'pregnant_unspecified') {
            throw ValidationException::withMessages(['pregnancy_lactation_status' => ['Confirm the pregnancy trimester before automatic maternal targets are available.']]);
        }
        $options = $attributes['calculation_options'] ?? [];
        $activityKey = Assessment::ACTIVITY_LEVEL_MAP[strtolower(trim((string) $context['physical_activity_level']))] ?? null;
        if (($context['age_years'] ?? 0) >= 18
            && in_array($attributes['goal_type'] ?? null, ['general_diet', 'diabetic_control', 'cardiac_diet'], true)
            && $activityKey === null) {
            throw ValidationException::withMessages(['physical_activity_level' => ['Select a supported activity estimate.']]);
        }
        $weight = $context['edema_present'] ? $context['dry_weight_kg'] : $context['weight'];
        $metrics = [
            'weightKg' => (float) $weight, 'heightCm' => (float) $context['height'],
            'ageYears' => (float) $context['age_years'], 'sex' => $context['sex'],
            'ageMonths' => (float) $context['age_months'],
            'isAdult' => $context['age_years'] >= 18,
            'activityFactor' => NutritionPrescriptionService::ACTIVITY_FACTORS[$activityKey ?? 'sedentary'],
            'pregnancyLactationStatus' => $context['pregnancy_lactation_status'] ?? 'none',
            'measurementDate' => $context['measurement_date'],
            'weightBasis' => $context['edema_present'] ? 'dry' : 'measured',
            'energyAdjustmentMode' => $attributes['energy_adjustment_mode'] ?? 'none',
            'energyAdjustmentKcal' => $attributes['energy_adjustment_kcal'] ?? 0,
            'fluidMode' => $attributes['fluid_mode'] ?? 'estimated',
            'fluidLimitMl' => $attributes['fluid_ml'] ?? null,
            'pediatricActivityCategory' => match ($activityKey) {
                'sedentary' => 'inactive', 'light' => 'low_active', 'moderate' => 'active',
                'very_active', 'extra_active' => 'very_active', default => null,
            },
        ];
        foreach ([
            'clinical_weight_kg' => 'clinicalWeightKg', 'protein_weight_kg' => 'proteinWeightKg',
            'energy_per_kg' => 'energyPerKg', 'protein_per_kg' => 'proteinPerKg',
            'weight_basis' => 'weightBasis', 'nutrition_status' => 'nutritionStatus',
            'maternal_confirmed' => 'maternalConfirmed', 'fat_fraction' => 'fatFraction',
        ] as $key => $metric) {
            if (isset($options[$key])) {
                $metrics[$metric] = $options[$key];
            }
        }
        if (isset($options['clinical_weight_kg']) && ! isset($options['weight_basis'])) {
            throw ValidationException::withMessages(['calculation_options.weight_basis' => ['Identify the selected calculation weight basis.']]);
        }
        if (isset($options['weight_basis']) && ! isset($options['clinical_weight_kg'])
            && $options['weight_basis'] !== ($context['edema_present'] ? 'dry' : 'measured')) {
            throw ValidationException::withMessages(['calculation_options.weight_basis' => ['Enter the confirmed calculation weight before selecting a different weight basis.']]);
        }
        try {
            $result = app(NutritionPrescriptionService::class)->autofill($attributes['goal_type'], $attributes['disease_stage'] ?? null, $metrics);
        } catch (\InvalidArgumentException $error) {
            throw ValidationException::withMessages(['calculation_options' => [$error->getMessage()]]);
        }
        $result['calculation_snapshot']['field_sources'] = $context['field_sources'];
        $result['calculation_snapshot']['measurement_source'] = $context['source_type'];
        $result['source_type'] = $context['source_type'];
        $result['source_monitoring_id'] = $context['source_monitoring_id'];
        $result['source_monitoring_date'] = $context['source_monitoring_date'];

        return $result;
    }
}
