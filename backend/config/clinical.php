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
        'renal_diet' => ['CKD'],
        'diabetic_control' => ['DM'],
        'cardiac_diet' => ['hypertension', 'dyslipidemia'],
        'liver_disease' => ['liver_disease'],
        'malnutrition' => ['malnutrition'],
        'weight_loss' => [],
        'weight_gain' => [],
        'high_protein' => [],
        'custom' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Intervention goal_type -> allowed disease_stage values
    |--------------------------------------------------------------------------
    |
    | Shared by FormRequests and the authoritative autofill endpoint. Unknown
    | goals/stages must fail validation instead of falling through to generic
    | maintenance math.
    */

    'intervention_goal_stages' => [
        'renal_diet' => [
            'stage_1', 'stage_2', 'stage_3', 'stage_4',
            'stage_5_predialysis', 'hemodialysis', 'peritoneal',
        ],
        'diabetic_control' => ['stage_1', 'stage_2', 'stage_3'],
        'cardiac_diet' => ['mild', 'moderate', 'severe'],
        'weight_loss' => ['overweight', 'class_1', 'class_2', 'class_3'],
        'weight_gain' => ['mild', 'moderate', 'severe'],
        'high_protein' => ['mild_stress', 'moderate_stress', 'severe_stress', 'burns'],
        'liver_disease' => [
            'compensated', 'decompensated',
            'encephalopathy_grade_1_2', 'encephalopathy_grade_3_4',
        ],
        'malnutrition' => ['moderate', 'severe'],
        'custom' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Assessment anthropometric input bounds (physiological plausibility)
    |--------------------------------------------------------------------------
    |
    | The prescription engine multiplies weight/height through IBW, working
    | weight and BMR. A `min:0`-only rule let digit-transposition typos
    | (e.g. 70 -> 700 kg, 165 -> 1650 cm) flow straight into flat kcal/kg and
    | protein g/kg targets, producing absurd prescriptions. These bounds reject
    | implausible entries at the source (StoreAssessmentRequest /
    | UpdateAssessmentRequest). Keep the frontend input min/max in sync
    | (frontend assessment page TextInput).
    |
    | Ranges are deliberately wide enough to never reject a real district-
    | hospital patient (neonate -> super-obese adult) while still catching a
    | transposed digit.
    */

    'assessment_input_bounds' => [
        'weight' => ['min' => 1,  'max' => 400], // kg
        'usual_weight' => ['min' => 1,  'max' => 400], // kg
        'dry_weight_kg' => ['min' => 1,  'max' => 400], // kg
        'height' => ['min' => 30, 'max' => 250], // cm
    ],

];
