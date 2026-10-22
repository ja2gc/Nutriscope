<?php

return [

    /*
    | Legacy condition lookup for existing integrations.
    | Current intervention advice uses InterventionGuidance and the actual
    | individual prescription, not automatic disease-wide nutrient orders.
    */

    'goal_type_conditions' => [
        'general_diet' => [],
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
        'general_diet' => [],
        'diabetic_control' => ['type_1', 'type_2'],
        'renal_diet' => ['ckd_1_2', 'ckd_3_5_non_dialysis', 'hemodialysis'],
        'cardiac_diet' => ['hypertension', 'dyslipidemia', 'heart_failure'],
        'high_protein' => ['postoperative_recovery', 'wound_healing'],
        'liver_disease' => ['compensated', 'decompensated'],
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
