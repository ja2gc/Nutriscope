<?php

namespace App\Support;

use App\Models\NcpRecord;
use App\Models\Patient;
use App\Services\Diagnosis\PesEligibilityService;
use Illuminate\Support\Str;

class ClinicalAiContext
{
    public static function library(): array
    {
        return once(fn () => json_decode(file_get_contents(resource_path('clinical-ai-context.json')), true, flags: JSON_THROW_ON_ERROR));
    }

    public static function version(): string
    {
        return self::library()['version'];
    }

    /** Only allowlisted clinical fields leave the application. */
    public static function patientEvidence(NcpRecord $record): array
    {
        $record->loadMissing(['patient', 'assessment']);
        $assessment = $record->assessment;

        return array_filter(self::redact([
            'referral_diagnosis' => $record->patient?->medical_diagnosis,
            'nutrition_care_category' => $assessment?->primary_diagnosis_category,
            'nutrition_care_other' => $assessment?->primary_diagnosis_other,
            'medications' => $assessment?->medications,
        ], $record->patient), fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    public static function redact(mixed $value, ?Patient $patient): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => self::redact($item, $patient), $value);
        }
        if (! is_string($value)) {
            return $value;
        }
        $value = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[redacted]', $value);
        foreach (array_filter([$patient?->display_name, $patient?->getRawOriginal('name'), $patient?->first_name, $patient?->last_name, $patient?->middle_name,
            $patient?->hospital_number, $patient?->patient_code, $patient?->address, $patient?->contact, $patient?->physician]) as $identifier) {
            if (is_string($identifier) && trim($identifier) !== '') {
                $value = preg_replace('/(?<![\p{L}\p{N}])'.preg_quote(trim($identifier), '/').'(?![\p{L}\p{N}])/iu', '[redacted]', $value);
            }
        }
        $value = preg_replace_callback('/(\+?\d[\d ()-]{8,}\d)(\s*(?:kcal|kg|g|mg|mcg|IU|mmol|mEq|ml|cm)\b)?/i', static function (array $match): string {
            $digits = preg_replace('/\D/', '', $match[1]);
            $range = ! empty($match[2]) && preg_match('/^\d+\s*-\s*\d+$/', $match[1]);

            return strlen($digits) < 10 || $range ? $match[0] : '[redacted]'.($match[2] ?? '');
        }, $value);

        return Str::limit(trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $value)), 600, '');
    }

    /** Select on the server; send guidance, never the library/selector/source metadata. */
    public static function guidance(array $evidence, string $purpose): string
    {
        $clinical = [...$evidence, ...($evidence['clinical_context'] ?? [])];
        $text = implode('; ', array_filter([
            $clinical['referral_diagnosis'] ?? null, $clinical['medical_history'] ?? null,
            $clinical['nutrition_care_other'] ?? null, $clinical['physical_assessment'] ?? null,
            implode('; ', $clinical['medications'] ?? []),
        ], 'is_string'));
        $text = implode('; ', array_map(fn ($part) => preg_match('/\b(suspected|possible|ruled out)\b/i', $part)
            ? '' : preg_replace('/\b(no|denies|denied|family history)\b.*$/i', '', $part), preg_split('/[.;\n]+/', $text)));
        $keys = array_keys($evidence['laboratory_flags'] ?? []);
        $keys = array_unique([...$keys, ...array_column($evidence['indicators'] ?? [], 'key'),
            ...array_keys($evidence['intake_nutrients'] ?? []), ...array_keys($evidence['recall_intake_estimate'] ?? []), ...array_keys($evidence['food_recall']['average_daily'] ?? [])]);
        if (! empty($evidence['symptoms'])) {
            $keys[] = 'gi_symptoms';
        }
        foreach (['dietary_intake', 'physical_assessment', 'gi_symptoms', 'intake_notes'] as $key) {
            if (! empty($evidence[$key])) {
                $keys[] = $key;
            }
        }
        // BMI/weight context is useful only where those measurements are supplied.
        foreach (['bmi', 'weight_kg' => 'weight', 'weight_loss_percentage'] as $field => $key) {
            $field = is_int($field) ? $key : $field;
            if (isset($evidence[$field])) {
                $keys[] = $key;
            }
        }
        $goal = $evidence['goal'] ?? '';
        $type = $evidence['goal_type_detail'] ?? '';
        $selected = [];
        foreach (self::library()['cards'] as $card) {
            $matches = $card['conditions'] === '' || preg_match('/(?<![\p{L}])(?:'.$card['conditions'].')/iu', $text)
                || in_array($goal, $card['goals'], true) || in_array($goal.':'.$type, $card['goals'], true);
            if ($matches && array_intersect($keys, $card['indicators']) !== []) {
                $selected[] = $card[$purpose];
            }
        }

        if ($purpose === 'diagnosis') {
            $eligibility = app(PesEligibilityService::class);
            $intake = $eligibility->intakeFinding($evidence);
            foreach (DiagnosisBuilder::options()['NI']['nutrients'] as $group => $nutrients) {
                foreach ($nutrients as $nutrient) {
                    $directions = [];
                    foreach (['inadequate', 'excessive'] as $direction) {
                        if ($eligibility->supportsIntakeProblem(ucfirst($direction).' '.$nutrient.' Intake', $intake)) {
                            $directions[] = $direction;
                        }
                    }
                    if (count($directions) === 2) {
                        $selected[] = $nutrient.': conflicting intake directions are documented; assess timing and context before selecting a problem.';
                    } elseif ($directions !== []) {
                        $selected[] = str_replace('{nutrient}', strtolower($nutrient), self::library()['nutrient_cards'][$group][$directions[0]]);
                    }
                }
            }
            $routineText = implode('; ', array_filter(array_intersect_key($evidence, array_fill_keys(['lifestyle', 'dietary_intake', 'social_history', 'knowledge_notes', 'medical_history', 'supplements'], true)), 'is_string'));
            foreach (self::library()['behavior_cards'] as $card) {
                foreach ($eligibility->clauses($routineText) as $clause) {
                    if (preg_match('/(?:'.$card['conditions'].')/iu', $clause)
                        && ! preg_match('/\b(?:no|not|never|denies|denied|former|quit|stopped)\s+(?:\w+\s+){0,2}(?:'.$card['conditions'].')/iu', $clause)) {
                        $selected[] = $card['context'];
                        break;
                    }
                }
            }
        }

        return implode("\n", array_unique($selected));
    }
}
