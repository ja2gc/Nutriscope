<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\RiskScoreCalculator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds 2 demo NCP patients for Nutriscope:
 *
 * Patient 1 — Maria Santos (Completed first session)
 *   Type 2 Diabetes Mellitus + Hypertension
 *   Full A + D + I recorded. NCP record status = "completed".
 *   No follow-up pending — represents a patient who finished initial counseling.
 *
 * Patient 2 — Roberto Reyes (Active, follow-up scheduled)
 *   Moderate Malnutrition secondary to poor oral intake
 *   Full A + D + I recorded. NCP record status = "active".
 *   next_followup_date is set 2 weeks out — follow-up session pending.
 *
 * Idempotent: skips creation if patient name already exists.
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

        $this->seedMariaSantos($rnd->id);
        $this->seedRobertoReyes($rnd->id);
    }

    // ── Patient 1: Maria Santos — T2DM + Hypertension, Completed ─────────────

    private function seedMariaSantos(int $rndId): void
    {
        if (Patient::where('name', 'Maria Santos')->exists()) {
            $this->command->line('  PatientSeeder: Maria Santos already exists — skipped.');
            return;
        }

        // Patient demographics
        $patient = Patient::create([
            'name'              => 'Maria Santos',
            'dob'               => '1975-03-15',        // 50 yrs old
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

        // NCP Record — first session, completed
        $record = NcpRecord::create([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $rndId,
            'type'        => 'new',
            'status'      => 'completed',
        ]);

        // Assessment
        // Anthro: wt 68 kg, ht 155 cm → BMI 28.3 (Overweight)
        // IBW (Hamwi female): 155 cm = 61.02 in; 45.5 + 2.2×1.02 ≈ 47.7 kg
        // %IBW: 68/47.7 × 100 ≈ 142.6% → Obese
        // BMR (Mifflin-St Jeor): 10×68 + 6.25×155 − 5×50 − 161 = 1238 kcal
        // PAL: sedentary (1.2) → TEE ≈ 1485 kcal
        Assessment::create([
            'ncp_record_id'                  => $record->id,
            // Dietary
            'dietary_intake_method'          => '24_hour_recall',
            'dietary_intake'                 => 'Breakfast: 2 cups steamed white rice, 1 fried egg, instant coffee with sugar. '
                                               . 'Lunch: 2 cups white rice, pork adobo (150g), softdrink. '
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
            'nutritional_status'             => null,  // computed below by RiskScoreCalculator
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
            'rnd_summary'                    => 'Maria is a 50-year-old female with T2DM and hypertension, currently overweight with poor glycemic control (HbA1c 8.4%). '
                                               . 'Diet assessment reveals excessive refined carbohydrate intake, high glycemic load, and inadequate fiber. '
                                               . 'She is motivated for change. Priority: carbohydrate distribution, fiber increase, and sodium reduction.',
        ]);

        // Compute risk score from seeded assessment data so header badge and panel are consistent.
        $mariaAssessment = Assessment::where('ncp_record_id', $record->id)->first();
        $mariaAssessment->setRelation('ncpRecord', $record->setRelation('patient', $patient));
        $riskResult = resolve(RiskScoreCalculator::class)->calculate($mariaAssessment);
        $record->update(['risk_score' => $riskResult['score']]);
        $mariaAssessment->update(['nutritional_status' => $riskResult['nutritional_status']]);

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
            'extra_notes'   => 'Secondary diagnosis: Excess sodium intake (NI-5.10.2) related to frequent use of salty condiments (patis, toyo) as evidenced by BP 138/88 mmHg and reported high condiment use.',
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
            // Micronutrient keys only (must match ALL_MICROS / GOAL_MICRO_FLAGS) — never macros.
            'displayed_nutrients' => ['fiber', 'sodium', 'free_sugars'],
            'session_type'     => 'initial',
            'next_followup_date' => null,   // completed — no follow-up scheduled
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
                                . "   Sugary drinks, white bread, white rice (large portions), sweets, fried foods, patis and toyo (high sodium).\n\n"
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

        $this->command->info('  PatientSeeder: Maria Santos seeded (completed session).');
    }

    // ── Patient 2: Roberto Reyes — Moderate Malnutrition, Active/Follow-up ───

    private function seedRobertoReyes(int $rndId): void
    {
        if (Patient::where('name', 'Roberto Reyes')->exists()) {
            $this->command->line('  PatientSeeder: Roberto Reyes already exists — skipped.');
            return;
        }

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
            'status'      => 'active',      // first session complete, follow-up pending
        ]);

        // Assessment
        // Anthro: wt 52 kg, ht 170 cm → BMI 18.0 (Underweight / mild malnutrition borderline)
        // IBW (Hamwi male): 170 cm = 66.93 in; 48 + 2.7×6.93 ≈ 66.7 kg
        // %IBW: 52/66.7 × 100 ≈ 78.0% → Moderate malnutrition
        // BMR: 10×52 + 6.25×170 − 5×37 + 5 = 1402.5 kcal
        // PAL: light (1.375) ambulatory → TEE ≈ 1928 kcal
        Assessment::create([
            'ncp_record_id'                  => $record->id,
            'dietary_intake_method'          => '24_hour_recall',
            'dietary_intake'                 => 'Breakfast: 1 cup lugaw (plain, no toppings). '
                                               . 'Lunch: ½ cup white rice, ½ cup monggo soup — patient reports nausea, ate only half. '
                                               . 'Dinner: Refused dinner — fatigue and decreased appetite. '
                                               . 'Estimated total: ~550 kcal, ~18 g protein. '
                                               . 'Pre-admission: irregular meals, skipping lunch and dinner for past 3 weeks due to work stress.',
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
            'nutritional_status'             => null,  // computed below by RiskScoreCalculator
            'medical_history'                => 'No prior chronic illness. No hospitalizations. '
                                               . 'History of loose stools for past 2 weeks (3–4×/day, non-bloody). '
                                               . 'Reports 11 kg unintentional weight loss over 3 weeks.',
            'social_history'                 => 'Single, lives alone in rented room in Angeles City. '
                                               . 'Construction worker — currently unemployed due to project pause (3 weeks). '
                                               . 'Non-smoker, social drinker (beer 2–3× per week). No illicit drug use.',
            'lifestyle'                      => 'Physically active prior to current illness. Now ambulatory but weak. '
                                               . 'Limited cooking facilities — relies on turo-turo (carinderias) for food.',
            'allergies'                      => [],
            'food_dislikes'                  => ['liver', 'malunggay'],
            'medications'                    => ['Thiamine 100 mg OD', 'Multivitamin OD', 'ORS PRN'],
            'chewing_swallowing_difficulties'=> null,
            'constipation'                   => null,
            'diarrhea_notes'                 => 'Loose stools 3–4×/day for 2 weeks prior to admission. Now resolving on admission day 3. Monitor for electrolyte losses.',
            'present_diet'                   => 'Soft diet — progressing from lugaw to regular texture as tolerated. '
                                               . 'Start with 3–4 small frequent meals at 400–500 kcal each.',
            'rnd_summary'                    => 'Roberto is a 37-year-old male with moderate protein-energy malnutrition (BMI 18.0, %IBW 78%, 17.5% weight loss over 3 weeks). '
                                               . 'Contributing factors: inadequate intake secondary to financial stress, food insecurity, and recent diarrheal illness. '
                                               . 'Thiamine supplementation initiated pre-refeeding. Progressive caloric build-up plan required. '
                                               . 'High nutritional risk — priority admission. Follow-up in 2 weeks to assess weight gain and tolerance.',
        ]);

        // Compute risk score from seeded assessment data so header badge and panel are consistent.
        $robertoAssessment = Assessment::where('ncp_record_id', $record->id)->first();
        $robertoAssessment->setRelation('ncpRecord', $record->setRelation('patient', $patient));
        $riskResult = resolve(RiskScoreCalculator::class)->calculate($robertoAssessment);
        $record->update(['risk_score' => $riskResult['score']]);
        $robertoAssessment->update(['nutritional_status' => $riskResult['nutritional_status']]);

        // Diagnosis — NC-3.1 Underweight / Malnutrition
        $pes = Diagnosis::buildPes(
            'Moderate protein-energy malnutrition',
            'severely inadequate dietary intake secondary to food insecurity and diarrheal illness',
            'BMI 18.0 kg/m², %IBW 78%, 17.5% unintentional weight loss over 3 weeks, MUAC 215 mm (borderline MAM), 24-hour recall estimated at ~550 kcal and ~18 g protein'
        );
        Diagnosis::create([
            'ncp_record_id' => $record->id,
            'domain'        => 'NC',
            'problem'       => 'Moderate protein-energy malnutrition',
            'label'         => 'NC-3.1',
            'etiology'      => 'Severely inadequate dietary intake secondary to food insecurity and diarrheal illness',
            'signs_symptoms'=> 'BMI 18.0 kg/m², %IBW 78%, 17.5% unintentional weight loss over 3 weeks, MUAC 215 mm (borderline MAM), 24-hour recall estimated at ~550 kcal and ~18 g protein',
            'pes_statement' => $pes,
            'extra_notes'   => 'Risk of refeeding syndrome — thiamine supplementation initiated. Gradual caloric increase per protocol. Monitor electrolytes (phosphate, potassium, magnesium) on Day 3–5.',
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
            'energy_kcal'      => 1300.00,   // progressive start — not full target yet
            'protein_g'        => 78.00,
            'carbs_g'          => 178.00,
            'fat_g'            => 43.00,
            'fluid_ml'         => 2000.00,
            'micronutrient_limits' => null,
            // Malnutrition flags no micros by default (GOAL_MICRO_FLAGS.malnutrition = []).
            'displayed_nutrients' => [],
            'session_type'     => 'initial',
            'next_followup_date' => Carbon::today()->addDays(14)->toDateString(),  // 2-week follow-up
            'education_notes'  => "NUTRITION EDUCATION — NUTRITIONAL REHABILITATION\n\n"
                                . "1. Your Current Status\n"
                                . "   You have moderate protein-energy malnutrition. "
                                . "This means your body has not been getting enough food and protein to maintain muscle and immune function.\n\n"
                                . "2. Feeding Plan — Start Slowly\n"
                                . "   Week 1: ~1,300 kcal/day in 4–5 small meals\n"
                                . "   Week 2: Increase to ~1,800 kcal/day as tolerated\n"
                                . "   Week 3+: Progress toward full target based on weight gain and tolerance\n\n"
                                . "3. Thiamine First (Refeeding Safety)\n"
                                . "   You are receiving Vitamin B1 (Thiamine) before increasing food intake. "
                                . "This prevents a serious complication called refeeding syndrome.\n\n"
                                . "4. Warning Signs — Tell your nurse or RND immediately\n"
                                . "   Muscle cramps, numbness/tingling in hands or feet, fast heartbeat, unusual weakness, swelling in legs.\n"
                                . "   These may signal dangerous electrolyte shifts.\n\n"
                                . "5. Food Priorities\n"
                                . "   Protein at every meal: eggs, chicken, fish, tokwa, monggo.\n"
                                . "   Energy-dense foods: lugaw with egg, peanut butter pandesal, evaporated milk.\n\n"
                                . "Next session (2 weeks): Weight check, electrolyte labs review, food tolerance assessment, diet upgrade.",
            'counseling_goals' => "1. Achieve minimum 0.5 kg weight gain per week over the next 2 weeks.\n"
                                . "2. Tolerate full soft diet (4 meals + 2 snacks) without nausea or diarrhea by Day 7.\n"
                                . "3. Understand and report warning signs of refeeding syndrome.\n"
                                . "4. Progress energy intake from 1,300 to 1,800 kcal/day by Week 2.",
            'barriers'         => "1. Financial constraint — patient's primary barrier to adequate food intake.\n"
                                . "2. Lives alone with no support system for meal preparation.\n"
                                . "3. Nausea and early satiety limiting meal portion tolerance.\n"
                                . "4. Prior diarrheal illness may have compromised nutrient absorption.",
            'strategies'       => "1. Refer to Medical Social Worker for financial assistance and food support programs.\n"
                                . "2. Provide list of affordable high-protein Filipino foods (eggs, tokwa, monggo, sardines).\n"
                                . "3. Small frequent meals every 3–4 hours — avoid large portions that trigger nausea.\n"
                                . "4. Fortify lugaw with egg, evaporated milk, or peanut butter to increase energy density.\n"
                                . "5. Electrolyte monitoring (phosphate, K+, Mg2+) on Day 3 and Day 5 post-refeeding start.",
        ]);

        $this->command->info('  PatientSeeder: Roberto Reyes seeded (active, follow-up in 2 weeks).');
    }
}
