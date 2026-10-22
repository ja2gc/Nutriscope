<?php

namespace App\Support;

class InterventionGuidance
{
    public const FIELD_MAX = 1200;

    public const TOTAL_MAX = 2200;

    public const FIELDS = [
        'education_notes',
        'counseling_goals',
        'barriers',
        'strategies',
    ];

    /** @param array<string,mixed> $values */
    public static function characterCount(array $values): int
    {
        return collect(self::FIELDS)
            ->sum(fn (string $field): int => mb_strlen((string) ($values[$field] ?? '')));
    }

    private const EDUCATION = [
        'general_diet' => 'Choose varied foods in prescribed portions, including vegetables, fruit and protein; limit sweetened drinks and salty foods.',
        'diabetic_control' => 'Follow prescribed carbohydrate portions and meal timing; choose suitable higher-fiber foods and keep the agreed low-glucose treatment.',
        'renal_diet' => 'Follow prescribed protein and fluid targets; limit salty foods and restrict potassium or phosphorus only when indicated.',
        'cardiac_diet' => 'Choose vegetables, whole grains, fish and unsaturated oils; limit salty foods and large portions high in saturated fat.',
        'high_protein' => 'Include protein across meals and enough energy for recovery, within tolerance and any other clinical limits.',
        'liver_disease' => 'Eat regularly with adequate protein and an advised evening snack; avoid alcohol, unsafe raw animal foods and unpasteurized milk.',
        'custom' => 'Follow the individual prescription, including any confirmed food or fluid limits.',
    ];

    private const TYPE_NOTES = [
        'type_1' => 'Coordinate carbohydrate intake, insulin and activity with the diabetes team; this plan does not change insulin doses.',
        'type_2' => 'Review carbohydrate portions, activity and glucose trends; weight direction follows assessed needs.',
        'ckd_1_2' => 'Preserve adequate intake; early CKD alone does not require broad food restrictions.',
        'ckd_3_5_non_dialysis' => 'Follow the confirmed protein target and review kidney function, intake and labs.',
        'hemodialysis' => 'Confirm dry weight and dialysis-team protein, mineral and fluid orders.',
        'hypertension' => 'Reduce sodium from salty seasonings and processed foods within the prescribed target.',
        'dyslipidemia' => 'Replace some saturated fat with unsaturated fats and choose suitable fiber-rich foods.',
        'heart_failure' => 'Follow actual sodium/fluid orders and report worsening swelling, breathlessness or sudden weight change.',
        'postoperative_recovery' => 'Meet recovery targets with tolerated meals/snacks and follow surgical texture and route orders.',
        'wound_healing' => 'Meet assessed energy/protein needs; use supplements only when indicated or prescribed.',
        'compensated' => 'Avoid prolonged fasting and review intake and muscle loss.',
        'decompensated' => 'Confirm usable weight with fluid retention; do not restrict protein solely for encephalopathy.',
    ];

    private const CONTEXT_NOTES = [
        'energy' => 'Follow the confirmed energy target and review weight, intake and tolerance at follow-up.',
        'surplus' => 'The total target includes +{amount} kcal/day; review weight, intake and tolerance.',
        'deficit' => 'The total target includes −{amount} kcal/day; review weight, intake and tolerance.',
    ];

