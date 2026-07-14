<?php

namespace Database\Seeders;

use App\Models\ClinicalRule;
use Illuminate\Database\Seeder;

class ClinicalRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // ── Diabetes Mellitus ──────────────────────────────────────────
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'carbs', 'rule_type' => 'limit', 'threshold' => 180, 'unit' => 'g', 'reason' => 'Carbohydrate restriction for glycemic control in diabetes mellitus'],
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'simple_sugar', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'Simple sugars cause rapid blood glucose spikes'],
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'refined_carbs', 'rule_type' => 'limit', 'threshold' => null, 'unit' => null, 'reason' => 'Refined carbohydrates have high glycemic index'],
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'fiber', 'rule_type' => 'recommend', 'threshold' => 25, 'unit' => 'g', 'reason' => 'Dietary fiber slows glucose absorption'],
            ['condition' => 'DM', 'stage' => 'all', 'nutrient_or_food_tag' => 'saturated_fat', 'rule_type' => 'limit', 'threshold' => 7, 'unit' => '%kcal', 'reason' => 'Saturated fat increases cardiovascular risk in diabetics'],

            // ── Chronic Kidney Disease ─────────────────────────────────────
            ['condition' => 'CKD', 'stage' => 'stage1-2', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'limit', 'threshold' => 0.8, 'unit' => 'g/kg', 'reason' => 'Moderate protein restriction to reduce kidney workload'],
            ['condition' => 'CKD', 'stage' => 'stage3-4', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'limit', 'threshold' => 0.6, 'unit' => 'g/kg', 'reason' => 'Stricter protein restriction in advanced CKD'],
            ['condition' => 'CKD', 'stage' => 'all', 'nutrient_or_food_tag' => 'potassium', 'rule_type' => 'limit', 'threshold' => 2000, 'unit' => 'mg', 'reason' => 'Hyperkalemia risk in CKD — restrict dietary potassium'],
            ['condition' => 'CKD', 'stage' => 'all', 'nutrient_or_food_tag' => 'phosphate', 'rule_type' => 'limit', 'threshold' => 800, 'unit' => 'mg', 'reason' => 'Phosphate restriction to prevent renal osteodystrophy'],
            ['condition' => 'CKD', 'stage' => 'all', 'nutrient_or_food_tag' => 'sodium', 'rule_type' => 'limit', 'threshold' => 2000, 'unit' => 'mg', 'reason' => 'Sodium restriction to control blood pressure and fluid retention'],
            ['condition' => 'CKD', 'stage' => 'all', 'nutrient_or_food_tag' => 'fluid', 'rule_type' => 'limit', 'threshold' => 1500, 'unit' => 'ml', 'reason' => 'Fluid restriction to prevent edema and fluid overload'],
            ['condition' => 'CKD', 'stage' => 'dialysis', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'recommend', 'threshold' => 1.2, 'unit' => 'g/kg', 'reason' => 'Dialysis patients need higher protein to compensate for dialysis losses'],

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
            ['condition' => 'liver_disease', 'stage' => 'cirrhosis', 'nutrient_or_food_tag' => 'protein', 'rule_type' => 'limit', 'threshold' => 1.0, 'unit' => 'g/kg', 'reason' => 'Protein restriction to prevent hepatic encephalopathy in cirrhosis'],
            ['condition' => 'liver_disease', 'stage' => 'all', 'nutrient_or_food_tag' => 'sodium', 'rule_type' => 'limit', 'threshold' => 2000, 'unit' => 'mg', 'reason' => 'Sodium restriction to manage ascites in liver disease'],

            // ── Gout / Hyperuricemia ───────────────────────────────────────
            ['condition' => 'gout', 'stage' => 'all', 'nutrient_or_food_tag' => 'purine', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'High-purine foods exacerbate hyperuricemia and gout attacks'],
            ['condition' => 'gout', 'stage' => 'all', 'nutrient_or_food_tag' => 'shellfish', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'Shellfish are high in purines'],
            ['condition' => 'gout', 'stage' => 'all', 'nutrient_or_food_tag' => 'organ_meats', 'rule_type' => 'avoid', 'threshold' => null, 'unit' => null, 'reason' => 'Organ meats are very high in purines'],
        ];

        foreach ($rules as $rule) {
            ClinicalRule::firstOrCreate(
                [
                    'condition' => $rule['condition'],
                    'stage' => $rule['stage'],
                    'nutrient_or_food_tag' => $rule['nutrient_or_food_tag'],
                    'rule_type' => $rule['rule_type'],
                ],
                $rule
            );
        }
    }
}
