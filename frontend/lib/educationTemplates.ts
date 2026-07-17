/**
 * Per-goal nutrition education templates.
 * Auto-populated into education_notes when a goal is set and the field is empty.
 * RND may edit freely after auto-populate.
 */

export const EDUCATION_TEMPLATES: Record<string, string> = {
  renal_diet: `NUTRITION EDUCATION — RENAL DIET (CKD)

1. Understanding Kidney Disease and Diet
   Your kidneys can no longer filter waste normally. A controlled diet reduces the burden on your kidneys and prevents dangerous buildup of potassium, phosphorus, and sodium.

2. Foods to Limit
   • Potassium: bananas, oranges, tomatoes, potatoes — swap for apples, cabbage, white rice
   • Phosphorus: dairy, dark sodas, nuts, whole grains — choose egg whites, white rice, cabbage
   • Sodium: all processed and salty foods — cook fresh, use calamansi for flavor

3. Fluid Monitoring
   [Per stage — RND to fill in fluid target]
   Measure all fluids including soups, gelatin, and ice. Weigh yourself daily.

4. Protein Goals
   [Per stage — RND to fill in protein target]
   Choose high-quality protein (eggs, fish, chicken) and keep portions controlled.

5. Label Reading
   Check for "phosphate additives" in ingredient lists — these absorb 100% unlike natural phosphorus.

Next session: Review lab values (creatinine, BUN, potassium, phosphorus, albumin).`,

  diabetic_control: `NUTRITION EDUCATION — DIABETIC MEAL PLANNING

1. How Food Affects Blood Sugar
   Carbohydrates raise blood sugar most. The goal is consistent, controlled carb intake — not elimination.

2. Carbohydrate Counting
   Target: [RND to fill per prescription] g carbs per meal, [g] per snack.
   Examples: 1 cup cooked rice = ~45 g carbs; 1 slice white bread = ~15 g carbs.

3. The Plate Method
   ½ plate: non-starchy vegetables (kangkong, ampalaya, pechay)
   ¼ plate: lean protein (fish, chicken, tofu)
   ¼ plate: complex carbs (brown rice, oatmeal)

4. Timing Matters
   Eat at regular intervals. Never skip meals — this causes blood sugar swings.

5. Foods to Minimize
   Sugary drinks, white bread, sticky rice (malagkit), sweets, fried foods.

6. Reading Labels
   Watch for "sugar," "corn syrup," "glucose" in ingredients. Total carbohydrates = the number that matters.

Next session: Review blood glucose log and medication timing with meals.`,

  cardiac_diet: `NUTRITION EDUCATION — HEART-HEALTHY DIET

1. Why Diet Matters for Your Heart
   Excess sodium causes fluid retention and raises blood pressure. Saturated and trans fats contribute to plaque buildup in arteries.

2. Sodium Targets
   Daily limit: [RND to fill per stage] mg/day
   1 teaspoon salt = ~2300 mg sodium. Most Filipinos consume 3000–4000 mg/day.

3. The DASH Approach
   • More: vegetables, fruits, fish, legumes, low-fat dairy
   • Less: salt, salty condiments (patis, toyo, bagoong), processed meats, coconut milk

4. Reading Sauces and Condiments
   Patis (fish sauce): ~1400 mg sodium per tablespoon. Use calamansi and herbs instead.

5. Heart-Healthy Fats
   Choose: fish (omega-3), canola oil, olive oil.
   Avoid: lard, coconut oil (high saturated fat), trans fats in packaged foods.

6. Fluid Limit
   [If moderate/severe stage — RND to fill in fluid target]
   Track all liquids. Sudden weight gain of >1 kg overnight = fluid retention, call your RND.

Next session: Review sodium diary and blood pressure trends.`,

  weight_loss: `NUTRITION EDUCATION — WEIGHT MANAGEMENT

1. Energy Balance
   Weight loss occurs when energy intake < energy expenditure.
   Your daily energy target: [RND to fill] kcal/day.

2. Mindful Portions
   Use the plate method. Reduce rice portions by ¼ — this alone removes 150–200 kcal/day.

3. Protein Priority
   High protein (fish, eggs, chicken, tofu) at each meal preserves muscle mass while losing fat and improves satiety.

4. Fiber for Fullness
   Vegetables, fruits, and whole grains slow digestion. Aim for 25–38 g fiber/day.

5. Beverage Calories Count
   Sweetened drinks (juice, soda, milk tea) add 150–400 kcal without making you feel full. Switch to water, unsweetened tea, or calamansi water.

6. Sustainable Changes
   Crash diets cause muscle loss and rebound weight gain. Slow, steady loss of 0.25–0.5 kg/week is the evidence-based target.

Next session: Review 3-day food record and identify top calorie sources.`,

  weight_gain: `NUTRITION EDUCATION — WEIGHT GAIN / NUTRITIONAL REHABILITATION

1. Your Goal
   Safe, steady weight gain of 0.25–0.5 kg/week through nutrient-dense foods.
   Daily energy target: [RND to fill] kcal/day.

2. Energy-Dense Foods
   • Peanut butter (2 tbsp = ~200 kcal)
   • Eggs (1 egg = ~70 kcal)
   • Brown rice, camote, gabi (complex carbs)
   • Full-fat evaporated milk as drink or ingredient

3. Eating Pattern
   3 full meals + 2–3 snacks daily. Never skip meals.
   Add a bedtime snack (e.g., peanut butter on pandesal + glass of milk).

4. Protein for Muscle
   Target: [RND to fill] g protein/day. Distribute across all meals.
   Aim for at least 20–30 g protein per meal.

5. If Severe Malnutrition
   Follow the prescription and monitoring schedule your RND gives you exactly.
   Report any muscle cramps, tingling, or confusion immediately — these may signal electrolyte changes.

Next session: Weight check and tolerance assessment.`,

  high_protein: `NUTRITION EDUCATION — HIGH PROTEIN DIET

1. Why You Need More Protein
   Your body is under [stress/healing/recovery]. Extra protein is needed to repair tissue, support immune function, and prevent muscle breakdown.
   Daily protein target: [RND to fill] g/day.

2. Protein Sources
   • Animal: eggs, chicken breast, fish, low-fat dairy
   • Plant: tofu/tokwa, mung beans (monggo), peanuts
   Distribute protein across ALL meals — the body can only use ~25–40 g at a time for muscle synthesis.

3. Energy Is Equally Important
   Protein cannot do its job if you are undereating calories. Eat your full energy target.
   Daily energy target: [RND to fill] kcal/day.

4. Wound / Surgery Patients
   Vitamin C (from guava, calamansi, papaya) supports collagen synthesis.
   Zinc (from meat, shellfish, legumes) supports wound healing.

5. Monitoring
   Lab values to watch: albumin, pre-albumin (reflect protein status over weeks).

Next session: Review healing progress and protein intake diary.`,

  liver_disease: `NUTRITION EDUCATION — LIVER DISEASE DIET

1. Why Nutrition Matters in Liver Disease
   The liver processes all nutrients. A damaged liver needs consistent nutritional support — not restriction — to prevent muscle wasting (sarcopenia).

2. Protein — Do NOT Restrict (except severe encephalopathy)
   Target: [RND to fill] g/day. Distribute across 4–6 small meals.
   IMPORTANT: Eat a late-evening snack (e.g., oatmeal with egg) — this prevents overnight muscle breakdown.

3. Sodium Control
   Limit sodium to [RND to fill per stage] mg/day to reduce ascites (fluid in abdomen).
   Avoid: patis, bagoong, toyo, canned goods, processed snacks.

4. Encephalopathy Management
   If you have been told your liver is affecting your thinking: Continue eating protein — do not stop on your own. Your RND manages this through diet composition, not restriction.

5. Absolutely No Alcohol
   Even small amounts of alcohol cause further liver damage. This is non-negotiable.

6. Meal Timing
   Small, frequent meals (every 3–4 hours) reduce stress on the liver and prevent blood sugar drops.

Next session: Assess encephalopathy grade and adjust protein targets if needed.`,

  malnutrition: `NUTRITION EDUCATION — NUTRITIONAL REHABILITATION

1. Your Current Status
   Nutritional assessment shows [moderate/severe] malnutrition. This affects your strength, immunity, and healing ability.

2. Nutrition Plan
   Daily energy target: [RND to fill] kcal/day.
   Daily protein target: [RND to fill] g/day.
   Follow the portions and meal schedule prescribed by your RND.

3. Micronutrient Support (Severe Malnutrition)
   Take only supplements ordered by your clinical team. Do not self-prescribe high-dose vitamins or minerals.

4. Consistent Intake
   Follow the prescribed daily target. Spread meals and snacks across the day to improve tolerance.

5. Warning Signs — Report Immediately
   Muscle cramps, numbness/tingling, rapid heartbeat, confusion, swelling.
   These may indicate dangerous electrolyte shifts and require prompt clinical review.

6. Beyond the Hospital
   Sustainable recovery requires: consistent meal schedule, protein at every meal, follow-up labs every 1–2 weeks.

Next session: Weight, labs (phosphate, potassium, magnesium), and tolerance review.`,
};
