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
        'diabetic_control' => 'Follow your meal portions for rice, bread, fruit and other carbohydrate foods; know what to eat or drink if your blood sugar drops.',
        'renal_diet' => 'Follow your kidney team\'s advice on protein and drinks; use less salt and avoid other foods only when your team advises it.',
        'cardiac_diet' => 'Choose vegetables, whole grains and fish; limit salty packaged foods, fatty meats and butter.',
        'high_protein' => 'Include protein foods you can safely eat at meals; eat enough to support healing, following your care plan.',
        'liver_disease' => 'Eat regular meals with enough protein and an evening snack if advised; avoid alcohol, raw or undercooked meat or seafood, and raw milk.',
        'custom' => 'Follow the food and drink plan made for you by your care team.',
    ];

    private const TYPE_NOTES = [
        'type_1' => 'Match meals and activity to the insulin plan agreed with your diabetes team; ask them before changing insulin doses.',
        'type_2' => 'Follow your meal portions, stay active as advised and track blood sugar; ask your care team about a suitable weight goal.',
        'ckd_1_2' => 'Eat enough to stay well; early kidney disease alone does not mean you must avoid many foods.',
        'ckd_3_5_non_dialysis' => 'Follow the protein portions in your plan and attend kidney checkups and blood tests.',
        'hemodialysis' => 'Spread protein foods across your meals and follow any salt or drink limits set by your dialysis team.',
        'hypertension' => 'Use less salt, salty sauces and packaged foods to help manage blood pressure.',
        'dyslipidemia' => 'Choose suitable high-fiber foods such as oats and beans; use oils such as olive or canola instead of some butter or fatty meat.',
        'heart_failure' => 'Follow your advised salt and drink limits; tell your care team about worse swelling, shortness of breath or sudden weight change.',
        'postoperative_recovery' => 'Eat meals and snacks you can manage; follow your surgical team\'s advice on when, what and how to eat.',
        'wound_healing' => 'Eat enough food and include protein at meals; take nutrition supplements only if your care team recommends them.',
        'compensated' => 'Avoid long gaps between meals and tell your care team if you are eating less or losing strength.',
        'decompensated' => 'Keep eating the protein foods in your plan; ask your care team about salt and drink limits if you have swelling.',
    ];

    private const CONTEXT_NOTES = [
        'energy' => 'Follow the portions in your meal plan; discuss appetite or weight changes with your care team.',
        'surplus' => 'Eat the extra portions in your plan; tell your care team if you feel too full or your weight changes.',
        'deficit' => 'Follow the portions in your plan; choose water instead of sugary drinks and tell your care team if you feel very hungry.',
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
            $precautions[] = 'your care team will guide gradual feeding increases and check your blood mineral levels';
        }
        if (($context['fluid_mode'] ?? null) === 'restricted') {
            $precautions[] = 'count drinks and water in foods toward your daily fluid limit';
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
        if ($context['malnutrition_note'] ?? (($context['nutrition_status'] ?? null) === 'malnutrition_or_muscle_loss')) {
            $reviews[] = 'appetite or strength changes';
        }
        if (! empty($context['pediatric'])) {
            $reviews[] = 'growth and safe feeding';
        }
        if (! empty($context['maternal'])) {
            $reviews[] = 'pregnancy or breastfeeding';
        }
        if (($context['age_months'] ?? 216) < 6) {
            $education = 'Follow your baby\'s breastmilk or infant-formula plan; do not start solid foods or change how you mix formula unless your care team advises it.';
            $foods = ['choose' => 'Breastmilk or infant formula as clinically directed', 'limit' => 'Ordinary solid-food menus and unprescribed feeding changes'];
        }

        if ($reviews) {
            $education .= ' Discuss '.implode(', ', $reviews).' with your care team.';
        }

        return [
            'education_notes' => $education,
            'counseling_goals' => str_replace('{amount}', (string) ($context['energy_adjustment_kcal'] ?? 0), $counseling),
            'strategies' => implode("\n", $strategy),
            'foods' => [...$foods, 'context' => 'Follow current allergies, age-appropriate textures, labs and prescribed limits. These examples do not replace the individual prescription.'],
        ];
    }
}
