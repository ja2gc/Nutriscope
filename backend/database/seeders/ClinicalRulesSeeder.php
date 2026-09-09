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
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'simple_sugar', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'Minimize free sugars and individualize carbohydrate distribution for the patient’s treatment regimen.'],
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'refined_carbs', 'rule_type' => 'limit', 'threshold' => null, 'unit' => null, 'reason' => 'Prefer higher-fiber carbohydrate sources and individualize portions within the prescription.'],
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'fiber', 'rule_type' => 'recommend', 'threshold' => 25, 'unit' => 'g', 'reason' => 'Dietary fiber supports glycemic control and should be individualized within the prescription.'],
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'saturated_fat', 'rule_type' => 'limit', 'threshold' => 7, 'unit' => '%kcal', 'reason' => 'Limit saturated fat to reduce cardiovascular risk.'],

            // ── Chronic Kidney Disease ─────────────────────────────────────
            ['condition' => 'CKD', 'stage' => 'all', 'nutrient_or_food_tag' => 'sodium', 'rule_type' => 'limit', 'threshold' => 2000, 'unit' => 'mg', 'reason' => 'Sodium restriction to control blood pressure and fluid retention'],
            ['condition' => 'CKD', 'stage' => 'stage_1', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => 0.8, 'unit' => 'g/kg IBW', 'reason' => 'Use the stage-specific protein target calculated in the patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'stage_2', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => 0.8, 'unit' => 'g/kg IBW', 'reason' => 'Use the stage-specific protein target calculated in the patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'stage_3', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => 0.7, 'unit' => 'g/kg IBW', 'reason' => 'Use the stage-specific protein target calculated in the patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'stage_4', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => 0.6, 'unit' => 'g/kg IBW', 'reason' => 'Use the stage-specific protein target calculated in the patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'stage_5_predialysis', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => 0.6, 'unit' => 'g/kg IBW', 'reason' => 'Use the stage-specific protein target calculated in the patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'hemodialysis', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => 1.2, 'unit' => 'g/kg IBW', 'reason' => 'Hemodialysis increases protein needs; use the calculated patient prescription.'],
            ['condition' => 'CKD', 'stage' => 'peritoneal', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => 1.35, 'unit' => 'g/kg IBW', 'reason' => 'Peritoneal dialysis increases protein needs; use the calculated patient prescription.'],

            // ── Hypertension ───────────────────────────────────────────────
            ['condition' => 'hypertension', 'stage' => 'all', 'nutrient_or_food_tag' => 'sodium', 'rule_type' => 'limit', 'threshold' => 1500, 'unit' => 'mg', 'reason' => 'Sodium restriction is first-line dietary intervention for hypertension'],
            ['condition' => 'hypertension', 'stage' => 'all', 'nutrient_or_food_tag' => 'potassium', 'rule_type' => 'recommend', 'threshold' => 4700, 'unit' => 'mg', 'reason' => 'Potassium helps counteract sodium and lower blood pressure'],
            ['condition' => 'hypertension', 'stage' => 'all', 'nutrient_or_food_tag' => 'saturated_fat', 'rule_type' => 'limit', 'threshold' => 7, 'unit' => '%kcal', 'reason' => 'Saturated fat contributes to cardiovascular risk'],

            // ── Dyslipidemia ───────────────────────────────────────────────
            ['condition' => 'dyslipidemia', 'stage' => 'all', 'nutrient_or_food_tag' => 'saturated_fat', 'rule_type' => 'limit', 'threshold' => 7, 'unit' => '%kcal', 'reason' => 'Saturated fat raises LDL cholesterol'],
            ['condition' => 'dyslipidemia', 'stage' => 'all', 'nutrient_or_food_tag' => 'trans_fat', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'Trans fat raises LDL and lowers HDL'],
            ['condition' => 'dyslipidemia', 'stage' => 'all', 'nutrient_or_food_tag' => 'cholesterol', 'rule_type' => 'limit', 'threshold' => 200, 'unit' => 'mg', 'reason' => 'Dietary cholesterol restriction for dyslipidemia management'],
            ['condition' => 'dyslipidemia', 'stage' => 'all', 'nutrient_or_food_tag' => 'omega3', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => null, 'reason' => 'Omega-3 fatty acids help lower triglycerides and LDL'],

            // ── Malnutrition / Hypoalbuminemia ─────────────────────────────
            ['condition' => 'malnutrition', 'stage' => 'all', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => 1.5, 'unit' => 'g/kg', 'reason' => 'High-protein intake to restore lean body mass and albumin levels'],
            ['condition' => 'malnutrition', 'stage' => 'all', 'nutrient_or_food_tag' => 'energy', 'rule_type' => 'recommend', 'threshold' => 35, 'unit' => 'kcal/kg', 'reason' => 'Hypercaloric diet to replenish energy stores'],

            // ── Liver Disease ──────────────────────────────────────────────
            ['condition' => 'liver_disease', 'stage' => 'all', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => 1.2, 'unit' => 'g/kg IBW', 'reason' => 'Protein should not be restricted routinely in liver disease; target 1.2–1.5 g/kg IBW within the patient prescription.'],
            ['condition' => 'liver_disease', 'stage' => 'all', 'nutrient_or_food_tag' => 'late_evening_snack', 'rule_type' => 'recommend', 'threshold' => null, 'unit' => null, 'reason' => 'A late-evening snack reduces prolonged overnight fasting and supports muscle preservation.'],
            ['condition' => 'liver_disease', 'stage' => 'all', 'nutrient_or_food_tag' => 'sodium', 'rule_type' => 'limit', 'threshold' => 2000, 'unit' => 'mg', 'reason' => 'Sodium restriction to manage ascites in liver disease'],

            // ── Gout / Hyperuricemia ───────────────────────────────────────
            ['condition' => 'gout', 'stage' => 'all', 'nutrient_or_food_tag' => 'purine', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'High-purine foods exacerbate hyperuricemia and gout attacks'],
            ['condition' => 'gout', 'stage' => 'all', 'nutrient_or_food_tag' => 'shellfish', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'Shellfish are high in purines'],
            ['condition' => 'gout', 'stage' => 'all', 'nutrient_or_food_tag' => 'organ_meats', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'Organ meats are very high in purines'],
        ];

        $obsoleteRules = [
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
