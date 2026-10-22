<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\InterventionGuidance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InterventionPlanService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly InterventionCalculationContextService $context,
        private readonly NutritionPrescriptionService $prescription,
    ) {}

    public function create(
        NcpRecord $ncpRecord,
        array $attributes,
        User $actor,
        ?Monitoring $sourceMonitoring = null,
    ): Intervention {
        $this->validateOwnership($ncpRecord, $actor, $sourceMonitoring);
        $this->auditLogger->assertAvailable();
        $attributes = $this->prepareAttributes($ncpRecord, $attributes);
        if (! isset($attributes['weight_target'])) {
            $context = $this->context->for($ncpRecord);
            if (($context['age_years'] ?? 0) >= 18 && ($context['height'] ?? 0) > 0 && in_array($context['sex'] ?? null, ['Male', 'Female'], true)) {
                $attributes['weight_target'] = ['target' => round($this->prescription->ibw((float) $context['height'], $context['sex']), 1)];
            }
        } else {
            $attributes['weight_target'] = array_map('floatval', $attributes['weight_target']);
        }

        return DB::transaction(function () use ($ncpRecord, $attributes, $sourceMonitoring): Intervention {
            $lockedNcpRecord = NcpRecord::query()
                ->whereKey($ncpRecord->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $intervention = new Intervention($attributes);
            $intervention->ncp_record_id = $lockedNcpRecord->getKey();
            $intervention->source_monitoring_id = $sourceMonitoring?->getKey();
            $intervention->save();

            return $intervention;
        }, 3);
    }

    public function prepareAttributes(NcpRecord $record, array $attributes, ?Intervention $existing = null): array
    {
        $merged = array_replace($existing?->only([
            'goal_type', 'disease_stage', 'energy_adjustment_mode', 'energy_adjustment_kcal',
            'fluid_mode', 'fluid_ml', 'calculation_options',
        ]) ?? [], $attributes);
        if (empty($merged['goal_type'])) {
            unset($attributes['recalculate']);

            return $attributes;
        }
        $changed = $existing === null || ! empty($attributes['recalculate']);
        unset($attributes['recalculate']);
        foreach (['goal_type', 'disease_stage', 'energy_adjustment_mode', 'energy_adjustment_kcal'] as $field) {
            $previous = $existing?->getAttribute($field);
            $next = $attributes[$field] ?? null;
            if ($field === 'energy_adjustment_mode') {
                $previous ??= 'none';
                $next ??= 'none';
            } elseif ($field === 'energy_adjustment_kcal') {
                $previous ??= 0;
                $next ??= 0;
            }
            if (array_key_exists($field, $attributes) && $next != $previous) {
                $changed = true;
            }
        }
        $numericOptions = function (array $options) use ($merged): array {
            unset($options['refeeding_risk'], $options['malnutrition_note']);
            if (($merged['goal_type'] ?? null) !== 'liver_disease') {
                unset($options['nutrition_status']);
            }

            return array_filter($options, fn ($value) => $value !== null);
        };
        if (array_key_exists('calculation_options', $attributes)
            && $numericOptions($attributes['calculation_options'] ?? []) != $numericOptions($existing?->calculation_options ?? [])) {
            $changed = true;
        }
        $snapshot = $existing?->calculation_snapshot;
        if ($changed) {
            $result = $this->context->calculate($record, $merged);
            $snapshot = $result['calculation_snapshot'];
            $attributes['source_monitoring_id'] = empty($result['source_monitoring_id']) ? null
                : $record->monitorings()->where('uuid', $result['source_monitoring_id'])->value('id');
            $attributes['energy_adjustment_mode'] = $snapshot['energy_adjustment_mode'];
            $attributes['energy_adjustment_kcal'] = $snapshot['energy_adjustment_kcal'];
            $attributes['fluid_mode'] = $result['fluid_mode'];
            foreach ($snapshot['calculated'] as $field => $value) {
                if (! array_key_exists($field, $attributes)) {
                    $attributes[$field] = $value;
                }
            }
        }
        if ($snapshot) {
            $overrides = [];
            foreach ($snapshot['calculated'] as $field => $calculated) {
                $value = $attributes[$field] ?? $existing?->getAttribute($field);
                if ($value !== null && ($calculated === null || (float) $value !== (float) $calculated)) {
                    $overrides[$field] = (float) $value;
                }
            }
            if ($overrides !== [] || array_key_exists('manual_overrides', $snapshot)) {
                $snapshot['manual_overrides'] = $overrides;
            }
            $attributes['calculation_snapshot'] = $snapshot;
        }
        if (($merged['fluid_mode'] ?? null) === 'restricted') {
            $attributes['nutrient_operators'] = [...($attributes['nutrient_operators'] ?? $existing?->nutrient_operators ?? []), 'fluid_ml' => 'max'];
        }
        if ($existing === null) {
            $guidance = InterventionGuidance::for($merged['goal_type'], $merged['disease_stage'] ?? null, [
                ...($merged['calculation_options'] ?? []),
                'energy_adjustment_mode' => empty($snapshot['manual_overrides']['energy_kcal']) ? ($snapshot['energy_adjustment_mode'] ?? 'none') : 'none',
                'energy_adjustment_kcal' => $snapshot['energy_adjustment_kcal'] ?? 0,
                'fluid_mode' => $merged['fluid_mode'] ?? null,
                'pediatric' => ($snapshot['inputs']['ageYears'] ?? 18) < 18,
                'age_months' => $snapshot['inputs']['ageMonths'] ?? (($snapshot['inputs']['ageYears'] ?? 18) * 12),
                'maternal' => ! in_array($snapshot['inputs']['pregnancyLactationStatus'] ?? 'none', [null, 'none'], true),
            ]);
            foreach (['education_notes', 'counseling_goals', 'strategies'] as $field) {
                if (! array_key_exists($field, $attributes)) {
                    $attributes[$field] = $guidance[$field];
                }
            }
        }
        $final = array_replace($existing?->toArray() ?? [], $attributes);
        if (! empty($snapshot['manual_overrides']['energy_kcal'])) {
            $adjusted = InterventionGuidance::for($merged['goal_type'], $merged['disease_stage'] ?? null, [
                'energy_adjustment_mode' => $snapshot['energy_adjustment_mode'] ?? 'none',
                'energy_adjustment_kcal' => $snapshot['energy_adjustment_kcal'] ?? 0,
            ])['counseling_goals'];
            if (($final['counseling_goals'] ?? null) === $adjusted) {
                $attributes['counseling_goals'] = $final['counseling_goals'] = InterventionGuidance::for($merged['goal_type'], $merged['disease_stage'] ?? null)['counseling_goals'];
            }
        }
        $guidanceChanged = $existing === null || collect(InterventionGuidance::FIELDS)->contains(fn (string $field): bool => array_key_exists($field, $attributes) && $attributes[$field] !== $existing->getAttribute($field));
        if ($guidanceChanged && InterventionGuidance::characterCount($final) > InterventionGuidance::TOTAL_MAX) {
            throw ValidationException::withMessages(['education_notes' => ['Shorten the combined education and counseling guidance to 2,200 characters.']]);
        }
        $targetsChanged = $existing === null || collect(['energy_kcal', 'protein_g', 'fat_g', 'nutrient_operators'])->contains(
            fn (string $field): bool => array_key_exists($field, $attributes) && $attributes[$field] != $existing->getAttribute($field),
        );
        if ($targetsChanged) {
            $operators = $final['nutrient_operators'] ?? [];
            $minimumMacroEnergy = (($operators['protein_g'] ?? null) === 'max' ? 0 : 4 * (float) ($final['protein_g'] ?? 0))
                + (($operators['fat_g'] ?? null) === 'max' ? 0 : 9 * (float) ($final['fat_g'] ?? 0));
            if (($final['energy_kcal'] ?? 0) > 0 && ($operators['energy_kcal'] ?? null) !== 'min' && $minimumMacroEnergy > (float) $final['energy_kcal']) {
                throw ValidationException::withMessages(['energy_kcal' => ['Energy cannot be below the energy supplied by the prescribed protein and fat. Review the final targets and bounds.']]);
            }
        }

        return $attributes;
    }

    private function validateOwnership(
        NcpRecord $ncpRecord,
        User $actor,
        ?Monitoring $sourceMonitoring,
    ): void {
        if ($ncpRecord->rnd_user_id !== $actor->getKey()) {
            throw ValidationException::withMessages([
                'ncp_record' => ['The NCP record does not belong to this RND.'],
            ]);
        }

        if ($sourceMonitoring !== null && $sourceMonitoring->ncp_record_id !== $ncpRecord->getKey()) {
            throw ValidationException::withMessages([
                'source_monitoring' => ['The monitoring record does not belong to this NCP record.'],
            ]);
        }
    }
}