    private const FOODS = [
        'general_diet' => ['choose' => 'Vegetables/fruit; varied protein foods; portioned grains', 'limit' => 'Sweetened drinks; salty processed foods'],
        'type_1' => ['choose' => 'Non-starchy vegetables; portioned grains/fruit; fish, eggs or tofu', 'limit' => 'Sweetened drinks and large carbohydrate portions; preserve the agreed low-glucose treatment'],
        'type_2' => ['choose' => 'Same shared diabetes pattern', 'limit' => 'Same shared diabetes advice; no compulsory deficit or fruit/rice ban'],
        'ckd_1_2' => ['choose' => 'Varied foods fitting the prescription', 'limit' => 'Salty foods; mineral restrictions only when indicated'],
        'ckd_3_5_non_dialysis' => ['choose' => 'Foods meeting the confirmed protein target; suitable plant foods', 'limit' => 'Salty foods and phosphate additives; potassium/phosphorus portions depend on labs/orders'],
        'hemodialysis' => ['choose' => 'Protein foods in prescribed portions; adequate energy', 'limit' => 'Salty foods; minerals and fluids as ordered, without carrying over a predialysis low-protein rule'],
        'hypertension' => ['choose' => 'Vegetables/fruit when suitable; whole grains; varied lean/plant proteins', 'limit' => 'Salty seasonings and processed foods; potassium-rich foods/salt substitutes require renal/medication review'],
        'dyslipidemia' => ['choose' => 'Fiber-rich foods; fish; unsaturated oils', 'limit' => 'Large portions high in saturated fat'],
        'heart_failure' => ['choose' => 'Balanced foods meeting the prescription; lower-sodium choices', 'limit' => 'Salty foods; fluid only to the actual prescribed limit'],
        'postoperative_recovery' => ['choose' => 'Protein foods across meals; adequate energy; tolerated textures', 'limit' => 'Unnecessary exclusions; surgical route/texture orders take precedence'],
        'wound_healing' => ['choose' => 'Protein foods; adequate energy; varied produce', 'limit' => 'Unnecessary exclusions; no routine high-dose vitamin/zinc advice'],
        'compensated' => ['choose' => 'Regular meals; adequate protein; advised evening snack', 'limit' => 'Avoid alcohol, raw/undercooked animal foods and unpasteurized milk'],
        'decompensated' => ['choose' => 'Same core cirrhosis pattern, adapted to intake/tolerance', 'limit' => 'Same food-safety exclusions; sodium/fluid limits only as ordered; no encephalopathy-driven protein ban'],
        'custom' => ['choose' => 'Foods appropriate to the confirmed individual prescription', 'limit' => 'Actual allergies, safety exclusions and prescribed limits'],
    ];

    public static function for(string $goal, ?string $type, array $context = []): array
    {
        $strategy = array_filter([self::TYPE_NOTES[$type ?? ''] ?? null]);
        $precautions = [];
        if (! empty($context['refeeding_risk'])) {
            $precautions[] = 'advance intake under clinical supervision with electrolyte monitoring; displayed calories are the final target';
        }
        if (($context['fluid_mode'] ?? null) === 'restricted') {
            $precautions[] = 'count all sources toward the fluid maximum';
        }
        if ($precautions) {
            $strategy[] = ucfirst(implode('; ', $precautions)).'.';
        }
        $mode = $context['energy_adjustment_mode'] ?? 'none';
        $counseling = self::CONTEXT_NOTES[in_array($mode, ['surplus', 'deficit'], true) ? $mode : 'energy'];
        $foods = self::FOODS[$type ?? $goal] ?? self::FOODS[$goal] ?? self::FOODS['custom'];
        if ($type === 'type_2') {
            $foods = self::FOODS['type_1'];
        } elseif ($type === 'decompensated') {
            $foods['choose'] = self::FOODS['compensated']['choose'];
            $foods['limit'] = self::FOODS['compensated']['limit'].'; sodium and fluid only as ordered';
        }
        $education = self::EDUCATION[$goal] ?? self::EDUCATION['custom'];
        $reviews = [];
        if (($context['nutrition_status'] ?? null) === 'malnutrition_or_muscle_loss') {
            $reviews[] = 'poor intake and muscle loss';
        }
        if (! empty($context['pediatric'])) {
            $reviews[] = 'growth and age-appropriate feeding';
        }
        if (! empty($context['maternal'])) {
            $reviews[] = 'pregnancy/lactation needs and food safety';
        }
        if (($context['age_months'] ?? 216) < 6) {
            $education = 'Follow the pediatric breastmilk or infant-formula plan; do not use ordinary food menus or change milk concentration without clinical instructions.';
            $foods = ['choose' => 'Breastmilk or infant formula as clinically directed', 'limit' => 'Ordinary solid-food menus and unprescribed feeding changes'];
        }

        if ($reviews) {
            $education .= ' Review '.implode(', ', $reviews).' with the care team.';
        }

        return [
            'education_notes' => $education,
            'counseling_goals' => str_replace('{amount}', (string) ($context['energy_adjustment_kcal'] ?? 0), $counseling),
            'strategies' => implode("\n", $strategy),
            'foods' => [...$foods, 'context' => 'Follow current allergies, age-appropriate textures, labs and prescribed limits. These examples do not replace the individual prescription.'],
        ];
    }
}
