<?php

namespace Database\Seeders;

use App\Models\ClinicalRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClinicalRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // ── Diabetes Mellitus ──────────────────────────────────────────
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'simple_sugar', 'rule_type' => 'limit', 'threshold' => null, 'unit' => null, 'reason' => 'Limit routine sweetened drinks; individualize carbohydrate portions. Keep the agreed fast-acting carbohydrate treatment for low blood glucose.'],
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'refined_carbs', 'rule_type' => 'limit', 'threshold' => null, 'unit' => null, 'reason' => 'Prefer higher-fiber carbohydrate sources and individualize portions within the prescription.'],
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'fiber', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'g', 'reason' => 'Dietary fiber supports glycemic control and should be individualized within the prescription.'],
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'saturated_fat', 'rule_type' => 'limit', 'threshold' => null, 'unit' => '%kcal', 'reason' => 'Limit saturated fat to reduce cardiovascular risk.'],

            // ── Chronic Kidney Disease ─────────────────────────────────────
            ['condition' => 'CKD', 'stage' => 'all', 'nutrient_or_food_tag' => 'sodium', 'rule_type' => 'limit', 'threshold' => null, 'unit' => 'mg', 'reason' => 'Sodium restriction to control blood pressure and fluid retention'],
            ['condition' => 'CKD', 'stage' => 'ckd_1_2', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'g/kg', 'reason' => 'Use the stage-specific protein target calculated in the patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'ckd_1_2', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'g/kg', 'reason' => 'Use the stage-specific protein target calculated in the patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'ckd_3_5_non_dialysis', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'g/kg', 'reason' => 'Use the stage-specific protein target calculated in the patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'ckd_3_5_non_dialysis', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'g/kg', 'reason' => 'Use the stage-specific protein target calculated in the patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'ckd_3_5_non_dialysis', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'g/kg', 'reason' => 'Use the stage-specific protein target calculated in the patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'hemodialysis', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'g/kg', 'reason' => 'Hemodialysis increases protein needs; use the calculated patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'peritoneal', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'g/kg', 'reason' => 'Peritoneal dialysis increases protein needs; use the calculated patient prescription.'],

            // ── Hypertension ───────────────────────────────────────────────
            ['condition' => 'hypertension', 'stage' => 'all', 'nutrient_or_food_tag' => 'sodium', 'rule_type' => 'limit', 'threshold' => null, 'unit' => 'mg', 'reason' => 'Sodium restriction is first-line dietary intervention for hypertension'],
            ['condition' => 'hypertension', 'stage' => 'all', 'nutrient_or_food_tag' => 'potassium', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'mg', 'reason' => 'Prefer suitable potassium-containing foods only after renal function and medication review; avoid automatic potassium or salt-substitute advice.'],
            ['condition' => 'hypertension', 'stage' => 'all', 'nutrient_or_food_tag' => 'saturated_fat', 'rule_type' => 'limit', 'threshold' => null, 'unit' => '%kcal', 'reason' => 'Saturated fat contributes to cardiovascular risk'],

            // ── Dyslipidemia ───────────────────────────────────────────────
            ['condition' => 'dyslipidemia', 'stage' => 'all', 'nutrient_or_food_tag' => 'saturated_fat', 'rule_type' => 'limit', 'threshold' => null, 'unit' => '%kcal', 'reason' => 'Saturated fat raises LDL cholesterol'],
            ['condition' => 'dyslipidemia', 'stage' => 'all', 'nutrient_or_food_tag' => 'trans_fat', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'Trans fat raises LDL and lowers HDL'],
            ['condition' => 'dyslipidemia', 'stage' => 'all', 'nutrient_or_food_tag' => 'cholesterol', 'rule_type' => 'limit', 'threshold' => null, 'unit' => 'mg', 'reason' => 'Dietary cholesterol restriction for dyslipidemia management'],
            ['condition' => 'dyslipidemia', 'stage' => 'all', 'nutrient_or_food_tag' => 'omega3', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => null, 'reason' => 'Choose fish within the individual food plan; do not assume omega-3 lowers LDL.'],

            // ── Malnutrition / Hypoalbuminemia ─────────────────────────────
            ['condition' => 'malnutrition', 'stage' => 'all', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'g/kg', 'reason' => 'Meet assessed protein and energy needs; albumin alone does not diagnose malnutrition or measure response.'],
            ['condition' => 'malnutrition', 'stage' => 'all', 'nutrient_or_food_tag' => 'energy', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'kcal/kg', 'reason' => 'Meet the confirmed final energy target; refeeding progression requires clinical supervision.'],

            // ── Liver Disease ──────────────────────────────────────────────
            ['condition' => 'liver_disease', 'stage' => 'all', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => 'g/kg', 'reason' => 'Do not routinely restrict protein in cirrhosis; use the assessed weight and nutritional status in the individual prescription.'],
            ['condition' => 'liver_disease', 'stage' => 'all', 'nutrient_or_food_tag' => 'late_evening_snack', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => null, 'reason' => 'A late-evening snack reduces prolonged overnight fasting and supports muscle preservation.'],
            ['condition' => 'liver_disease', 'stage' => 'all', 'nutrient_or_food_tag' => 'sodium', 'rule_type' => 'limit', 'threshold' => null, 'unit' => 'mg', 'reason' => 'Follow an actual sodium order for ascites; preserve adequate intake.'],

            // ── Gout / Hyperuricemia ───────────────────────────────────────
            ['condition' => 'gout', 'stage' => 'all', 'nutrient_or_food_tag' => 'purine', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'High-purine foods exacerbate hyperuricemia and gout attacks'],
            ['condition' => 'gout', 'stage' => 'all', 'nutrient_or_food_tag' => 'shellfish', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'Shellfish are high in purines'],
            ['condition' => 'gout', 'stage' => 'all', 'nutrient_or_food_tag' => 'organ_meats', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'Organ meats are very high in purines'],
        ];

        $obsoleteRules = [
            ...array_map(fn (string $stage): array => ['condition' => 'CKD', 'stage' => $stage, 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend'], ['stage_1', 'stage_2', 'stage_3', 'stage_4', 'stage_5_predialysis']),
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'simple_sugar', 'rule_type' => 'avoid'],
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'carbs', 'rule_type' => 'limit'],
            ['condition' => 'CKD', 'stage' => 'stage1-2', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'limit'],
            ['condition' => 'CKD', 'stage' => 'stage3-4', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'limit'],
            ['condition' => 'CKD', 'stage' => 'all', 'nutrient_or_food_tag' => 'potassium', 'rule_type' => 'limit'],
            ['condition' => 'CKD', 'stage' => 'all', 'nutrient_or_food_tag' => 'phosphate', 'rule_type' => 'limit'],
            ['condition' => 'CKD', 'stage' => 'all', 'nutrient_or_food_tag' => 'fluid', 'rule_type' => 'limit'],
            ['condition' => 'CKD', 'stage' => 'dialysis', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend'],
            ['condition' => 'liver_disease', 'stage' => 'cirrhosis', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'limit'],
        ];

        DB::transaction(function () use ($obsoleteRules, $rules): void {
            foreach ($obsoleteRules as $identity) {
                ClinicalRule::query()->where($identity)->delete();
            }

            foreach ($rules as $rule) {
                ClinicalRule::updateOrCreate(
                    [
                        'condition' => $rule['condition'],
                        'stage' => $rule['stage'],
                        'nutrient_or_food_tag' => $rule['nutrient_or_food_tag'],
                        'rule_type' => $rule['rule_type'],
                    ],
                    $rule
                );
            }
        });
    }
}
