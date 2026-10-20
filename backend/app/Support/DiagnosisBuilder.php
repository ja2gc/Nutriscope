<?php

namespace App\Support;

class DiagnosisBuilder
{
    public static function options(): array
    {
        return once(fn () => json_decode(file_get_contents(resource_path('diagnosis-options.json')), true, flags: JSON_THROW_ON_ERROR));
    }

    public static function rules(): array
    {
        $rules = [
            'builder_data' => ['sometimes', 'nullable', 'array:problem_checks,problem_other,etiology_checks,etiology_other,sign_checks,sign_other,etiology_notes,sign_notes,ni_direction,ni_nutrient'],
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

        return $rules;
    }

    public static function components(string $domain, array $builder): array
    {
        $join = static fn (string $checks, string $other): string => implode('; ', array_filter([
            ...($builder[$checks] ?? []), trim((string) ($builder[$other] ?? '')),
        ], static fn ($value): bool => $value !== ''));
        $problem = $domain === 'NI'
            ? trim(($builder['ni_direction'] ?? '').' '.preg_replace('/\s+Intake$/i', '', (string) ($builder['ni_nutrient'] ?? '')).' Intake')
            : $join('problem_checks', 'problem_other');

        return ['problem' => $problem, 'etiology' => $join('etiology_checks', 'etiology_other'), 'signs_symptoms' => $join('sign_checks', 'sign_other')];
    }

    public static function fromDraft(string $domain, string $problem, string $etiology, string $signs, array $notes = []): array
    {
        $options = self::options()[$domain];
        $builder = ['etiology_notes' => $notes['etiology_notes'] ?? '', 'sign_notes' => $notes['sign_notes'] ?? ''];
        foreach (['problem' => [$problem, 'problems'], 'etiology' => [$etiology, 'etiologies'], 'sign' => [$signs, 'signs']] as $part => [$text, $list]) {
            $builder[$part.'_checks'] = in_array($text, $options[$list], true) ? [$text] : [];
            $builder[$part.'_other'] = $builder[$part.'_checks'] === [] ? $text : '';
        }
        if ($domain === 'NI' && preg_match('/^(Inadequate|Excessive) (.+) Intake$/', $problem, $matches)) {
            $builder['ni_direction'] = $matches[1];
            $builder['ni_nutrient'] = $matches[2];
            $builder['problem_other'] = '';
        }

        return $builder;
    }
}
