<?php

namespace App\Support;

class DiagnosisBuilder
{
    public static function options(): array
    {
        return once(fn () => json_decode(file_get_contents(resource_path('diagnosis-options.json')), true, flags: JSON_THROW_ON_ERROR));
    }

    public static function problemLabel(string $domain, string $label, string $signs = ''): string
    {
        foreach (self::options()[$domain]['problems'] as $option) {
            if (mb_strtolower($option) === mb_strtolower($label)) {
                return self::cleanProblemTerm($option);
            }
        }
        $aliases = [
            'Food and Nutrition Knowledge Deficit' => 'Food- and nutrition-related knowledge deficit',
            'Harmful Beliefs / Attitudes about Food' => 'Unsupported beliefs/attitudes about food- or nutrition-related topics (use with caution)',
            'Not Ready for Diet / Lifestyle Change' => 'Not ready for diet/lifestyle change',
            'Food Insecurity' => 'Limited access to food or water',
            'Overweight / Obesity' => 'Overweight/obesity',
            'Food-Medication Interaction' => 'Food–medication interaction',
            'Swallowing / Chewing Difficulty' => preg_match('/\b(chew\w*|biting|masticat\w*)\b/i', $signs) ? 'Biting/Chewing (masticatory) difficulty' : 'Swallowing difficulty',
            'Malnutrition (undernutrition)' => 'Malnutrition',
        ];
        if (isset($aliases[$label])) {
            return self::cleanProblemTerm($aliases[$label]);
        }
        if ($domain === 'NI' && preg_match('/^(Inadequate|Excessive) (.+) Intake$/i', $label, $parts)) {
            $subject = trim($parts[2]);
            $direction = ucfirst(strtolower($parts[1]));
            if (preg_match('/^Vitamin B[12357] \((.+)\)$/i', $subject, $namedVitamin)) {
                $subject = $namedVitamin[1];
            }
            if (preg_match('/^vitamin\s+/i', $subject) || in_array($subject, self::options()['NI']['nutrients']['vitamin'], true)) {
                return $direction.' vitamin intake: '.preg_replace('/^vitamin\s+/i', '', $subject);
            }
            if (in_array(strtolower($subject), array_map('strtolower', self::options()['NI']['nutrients']['mineral']), true)) {
                return $direction.' mineral intake: '.$subject;
            }

            return $direction.' '.preg_replace('/\bcarbohydrates\b/i', 'carbohydrate', strtolower($subject)).' intake';
        }

        return $label;
    }

    public static function cleanProblemTerm(string $term): string
    {
        return trim((string) preg_replace('/\s*\((?:specify|use with caution)\)/i', '', $term));
    }

    public static function isIntakeProblem(string $label): bool
    {
        return preg_match('/^(?:(?:Inadequate|Excessive|Suboptimal) .+ intake|(?:Inadequate|Excessive) (?:enteral|parenteral) nutrition infusion|Predicted (?:suboptimal|excessive) (?:energy|nutrient) intake|(?:Increased|Decreased) nutrient needs|Less than optimal (?:enteral nutrition|parenteral nutrition|intake of types of)|Increased energy expenditure|Limited food acceptance|Inconsistent carbohydrate intake|Imbalance of nutrients|Malnutrition)(?:\b|:)/iu', $label) === 1;
    }

    public static function rules(): array
    {
        $rules = [
            'builder_data' => ['sometimes', 'nullable', 'array:problem_checks,problem_other,problem_override,etiology_checks,etiology_other,sign_checks,sign_other,etiology_notes,sign_notes,ni_direction,ni_nutrient,ni_pattern,ni_group,ni_detail'],
            'builder_data.problem_override' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ai_draft_id' => ['sometimes', 'nullable', 'uuid'],
        ];
        foreach (['problem_checks', 'etiology_checks', 'sign_checks'] as $key) {
            $rules["builder_data.{$key}"] = ['sometimes', 'array', 'max:12'];
            $rules["builder_data.{$key}.*"] = ['string', 'max:255', 'distinct'];
        }
        foreach (['problem_other', 'etiology_other', 'sign_other'] as $key) {
            $rules["builder_data.{$key}"] = ['sometimes', 'nullable', 'string', 'max:75'];
        }
        foreach (['etiology_notes', 'sign_notes'] as $key) {
            $rules["builder_data.{$key}"] = ['sometimes', 'nullable', 'string', 'max:125'];
        }
        $rules['builder_data.ni_direction'] = ['sometimes', 'in:Inadequate,Excessive'];
        $rules['builder_data.ni_nutrient'] = ['sometimes', 'nullable', 'string', 'max:50'];
        $rules['builder_data.ni_pattern'] = ['sometimes', 'in:intake,predicted,needs,composition,other'];
        $rules['builder_data.ni_group'] = ['sometimes', 'nullable', 'in:Vitamins,Minerals,Nutrient / substance (specify)'];
        $rules['builder_data.ni_detail'] = ['sometimes', 'nullable', 'string', 'max:50'];

        return $rules;
    }

    public static function components(string $domain, array $builder): array
    {
        $join = static fn (string $checks, string $other): string => implode('; ', array_filter([
            ...($builder[$checks] ?? []), trim((string) ($builder[$other] ?? '')),
        ], static fn ($value): bool => $value !== ''));
        $problem = trim((string) ($builder['problem_override'] ?? '')) ?: ($domain === 'NI'
            ? self::problemLabel('NI', trim(($builder['ni_direction'] ?? '').' '.preg_replace('/\s+Intake$/i', '', (string) ($builder['ni_nutrient'] ?? '')).' Intake'))
            : self::cleanProblemTerm($join('problem_checks', 'problem_other')));

        return ['problem' => $problem, 'etiology' => $join('etiology_checks', 'etiology_other'), 'signs_symptoms' => $join('sign_checks', 'sign_other')];
    }

    public static function fromDraft(string $domain, string $problem, string $etiology, string $signs, array $notes = []): array
    {
        $options = self::options()[$domain];
        $builder = ['etiology_notes' => $notes['etiology_notes'] ?? '', 'sign_notes' => $notes['sign_notes'] ?? ''];
        foreach (['problem' => [$problem, 'problems'], 'etiology' => [$etiology, 'etiologies'], 'sign' => [$signs, 'signs']] as $part => [$text, $list]) {
            $match = $part === 'problem' ? collect($options[$list])->first(fn ($option) => self::cleanProblemTerm($option) === $text) : (in_array($text, $options[$list], true) ? $text : null);
            $builder[$part.'_checks'] = $match !== null ? [$match] : [];
            $builder[$part.'_other'] = $builder[$part.'_checks'] === [] ? $text : '';
        }
        if ($domain === 'NI' && preg_match('/^(Inadequate|Excessive) (.+) Intake$/i', $problem, $matches)) {
            $builder['ni_direction'] = $matches[1];
            $builder['ni_nutrient'] = $matches[2];
            $builder['problem_other'] = '';
        }

        return $builder;
    }
}
