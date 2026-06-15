<?php

return [

    /*
    |--------------------------------------------------------------------------
    | goal_type → clinical_rules.condition[] mapping
    |--------------------------------------------------------------------------
    |
    | Drives the intervention "recommendations" endpoint: an intervention's
    | goal_type is translated to the clinical condition(s) whose food
    | recommend/avoid/limit rules apply, then RecommendService reads those rows
    | from the clinical_rules table. The food-disease rules themselves live in
    | clinical_rules (never hardcoded); this map is only the goal→condition lookup.
    |
    | Condition values MUST match clinical_rules.condition exactly. Seeded values:
    |   CKD, DM, dyslipidemia, gout, hypertension, liver_disease, malnutrition.
    |
    | goal_type values are defined in docs/logic/intervention-goals.md (Appendix).
    | Purely calculation-based goals (weight_loss, weight_gain, high_protein, custom)
    | carry no inherent disease food-rules — recommendations for a comorbidity are
    | driven by that comorbidity's own goal. They map to [] (empty).
    |
    | NOTE (confirm with a dietitian): cardiac_diet → [hypertension, dyslipidemia]
    | reflects the cardiac diet's sodium + lipid focus (intervention-goals.md §4).
    */

    'goal_type_conditions' => [
        'renal_diet'       => ['CKD'],
        'diabetic_control' => ['DM'],
        'cardiac_diet'     => ['hypertension', 'dyslipidemia'],
        'liver_disease'    => ['liver_disease'],
        'malnutrition'     => ['malnutrition'],
        'weight_loss'      => [],
        'weight_gain'      => [],
        'high_protein'     => [],
        'custom'           => [],
    ],

];
