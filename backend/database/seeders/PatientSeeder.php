<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\BiochemicalData;
use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ScreeningDocument;
use App\Models\User;
use App\Services\RiskScoreCalculator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds 2 demo NCP patients for Nutriscope with full A + D + I + biochemical data.
 *
 * Patient 1 — Maria Santos (T2DM + Hypertension, Active/Monitored)
 *   Full A + D + I, biochemical data with abnormal glucose/HbA1c/lipids.
 *   2 monitoring follow-up visits showing glycemic improvement trend.
 *
 * Patient 2 — Roberto Reyes (Moderate Malnutrition, Active — first session done)
 *   Full A + D + I, biochemical data with low albumin/hemoglobin/potassium.
 *   No monitoring entries yet — follow-up scheduled in 2 weeks.
 *
 * Always deletes and re-seeds both patients for a clean, consistent demo state.
 */
class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $rnd = User::where('role', 'RND')->first();
        if (! $rnd) {
            $this->command->warn('PatientSeeder: No RND user found. Run AdminUserSeeder first.');
            return;
        }

        $this->cleanupPatient('Maria Santos');
        $this->cleanupPatient('Roberto Reyes');

        $this->seedMariaSantos($rnd->id);
        $this->seedRobertoReyes($rnd->id);
    }

    // ── Cleanup helper ────────────────────────────────────────────────────────

    private function cleanupPatient(string $name): void
    {
        $patient = Patient::where('name', $name)->first();
        if (! $patient) {
            return;
        }

        // Delete screening documents (has nullable assessment_id FK)
        ScreeningDocument::where('patient_id', $patient->id)->delete();

        foreach ($patient->ncpRecords as $record) {
            $record->monitorings()->delete();
            $record->diagnoses()->delete();
            $record->intervention?->delete();
            if ($assessment = $record->assessment) {
                $assessment->biochemicalData?->delete();
                $assessment->delete();
            }
            $record->delete();
        }

        $patient->delete();

        $this->command->line("  PatientSeeder: {$name} — existing record removed.");
    }

    // ── Patient 1: Maria Santos — T2DM + Hypertension, 2 follow-ups ──────────

    private function seedMariaSantos(int $rndId): void
    {
        // Patient demographics
        $patient = Patient::create([
            'name'              => 'Maria Santos',
            'dob'               => '1975-03-15',        // 51 yrs old
            'sex'               => 'Female',
            'religion'          => 'Roman Catholic',
            'address'           => 'Brgy. San Nicolas, San Fernando, Pampanga',
            'contact'           => '09171234567',
            'physician'         => 'Dr. Juan dela Cruz',
            'admission_date'    => '2026-05-20',
            'medical_diagnosis' => 'Type 2 Diabetes Mellitus with Hypertension',
            'ward'              => 'Ward 3 — Internal Medicine',
            'status'            => 'Active',
            'screening_type'    => 'adult',
            'hospital_number'   => 'HN-2026-0042',
            'age_group_category'=> 'adult',
        ]);

        // NCP Record — active (2 monitoring follow-ups done, ongoing)
        $record = NcpRecord::create([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $rndId,
            'type'        => 'new',
            'status'      => 'active',
        ]);

        // Assessment
        // Anthro: wt 68 kg, ht 155 cm → BMI 28.3 (Overweight)
        // IBW (Hamwi female): 45.5 + 2.2×(155-152.4)/2.54 ≈ 47.7 kg → %IBW ≈ 142.6%
        // BMR (Mifflin): 10×68 + 6.25×155 − 5×51 − 161 = 1221 kcal → TEE(sedentary) ≈ 1465 kcal
        $assessment = Assessment::create([
            'ncp_record_id'                  => $record->id,
            'religion'                       => 'Roman Catholic',
            // Dietary
            'dietary_intake_method'          => '24_hour_recall',
            'dietary_intake'                 => 'Breakfast: 2 cups steamed white rice, 1 fried egg, instant coffee with sugar. '
                                               . 'Lunch: 2 cups white rice, pork adobo (150 g), softdrink. '
                                               . 'Dinner: 1.5 cups rice, beef sinigang, rice crackers. '
                                               . 'Snacks: pandesal ×2, sweetened condensed milk beverage. '
                                               . 'Estimated total: ~2,400 kcal, ~280 g carbs, high glycemic load.',
            'appetite_changes'               => 'No significant changes in appetite. Patient reports feeling hungry frequently.',
            'dietary_restrictions'           => 'Patient is aware of diabetic diet but not consistently following. Reports difficulty avoiding sweet foods and white rice.',
            'supplements'                    => 'Metformin 500 mg BID (prescribed). No nutritional supplements currently.',
            'knowledge_notes'                => 'Patient has basic awareness of carbohydrate counting but lacks understanding of glycemic index. Motivated to change diet after recent HbA1c result of 8.4%.',
            'nutrient_drug_interaction'      => 'Metformin: may reduce Vitamin B12 absorption with long-term use. Monitor B12 levels annually.',
            // Anthropometric
            'weight'                         => 68.00,
            'height'                         => 155.00,
            'bmi'                            => 28.30,
            'usual_weight'                   => 71.00,
            'weight_loss_percentage'         => 4.23,
            'weight_loss_period'             => '3 months',
            'functional_assessment'          => 'Ambulatory',
            'energy_intake_status'           => 'Sub-optimal',
            'ibw_percentage'                 => 142.60,
            'physical_activity_level'        => 'sedentary',
            'muac_mm'                        => 285.0,
            'waist_cm'                       => 92.0,
            'hip_cm'                         => 100.0,
            'stress_factor'                  => null,
            'edema_present'                  => false,
            'pregnancy_lactation_status'     => 'none',
            'nutritional_status'             => null,  // computed below
            // Client history
            'medical_history'                => 'Type 2 Diabetes Mellitus (diagnosed 2019, HbA1c 8.4%). '
                                               . 'Hypertension Stage 1 (BP 138/88 mmHg on admission). '
                                               . 'No prior hospitalizations. Family history: mother with T2DM.',
            'social_history'                 => 'Married, 3 children. Housewife. Non-smoker, non-drinker. '
                                               . 'Lives with family in San Fernando. Moderate activity at home.',
            'lifestyle'                      => 'Sedentary — primarily home-based. Limited structured exercise. '
                                               . 'Walks ~10 minutes daily to market.',
            'allergies'                      => ['shellfish'],
            'food_dislikes'                  => ['bitter melon', 'sardines'],
            'medications'                    => ['Metformin 500 mg BID', 'Amlodipine 5 mg OD'],
            'chewing_swallowing_difficulties'=> null,
            'constipation'                   => 'Occasional — 1–2×/week hard stools. Likely low fiber intake.',
            'diarrhea_notes'                 => null,
            'present_diet'                   => 'Regular Filipino diet. High refined carbohydrate intake. Frequent sweet beverages. Limited vegetable and fiber intake.',
            'rnd_summary'                    => 'Maria is a 51-year-old female with T2DM and hypertension, currently overweight with poor glycemic control (HbA1c 8.4%). '
                                               . 'Diet assessment reveals excessive refined carbohydrate intake, high glycemic load, and inadequate fiber. '
                                               . 'She is motivated for change. Priority: carbohydrate distribution, fiber increase, and sodium reduction.',
        ]);

        // Biochemical data — T2DM profile: elevated glucose, HbA1c, LDL, cholesterol; BP string
        // Flagged by LabFlagService: glucose HIGH (>99), hba1c HIGH (>5.6), cholesterol HIGH (>200), ldl HIGH (>100)
        BiochemicalData::create([
            'assessment_id' => $assessment->id,
            'glucose'       => 145.00,   // HIGH (normal 70–99 mg/dL)
            'hba1c'         => 8.40,     // HIGH (normal ≤5.6%)
            'cholesterol'   => 218.00,   // HIGH (normal ≤200 mg/dL)
            'ldl'           => 125.00,   // HIGH (normal ≤100 mg/dL)
            'hdl'           => 48.00,    // LOW for female (normal ≥50 mg/dL)
            'triglycerides' => 168.00,   // HIGH (normal ≤150 mg/dL)
            'albumin'       => 3.80,     // normal (3.5–5.5 g/dL)
            'hemoglobin'    => 13.20,    // normal female (12.0–15.5 g/dL)
            'potassium'     => 4.20,     // normal (3.5–5.1 mEq/L)
            'sodium'        => 140.00,   // normal (136–145 mEq/L)
            'creatinine'    => 0.72,     // normal female (0.5–0.9 mg/dL)
            'bp'            => '138/88', // string field — hypertension stage 1
        ]);

        // Compute risk score
        $assessment->setRelation('ncpRecord', $record->setRelation('patient', $patient));
        $riskResult = resolve(RiskScoreCalculator::class)->calculate($assessment);
        $record->update(['risk_score' => $riskResult['score']]);
        $assessment->update(['nutritional_status' => $riskResult['nutritional_status']]);

        // Diagnosis — NI-5.8.2 Carbohydrate intake inconsistency
        $pes = Diagnosis::buildPes(
            'Inconsistent carbohydrate intake',
            'limited knowledge of glycemic index and carbohydrate counting',
            'HbA1c of 8.4%, dietary recall showing high refined carbohydrate and sugar-sweetened beverage intake (~280 g carbs/day vs target 150–165 g/day), and patient-reported difficulty avoiding sweet foods'
        );
        Diagnosis::create([
            'ncp_record_id' => $record->id,
            'domain'        => 'NI',
            'problem'       => 'Inconsistent carbohydrate intake',
            'label'         => 'NI-5.8.2',
            'etiology'      => 'Limited knowledge of glycemic index and carbohydrate counting',
            'signs_symptoms'=> 'HbA1c of 8.4%, dietary recall showing high refined carbohydrate and sugar-sweetened beverage intake (~280 g carbs/day vs target 150–165 g/day), and patient-reported difficulty avoiding sweet foods',
            'pes_statement' => $pes,
            'extra_notes'   => 'Secondary: Excess sodium intake (NI-5.10.2) related to frequent salty condiment use (patis, toyo) as evidenced by BP 138/88 mmHg. Secondary: Dyslipidemia — elevated LDL 125 and cholesterol 218 consistent with diabetic dyslipidemia.',
            'ai_generated'  => false,
        ]);

        // Intervention — Diabetic Control
        Intervention::create([
            'ncp_record_id'    => $record->id,
            'goal_type'        => 'diabetic_control',
            'disease_stage'    => 'stage_1',
            'energy_kcal'      => 1600.00,
            'protein_g'        => 70.00,
            'carbs_g'          => 200.00,
            'fat_g'            => 53.00,
            'fluid_ml'         => 1800.00,
            'micronutrient_limits' => [
                'sodium' => ['max' => 2000, 'unit' => 'mg'],
            ],
            'displayed_nutrients' => ['fiber', 'sodium', 'free_sugars'],
            'session_type'     => 'initial',
            'next_followup_date' => '2026-06-17',
            'education_notes'  => "NUTRITION EDUCATION — DIABETIC MEAL PLANNING\n\n"
                                . "1. How Food Affects Blood Sugar\n"
                                . "   Carbohydrates raise blood sugar most. The goal is consistent, controlled carb intake — not elimination.\n\n"
                                . "2. Carbohydrate Counting\n"
                                . "   Target: 150–165 g carbs/day distributed across 3 meals and 1–2 snacks.\n"
                                . "   Examples: 1 cup cooked rice = ~45 g carbs; 1 slice white bread = ~15 g carbs.\n\n"
                                . "3. The Plate Method\n"
                                . "   ½ plate: non-starchy vegetables (kangkong, ampalaya, pechay)\n"
                                . "   ¼ plate: lean protein (fish, chicken, tofu)\n"
                                . "   ¼ plate: complex carbs (brown rice, oatmeal)\n\n"
                                . "4. Foods to Minimize\n"
                                . "   Sugary drinks, white rice (large portions), sweets, fried foods, patis and toyo (high sodium).\n\n"
                                . "5. Timing Matters\n"
                                . "   Eat at regular intervals. Never skip meals — this causes blood sugar swings.\n\n"
                                . "Next session: Review blood glucose log, HbA1c trend, and medication timing with meals.",
            'counseling_goals' => "1. Reduce daily carbohydrate intake from ~280 g to 150–165 g/day within 4 weeks.\n"
                                . "2. Eliminate sugar-sweetened beverages — replace with water, unsweetened tea, or calamansi water.\n"
                                . "3. Incorporate at least 25 g fiber/day through vegetables and whole grains.\n"
                                . "4. Reduce sodium intake to ≤2,000 mg/day.",
            'barriers'         => "1. Cultural preference for white rice — family meals centered around high-carb dishes.\n"
                                . "2. Limited budget — brown rice and whole grains are perceived as expensive.\n"
                                . "3. Sweet food cravings — patient reports difficulty resisting sweet snacks at home.\n"
                                . "4. Limited cooking skills for low-GI meal preparation.",
            'strategies'       => "1. Plate method visual guide provided — practice replacing ¼ of rice portion with vegetables.\n"
                                . "2. Gradual rice reduction: mix ¼ brown rice with ¾ white rice as first step.\n"
                                . "3. Sweet craving alternatives: fresh mango or papaya instead of sweetened beverages.\n"
                                . "4. Filipino recipe modifications: less sugar in Tinola, less rice per serving in Adobo.\n"
                                . "5. Sodium reduction: replace patis with calamansi and herbs.",
        ]);

        // ── Monitoring — Follow-up 1 (4 weeks after admission, 2026-06-17) ─────
        // Labs trending down: glucose 145→128, HbA1c 8.4→8.0, cholesterol 218→205, LDL 125→112
        $m1 = Monitoring::create([
            'ncp_record_id'       => $record->id,
            'weight'              => 67.20,   // -0.8 kg from baseline
            'bmi'                 => 27.97,
            'lab_values'          => [
                'glucose'       => 128.0,    // improving but still HIGH
                'hba1c'         => 8.0,      // improving but still HIGH
                'cholesterol'   => 205.0,    // still slightly HIGH
                'ldl'           => 112.0,    // still HIGH
                'hdl'           => 49.0,     // still LOW female
                'triglycerides' => 155.0,    // still slightly HIGH
                'albumin'       => 3.9,      // normal, stable
                'potassium'     => 4.1,
                'sodium'        => 139.0,
            ],
            'intake_notes'        => 'Patient reports reducing rice portions by approx ¼. Eliminating softdrinks mostly successful — drinking more water and calamansi juice. Still having pandesal 1–2×/week. Vegetables added at lunch (pechay/kangkong). Eating 3 meals/day consistently.',
            'symptoms'            => 'Mild afternoon fatigue — improving. No polyuria, polydipsia, or new complaints.',
            'goal_achievement'    => [
                'carb_reduction'            => 'partial',
                'sweet_beverage_elimination'=> 'mostly_achieved',
                'fiber_goal'                => 'not_achieved',
                'sodium_reduction'          => 'partial',
            ],
            'clinical_summary'    => 'Glycemic control improving. Fasting glucose dropped 145→128 mg/dL. HbA1c reduced 8.4→8.0%. Weight loss 0.8 kg over 4 weeks (on track). Cholesterol and LDL still elevated but trending down. Continue current plan. Reinforce plate method and daily fiber targets (vegetables at every meal).',
            'next_monitoring_date'=> '2026-07-15',
        ]);
        DB::table('monitorings')->where('id', $m1->id)->update([
            'created_at' => '2026-06-17 09:00:00',
            'updated_at' => '2026-06-17 09:00:00',
        ]);

        // ── Monitoring — Follow-up 2 (8 weeks after admission, 2026-07-15) ─────
        // Labs trending toward normal: glucose 128→106, HbA1c 8.0→7.4, cholesterol 205→193, LDL 112→94
        $m2 = Monitoring::create([
            'ncp_record_id'       => $record->id,
            'weight'              => 66.10,   // -1.9 kg total from baseline
            'bmi'                 => 27.52,
            'lab_values'          => [
                'glucose'       => 106.0,    // still slightly HIGH (>99) but approaching normal
                'hba1c'         => 7.4,      // HIGH but significantly improved
                'cholesterol'   => 193.0,    // normalized (≤200)
                'ldl'           => 94.0,     // normalized (≤100)
                'hdl'           => 51.0,     // normalized female (≥50)
                'triglycerides' => 142.0,    // normalized (≤150)
                'albumin'       => 4.0,      // normal, stable
                'potassium'     => 4.3,
                'sodium'        => 138.0,
            ],
            'intake_notes'        => 'Consistent with plate method for 3 weeks. Brown rice mixed with white 4×/week. Vegetable intake improved to 2–3 servings/day. Sweet cravings managed with fresh papaya and mango. No sweetened beverages for past 3 weeks. Eating at regular intervals 3 meals + 1 snack.',
            'symptoms'            => 'No symptoms. Energy levels significantly improved. Walking 20–30 minutes daily. Reports feeling less fatigued after meals.',
            'goal_achievement'    => [
                'carb_reduction'            => 'achieved',
                'sweet_beverage_elimination'=> 'achieved',
                'fiber_goal'                => 'partially_achieved',
                'sodium_reduction'          => 'partially_achieved',
                'weight_loss_target'        => 'on_track',
            ],
            'clinical_summary'    => 'Significant glycemic improvement. Glucose 106 mg/dL (near-normal range). HbA1c 7.4% — good progress toward target <7%. Total cholesterol and LDL normalized. HDL improved. Weight reduced 1.9 kg over 8 weeks. Patient highly adherent and motivated. Plan: continue current diet, add 5-min post-meal walks. Next HbA1c lab in 6 weeks.',
            'next_monitoring_date'=> '2026-08-12',
        ]);
        DB::table('monitorings')->where('id', $m2->id)->update([
            'created_at' => '2026-07-15 09:00:00',
            'updated_at' => '2026-07-15 09:00:00',
        ]);

        $this->command->info('  PatientSeeder: Maria Santos seeded (active, 2 monitoring follow-ups).');
    }

    // ── Patient 2: Roberto Reyes — Moderate Malnutrition, Active, first session ─

    private function seedRobertoReyes(int $rndId): void
    {
        $patient = Patient::create([
            'name'              => 'Roberto Reyes',
            'dob'               => '1988-06-22',        // 37 yrs old
            'sex'               => 'Male',
            'religion'          => 'Roman Catholic',
            'address'           => 'Brgy. Sto. Rosario, Angeles City, Pampanga',
            'contact'           => '09281234567',
            'physician'         => 'Dr. Ana Gonzales',
            'admission_date'    => '2026-06-01',
            'medical_diagnosis' => 'Moderate Protein-Energy Malnutrition secondary to poor oral intake',
            'ward'              => 'Ward 1 — General Medicine',
            'status'            => 'Active',
            'screening_type'    => 'adult',
            'hospital_number'   => 'HN-2026-0078',
            'age_group_category'=> 'adult',
        ]);

        $record = NcpRecord::create([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $rndId,
            'type'        => 'new',
            'status'      => 'active',
        ]);

        // Assessment
        // Anthro: wt 52 kg, ht 170 cm → BMI 18.0 (Underweight)
        // IBW (Hamwi male): 48 + 2.7×(170-152.4)/2.54 ≈ 66.7 kg → %IBW ≈ 78.0%
        // BMR (Mifflin): 10×52 + 6.25×170 − 5×37 + 5 = 1402.5 kcal → TEE(light) ≈ 1928 kcal
        $assessment = Assessment::create([
            'ncp_record_id'                  => $record->id,
            'religion'                       => 'Roman Catholic',
            'dietary_intake_method'          => '24_hour_recall',
            'dietary_intake'                 => 'Breakfast: 1 cup lugaw (plain, no toppings). '
                                               . 'Lunch: ½ cup white rice, ½ cup monggo soup — patient reports nausea, ate only half. '
                                               . 'Dinner: Refused dinner — fatigue and decreased appetite. '
                                               . 'Estimated total: ~550 kcal, ~18 g protein. '
                                               . 'Pre-admission: irregular meals, skipping lunch and dinner for 3 weeks due to work stress.',
            'appetite_changes'               => 'Markedly decreased appetite for 3 weeks prior to admission. Nausea present especially in the morning. Reports eating only 1–2 small meals per day.',
            'dietary_restrictions'           => 'No known food restrictions. Reports avoiding meat due to perceived expense. Diet predominantly rice and instant noodles.',
            'supplements'                    => 'None. Prescribed: Thiamine 100 mg OD (pre-refeeding protocol), Multivitamin OD.',
            'knowledge_notes'                => 'Patient has minimal nutrition knowledge. Unaware of protein and energy requirements. Receptive to education — expressed willingness to change.',
            'nutrient_drug_interaction'      => 'Thiamine supplementation prescribed before refeeding to prevent refeeding syndrome.',
            'weight'                         => 52.00,
            'height'                         => 170.00,
            'bmi'                            => 18.00,
            'usual_weight'                   => 63.00,
            'weight_loss_percentage'         => 17.46,
            'weight_loss_period'             => '3 weeks',
            'functional_assessment'          => 'Ambulatory',
            'energy_intake_status'           => 'Poor intake prior to admission',
            'ibw_percentage'                 => 78.00,
            'physical_activity_level'        => 'light',
            'muac_mm'                        => 215.0,
            'waist_cm'                       => 74.0,
            'hip_cm'                         => 88.0,
            'stress_factor'                  => null,
            'edema_present'                  => false,
            'pregnancy_lactation_status'     => 'none',
            'nutritional_status'             => null,  // computed below
            'medical_history'                => 'No prior chronic illness. No hospitalizations. '
                                               . 'History of loose stools for past 2 weeks (3–4×/day, non-bloody). '
                                               . 'Reports 11 kg unintentional weight loss over 3 weeks.',
            'social_history'                 => 'Single, lives alone in rented room in Angeles City. '
                                               . 'Construction worker — currently unemployed due to project pause (3 weeks). '
                                               . 'Non-smoker, social drinker (beer 2–3×/week). No illicit drug use.',
            'lifestyle'                      => 'Physically active prior to current illness. Now ambulatory but weak. '
                                               . 'Limited cooking facilities — relies on turo-turo (carinderias) for food.',
            'allergies'                      => [],
            'food_dislikes'                  => ['liver', 'malunggay'],
            'medications'                    => ['Thiamine 100 mg OD', 'Multivitamin OD', 'ORS PRN'],
            'chewing_swallowing_difficulties'=> null,
            'constipation'                   => null,
            'diarrhea_notes'                 => 'Loose stools 3–4×/day for 2 weeks prior to admission. Now resolving on Day 3. Monitor for electrolyte losses (K+, Mg2+, phosphate).',
            'present_diet'                   => 'Soft diet — progressing from lugaw to regular texture as tolerated. '
                                               . 'Start with 3–4 small frequent meals at 400–500 kcal each.',
            'rnd_summary'                    => 'Roberto is a 37-year-old male with moderate protein-energy malnutrition (BMI 18.0, %IBW 78%, 17.5% weight loss over 3 weeks). '
                                               . 'Contributing factors: inadequate intake secondary to financial stress, food insecurity, and recent diarrheal illness. '
                                               . 'Thiamine supplementation initiated pre-refeeding. Progressive caloric build-up plan required. '
                                               . 'High nutritional risk — priority admission. Follow-up in 2 weeks to assess weight gain and tolerance.',
        ]);

        // Biochemical data — Malnutrition profile: low albumin, hemoglobin, potassium, phosphate
        // Flagged by LabFlagService: albumin LOW (<3.5), hemoglobin LOW (male <13.5), potassium LOW (<3.5), phosphate LOW (<2.5)
        BiochemicalData::create([
            'assessment_id' => $assessment->id,
            'albumin'       => 2.80,    // LOW (normal 3.5–5.5 g/dL) — protein depletion
            'hemoglobin'    => 10.50,   // LOW male (normal 13.5–17.5 g/dL) — nutritional anemia
            'hematocrit'    => 32.00,   // LOW male (normal 41–53%) — corresponds to Hgb
            'potassium'     => 3.10,    // LOW (normal 3.5–5.1 mEq/L) — from diarrheal losses
            'phosphate'     => 2.20,    // LOW (normal 2.5–4.5 mg/dL) — refeeding syndrome risk
            'sodium'        => 138.00,  // normal (136–145 mEq/L)
            'glucose'       => 82.00,   // normal (70–99 mg/dL)
            'creatinine'    => 0.85,    // normal male (0.7–1.2 mg/dL)
            'bun'           => 14.00,   // normal (7–18 mg/dL)
            'calcium'       => 8.50,    // borderline LOW (normal 8.7–10.3 mg/dL) — diarrhea losses
        ]);

        // Compute risk score
        $assessment->setRelation('ncpRecord', $record->setRelation('patient', $patient));
        $riskResult = resolve(RiskScoreCalculator::class)->calculate($assessment);
        $record->update(['risk_score' => $riskResult['score']]);
        $assessment->update(['nutritional_status' => $riskResult['nutritional_status']]);

        // Diagnosis — NC-3.1 Underweight / Malnutrition
        $pes = Diagnosis::buildPes(
            'Moderate protein-energy malnutrition',
            'severely inadequate dietary intake secondary to food insecurity and diarrheal illness',
            'BMI 18.0 kg/m², %IBW 78%, 17.5% unintentional weight loss over 3 weeks, MUAC 215 mm (borderline MAM), albumin 2.8 g/dL (low), hemoglobin 10.5 g/dL (low), 24-hour recall estimated at ~550 kcal and ~18 g protein'
        );
        Diagnosis::create([
            'ncp_record_id' => $record->id,
            'domain'        => 'NC',
            'problem'       => 'Moderate protein-energy malnutrition',
            'label'         => 'NC-3.1',
            'etiology'      => 'Severely inadequate dietary intake secondary to food insecurity and diarrheal illness',
            'signs_symptoms'=> 'BMI 18.0 kg/m², %IBW 78%, 17.5% unintentional weight loss over 3 weeks, MUAC 215 mm (borderline MAM), albumin 2.8 g/dL (low), hemoglobin 10.5 g/dL (low), 24-hour recall estimated at ~550 kcal and ~18 g protein',
            'pes_statement' => $pes,
            'extra_notes'   => 'Risk of refeeding syndrome — thiamine supplementation initiated. Gradual caloric increase per protocol. Monitor electrolytes (phosphate, K+, Mg2+) on Day 3 and Day 5 post-refeeding. Nutritional anemia confirmed — hemoglobin 10.5 g/dL, hematocrit 32%.',
            'ai_generated'  => false,
        ]);

        // Intervention — Malnutrition / Nutritional Rehabilitation
        // Start: 20 kcal/kg = 52×20 = 1040 kcal (refeeding caution)
        // Target by Week 2: 35 kcal/kg = 52×35 = 1820 kcal
        // Protein: 1.5 g/kg = 52×1.5 = 78 g
        Intervention::create([
            'ncp_record_id'    => $record->id,
            'goal_type'        => 'malnutrition',
            'disease_stage'    => 'moderate',
            'energy_kcal'      => 1300.00,
            'protein_g'        => 78.00,
            'carbs_g'          => 178.00,
            'fat_g'            => 43.00,
            'fluid_ml'         => 2000.00,
            'micronutrient_limits' => null,
            'displayed_nutrients' => [],
            'session_type'     => 'initial',
            'next_followup_date' => Carbon::parse('2026-06-23')->addDays(14)->toDateString(),
            'education_notes'  => "NUTRITION EDUCATION — NUTRITIONAL REHABILITATION\n\n"
                                . "1. Your Current Status\n"
                                . "   You have moderate protein-energy malnutrition. "
                                . "Your body has not been getting enough food and protein to maintain muscle and immune function.\n\n"
                                . "2. Feeding Plan — Start Slowly\n"
                                . "   Week 1: ~1,300 kcal/day in 4–5 small meals\n"
                                . "   Week 2: Increase to ~1,800 kcal/day as tolerated\n"
                                . "   Week 3+: Progress toward full target based on weight gain and tolerance\n\n"
                                . "3. Thiamine First (Refeeding Safety)\n"
                                . "   You are receiving Vitamin B1 (Thiamine) before increasing food intake. "
                                . "This prevents a serious complication called refeeding syndrome.\n\n"
                                . "4. Warning Signs — Tell your nurse or RND immediately\n"
                                . "   Muscle cramps, numbness/tingling in hands or feet, fast heartbeat, unusual weakness, swelling in legs. "
                                . "These may signal dangerous electrolyte shifts.\n\n"
                                . "5. Food Priorities\n"
                                . "   Protein at every meal: eggs, chicken, fish, tokwa, monggo.\n"
                                . "   Energy-dense foods: lugaw with egg, peanut butter pandesal, evaporated milk.\n\n"
                                . "Next session (2 weeks): Weight check, electrolyte labs review, food tolerance assessment, diet upgrade.",
            'counseling_goals' => "1. Achieve minimum 0.5 kg weight gain per week over the next 2 weeks.\n"
                                . "2. Tolerate full soft diet (4 meals + 2 snacks) without nausea or diarrhea by Day 7.\n"
                                . "3. Understand and report warning signs of refeeding syndrome.\n"
                                . "4. Progress energy intake from 1,300 to 1,800 kcal/day by Week 2.",
            'barriers'         => "1. Financial constraint — primary barrier to adequate food intake.\n"
                                . "2. Lives alone with no support system for meal preparation.\n"
                                . "3. Nausea and early satiety limiting meal portion tolerance.\n"
                                . "4. Prior diarrheal illness may have compromised nutrient absorption.",
            'strategies'       => "1. Refer to Medical Social Worker for financial assistance and food support programs.\n"
                                . "2. Provide list of affordable high-protein Filipino foods (eggs, tokwa, monggo, sardines).\n"
                                . "3. Small frequent meals every 3–4 hours — avoid large portions that trigger nausea.\n"
                                . "4. Fortify lugaw with egg, evaporated milk, or peanut butter to increase energy density.\n"
                                . "5. Electrolyte monitoring (phosphate, K+, Mg2+) on Day 3 and Day 5 post-refeeding start.",
        ]);

        // No monitoring entries — first session complete, follow-up pending in 2 weeks.

        $this->command->info('  PatientSeeder: Roberto Reyes seeded (active, first session, no monitoring yet).');
    }
}
