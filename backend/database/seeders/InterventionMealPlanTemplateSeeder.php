<?php

namespace Database\Seeders;

use App\Models\MealPlanTemplate;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InterventionMealPlanTemplateSeeder extends Seeder
{
    /** Source and clinical-boundary record: docs/logic/intervention-goals.md §15. */
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function run(): void
    {
        $this->seedTemplates();
    }

    public function seedMissingTemplates(): void
    {
        $rnd = User::where('role', 'RND')->first();
        if (! $rnd) {
            return;
        }
        $legacy = MealPlanTemplate::where('rnd_user_id', $rnd->id)->where('name', 'Demo — Liver Disease — Soft Frequent Meals')->first();
        if (! $legacy) {
            return;
        }
        if ($legacy->goal_type !== 'liver_disease' || $legacy->disease_stage !== $this->profiles()['liver_disease']['stage'] || $legacy->maternal_status !== null) {
            throw new RuntimeException('Verify the legacy liver template metadata before adding its No Snacks variant.');
        }
        $this->seedTemplates(true, 'Demo — Liver Disease — No Snacks');
    }

    private function seedTemplates(bool $missingOnly = false, ?string $onlyName = null): void
    {
        $rnd = User::query()->where('role', 'RND')->first();
        if (! $rnd) {
            throw new RuntimeException('Intervention templates require a seeded RND user.');
        }

        $recipes = Recipe::query()->with('ingredients.foodItem')->where('rnd_user_id', $rnd->id)->get()->keyBy('name');

        DB::transaction(function () use ($rnd, $recipes, $missingOnly, $onlyName): void {
            foreach ($this->profiles() as $profileKey => $profile) {
                $goal = array_key_exists('goal_type', $profile) ? $profile['goal_type'] : $profileKey;
                $withSnacksName = in_array($profileKey, ['custom', 'pregnant', 'lactating'], true)
                    ? 'Balanced'
                    : 'Standard';
                $variants = $goal === 'liver_disease'
                    ? [['Frequent Meals', false, 0], ['No Snacks', true, 1]]
                    : [[$withSnacksName, false, 0], ['No Snacks', true, 1]];

                foreach ($variants as [$variantName, $excludeSnacks, $offset]) {
                    $name = "Demo — {$profile['label']} — {$variantName}";
                    if (($onlyName !== null && $name !== $onlyName)
                        || ($missingOnly && MealPlanTemplate::where('rnd_user_id', $rnd->id)->where('name', $name)->exists())) {
                        continue;
                    }
                    $template = MealPlanTemplate::updateOrCreate(
                        ['rnd_user_id' => $rnd->id, 'name' => $name],
                        [
                            'description' => $profile['description'].($excludeSnacks
                                ? (isset($profile['maternal_status'])
                                    ? ' Snack slots remain empty; maternal milk and fruit servings are folded into meals and must be scaled to the patient prescription.'
                                    : ' Snack slots remain empty; main-meal quantities are modestly increased and must be scaled to the patient prescription.')
                                : ' Includes between-meal nourishment for a five-slot clinical day.'),
                            'goal_type' => $goal,
                            'disease_stage' => $profile['stage'],
                            'maternal_status' => $profile['maternal_status'] ?? null,
                        ],
                    );
                    $template->days()->delete();

                    $carb = $this->rotate($profile['carbs'], $offset);
                    foreach (self::DAYS as $dayIndex => $dayName) {
                        $vegetable = isset($profile['vegetables'])
                            ? $this->rotate($profile['vegetables'], $dayIndex + $offset)
                            : null;
                        $foldMaternalSnacksIntoMeals = $excludeSnacks && isset($profile['maternal_status']);
                        $slotItems = [
                            'breakfast' => array_merge(
                                [$this->rotate($profile['breakfasts'], $dayIndex + $offset), $carb],
                                $foldMaternalSnacksIntoMeals
                                    ? $this->snackItems($profile, 'am_snack', $dayIndex + $offset)
                                    : [],
                            ),
                            'am_snack' => $excludeSnacks
                                ? []
                                : $this->snackItems($profile, 'am_snack', $dayIndex + $offset),
                            'lunch' => array_values(array_filter([
                                $this->rotate($profile['mains'], $dayIndex + $offset),
                                $carb,
                                $vegetable,
                            ])),
                            'pm_snack' => $excludeSnacks
                                ? []
                                : $this->snackItems($profile, 'pm_snack', $dayIndex + $offset + 1),
                            'dinner' => array_merge(
                                array_values(array_filter([
                                    $this->rotate($profile['mains'], $dayIndex + $offset + 1),
                                    $carb,
                                    isset($profile['vegetables'])
                                        ? $this->rotate($profile['vegetables'], $dayIndex + $offset + 1)
                                        : null,
                                ])),
                                $foldMaternalSnacksIntoMeals
                                    ? $this->snackItems($profile, 'pm_snack', $dayIndex + $offset + 1)
                                    : [],
                            ),
                        ];

                        foreach ($slotItems as $mealType => $itemNames) {
                            $resolved = collect($itemNames)->map(fn (string $itemName): array => $this->resolveTemplateItem(
                                $itemName,
                                $recipes,
                                $name,
                                $profile,
                                $excludeSnacks && in_array($mealType, ['lunch', 'dinner'], true),
                            ))->values();
                            $first = $resolved->first();

                            $templateDay = $template->days()->create([
                                'day_of_week' => $dayName,
                                'meal_type' => $mealType,
                                'food_item_id' => null,
                                'recipe_id' => $first['recipe']->id ?? null,
                                'quantity' => $first['quantity'] ?? 1,
                                'unit' => $first['unit'] ?? 'serving',
                            ]);

                            foreach ($resolved as $lineIndex => $item) {
                                $templateDay->items()->create([
                                    'food_item_id' => null,
                                    'recipe_id' => $item['recipe']->id,
                                    'quantity' => $item['quantity'],
                                    'unit' => $item['unit'],
                                    'nutrient_snapshot' => $item['snapshot'],
                                    'line_order' => $lineIndex + 1,
                                ]);
                            }
                        }
                    }
                }
            }
        });
    }

    public function repairCanonicalCarbSlots(): void
    {
        $rnd = User::where('role', 'RND')->first();
        if (! $rnd) {
            return;
        }
        DB::transaction(function () use ($rnd): void {
            $recipes = Recipe::with('ingredients.foodItem')->where('rnd_user_id', $rnd->id)->get()->keyBy('name');
            foreach ($this->profiles() as $profileKey => $profile) {
                $goal = array_key_exists('goal_type', $profile) ? $profile['goal_type'] : $profileKey;
                $standard = $goal === 'liver_disease' ? 'Frequent Meals' : (in_array($profileKey, ['custom', 'pregnant', 'lactating'], true) ? 'Balanced' : 'Standard');
                $variants = [[$standard, false, 0], ['No Snacks', true, 1]];
                if ($goal === 'liver_disease') {
                    $variants[] = ['Soft Frequent Meals', false, 1];
                }
                foreach ($variants as [$variant, $excludeSnacks, $offset]) {
                    $name = "Demo — {$profile['label']} — {$variant}";
                    $template = MealPlanTemplate::where('rnd_user_id', $rnd->id)->where('name', $name)->lockForUpdate()->first();
                    if (! $template) {
                        continue;
                    }
                    if ($template->goal_type !== $goal || (string) $template->disease_stage !== (string) $profile['stage']
                        || (string) $template->maternal_status !== (string) ($profile['maternal_status'] ?? '')) {
                        throw new RuntimeException('Canonical template metadata changed; verify it before correcting Carbs.');
                    }
                    $carb = $recipes->get($this->rotate($profile['carbs'], $offset));
                    if (! $carb || $carb->category !== 'carb' || ! $carb->hasValidPortionData()) {
                        throw new RuntimeException('Canonical template correction requires its measured Carb Menu Item.');
                    }
                    $days = $template->days()->whereIn('meal_type', ['breakfast', 'lunch', 'dinner'])->lockForUpdate()->get();
                    if ($days->count() !== 21 || $days->map(fn ($day) => $day->day_of_week.'|'.$day->meal_type)->unique()->count() !== 21
                        || ! $days->every(fn ($day) => in_array($day->day_of_week, self::DAYS, true))) {
                        throw new RuntimeException('Canonical template correction requires all 21 distinct main slots.');
                    }
                    foreach ($days as $day) {
                        $items = $day->items()->with('recipe')->orderBy('line_order')->lockForUpdate()->get();
                        $carbs = $items->filter(fn ($item) => $item->recipe?->category === 'carb');
                        if ($carbs->count() > 1) {
                            throw new RuntimeException('Verify duplicate Carb lines before correcting this canonical template.');
                        }
                        $line = $carbs->first();
                        $snapshot = $line?->recipe_id === $carb->id ? $line->nutrient_snapshot : $this->recipeSnapshot($carb);
                        $values = ['food_item_id' => null, 'recipe_id' => $carb->id,
                            'quantity' => $this->templateQuantity($carb, $profile, $excludeSnacks && in_array($day->meal_type, ['lunch', 'dinner'], true)),
                            'unit' => 'serving', 'nutrient_snapshot' => $snapshot];
                        if ($line) {
                            DB::table('meal_plan_template_items')->where('id', $line->id)->update([...$values, 'nutrient_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
                        } else {
                            $day->items()->create([...$values, 'line_order' => ($items->max('line_order') ?? 0) + 1]);
                        }
                    }
                }
            }
        });
    }

    private function rotate(array $values, int $index): string
    {
        return $values[$index % count($values)];
    }

    /** @return list<string> */
    private function snackItems(array $profile, string $mealType, int $index): array
    {
        $setsKey = $mealType.'_sets';
        if (isset($profile[$setsKey])) {
            return $profile[$setsKey][$index % count($profile[$setsKey])];
        }

        return [$this->rotate($profile['snacks'], $index)];
    }

    /** @return array{recipe:Recipe,quantity:float,unit:string,snapshot:array<string,mixed>} */
    private function resolveTemplateItem(
        string $itemName,
        $recipes,
        string $templateName,
        array $profile,
        bool $largerMainMeal,
    ): array {
        $recipe = $recipes->get($itemName);
        if (! $recipe) {
            throw new RuntimeException("Template '{$templateName}' requires missing item '{$itemName}'.");
        }

        return [
            'recipe' => $recipe,
            'quantity' => $this->templateQuantity($recipe, $profile, $largerMainMeal),
            'unit' => 'serving',
            'snapshot' => $this->recipeSnapshot($recipe),
        ];
    }

    private function templateQuantity(Recipe $recipe, array $profile, bool $largerMainMeal): float
    {
        $portionAmount = (float) $recipe->prepared_portion_amount;
        $portionUnit = trim((string) $recipe->prepared_portion_unit);
        if ($portionAmount <= 0 || $portionUnit === '') {
            throw new RuntimeException("Menu Item '{$recipe->name}' has no positive prepared portion.");
        }

        $targetAmount = match (true) {
            $recipe->category === 'carb' => $this->carbTargetAmount($recipe, $profile, $portionUnit, $largerMainMeal),
            isset($profile['item_quantities'][$recipe->name]) => (float) $profile['item_quantities'][$recipe->name],
            default => $portionAmount,
        };

        if ($largerMainMeal && $recipe->category !== 'snack' && ! in_array($recipe->name, $profile['carbs'], true)) {
            $targetAmount += match ($portionUnit) {
                'g' => 25,
                'cup' => 0.5,
                default => 0,
            };
        }

        if ($targetAmount <= 0) {
            throw new RuntimeException("Template Menu Item '{$recipe->name}' requires a positive portion.");
        }

        return round($targetAmount / $portionAmount, 6);
    }

    private function carbTargetAmount(Recipe $recipe, array $profile, string $portionUnit, bool $largerMainMeal): float
    {
        if ($portionUnit !== 'g') {
            throw new RuntimeException("Carb Menu Item '{$recipe->name}' uses {$portionUnit}; its gram-based template target cannot be converted safely.");
        }

        return (float) $profile['carb_quantity_g'] + ($largerMainMeal ? 25 : 0);
    }

    /** @return array<string,mixed> */
    private function recipeSnapshot(Recipe $recipe): array
    {
        return ['source' => 'recipe', ...$recipe->planSnapshot()];
    }

    /** @return array<string,array<string,mixed>> */
    private function profiles(): array
    {
        return [
            'renal_diet' => [
                'label' => 'Renal Diet', 'stage' => 'ckd_3_5_non_dialysis',
                'description' => 'Moderate-protein, lower-sodium Filipino pattern for stable CKD without dialysis; potassium, phosphorus, and fluid still require patient-specific review.',
                'breakfasts' => ['Pandesal with Egg', 'Lugaw with Egg (Soft Diet)'],
                'mains' => ['Boiled Chicken Breast', 'Steamed Tilapia', 'Paksiw na Bangus', 'Chicken Breast with Pechay'],
                'snacks' => ['Pineapple Snack', 'Guava Snack'],
                'carbs' => ['Steamed White Rice'], 'carb_quantity_g' => 150,
            ],
            'diabetic_control' => [
                'label' => 'Diabetic Control', 'stage' => 'type_2',
                'description' => 'Consistent-carbohydrate Filipino pattern emphasizing vegetables, lean protein, and unsweetened high-fiber choices.',
                'breakfasts' => ['Oatmeal with Banana', 'Pandesal with Egg'],
                'mains' => ['Ampalaya with Tokwa', 'Pinakbet (Hospital Version)', 'Chicken Breast with Pechay', 'Paksiw na Bangus'],
                'snacks' => ['Guava Snack', 'Papaya Snack'],
                'carbs' => ['Steamed Brown Rice', 'Sweet Potato / Kamote'], 'carb_quantity_g' => 150,
            ],
            'cardiac_diet' => [
                'label' => 'Cardiac Diet', 'stage' => 'hypertension',
                'description' => 'Lower-sodium, lean-protein Filipino pattern with vegetables and higher-fiber carbohydrate choices.',
                'breakfasts' => ['Oatmeal with Banana', 'Pandesal with Egg'],
                'mains' => ['Steamed Tilapia', 'Paksiw na Bangus', 'Bulanglang (Vegetable Soup)', 'Chicken Breast with Pechay'],
                'snacks' => ['Papaya with Calamansi', 'Guava Snack'],
                'carbs' => ['Steamed Brown Rice', 'Sweet Potato / Kamote'], 'carb_quantity_g' => 150,
            ],
            'weight_loss' => [
                'goal_type' => 'general_diet',
                'label' => 'Weight Loss', 'stage' => null,
                'description' => 'Energy-controlled pattern centered on lean protein, vegetables, fruit, and practical portions.',
                'breakfasts' => ['Oatmeal with Banana', 'Pandesal with Egg'],
                'mains' => ['Boiled Chicken Breast', 'Steamed Tilapia', 'Mixed Vegetable Plate', 'Ampalaya with Tokwa'],
                'snacks' => ['Guava Snack', 'Fresh Watermelon Snack'],
                'carbs' => ['Sweet Potato / Kamote', 'Steamed Brown Rice'], 'carb_quantity_g' => 100,
            ],
            'weight_gain' => [
                'goal_type' => 'general_diet',
                'label' => 'Weight Gain', 'stage' => null,
                'description' => 'Energy-dense pattern with regular meals, carbohydrate choices, and planned between-meal nourishment.',
                'breakfasts' => ['Champorado (Chocolate Rice Porridge)', 'Pandesal with Egg', 'Arroz Caldo (Chicken Rice Soup)'],
                'mains' => ['Roasted Pork Loin', 'Tinolang Manok (Chicken Ginger Soup)', 'Ginisang Monggo with Sardines', 'Steamed Milkfish (Bangus)'],
                'snacks' => ['Crackers and Milk Snack', 'Fresh Mango Serving'],
                'carbs' => ['Steamed White Rice', 'Sweet Potato / Kamote'], 'carb_quantity_g' => 200,
            ],
            'high_protein' => [
                'label' => 'High Protein', 'stage' => 'postoperative_recovery',
                'description' => 'Protein-forward Filipino pattern for increased needs, with energy sources that help spare dietary protein.',
                'breakfasts' => ['Lugaw with Chicken (Soft Diet)', 'Pandesal with Egg'],
                'mains' => ['Boiled Chicken Breast', 'Steamed Tilapia', 'Steamed Milkfish (Bangus)', 'Ginisang Monggo with Sardines'],
                'snacks' => ['Crackers and Milk Snack', 'Hard-Boiled Egg'],
                'carbs' => ['Steamed Brown Rice', 'Sweet Corn (Cooked)'], 'carb_quantity_g' => 150,
            ],
            'liver_disease' => [
                'label' => 'Liver Disease', 'stage' => 'decompensated',
                'description' => 'Frequent-meal liver pattern that avoids prolonged fasting; retain between-meal nourishment and arrange the prescribed late-evening snack with clinician review.',
                'breakfasts' => ['Lugaw with Chicken (Soft Diet)', 'Oatmeal with Banana', 'Arroz Caldo (Chicken Rice Soup)'],
                'mains' => ['Boiled Chicken Breast', 'Steamed Tilapia', 'Plain Ginisang Monggo', 'Tinolang Manok (Chicken Ginger Soup)'],
                'snacks' => ['Crackers and Milk Snack', 'Papaya Snack'],
                'carbs' => ['Steamed White Rice', 'Sweet Potato / Kamote'], 'carb_quantity_g' => 150,
            ],
            'malnutrition' => [
                'goal_type' => 'general_diet',
                'label' => 'Malnutrition', 'stage' => null,
                'description' => 'Energy- and protein-dense rehabilitation pattern using frequent, culturally familiar meals and snacks.',
                'breakfasts' => ['Lugaw with Chicken (Soft Diet)', 'Pandesal with Egg', 'Arroz Caldo (Chicken Rice Soup)'],
                'mains' => ['Boiled Chicken Breast', 'Ginisang Monggo with Sardines', 'Steamed Milkfish (Bangus)', 'Roasted Pork Loin'],
                'snacks' => ['Crackers and Milk Snack', 'Fresh Mango Serving'],
                'carbs' => ['Steamed White Rice', 'Sweet Potato / Kamote'], 'carb_quantity_g' => 200,
            ],
            'general_diet' => [
                'label' => 'General Diet', 'stage' => null,
                'description' => 'Balanced Filipino base pattern using separate main, carbohydrate, and vegetable components; the RND prescription and limits remain authoritative.',
                'breakfasts' => ['Oatmeal with Banana', 'Pandesal with Egg'],
                'mains' => ['Boiled Chicken Breast', 'Steamed Tilapia', 'Steamed Milkfish (Bangus)', 'Plain Ginisang Monggo'],
                'snacks' => ['Guava Snack', 'Papaya Snack'],
                'carbs' => ['Sweet Potato / Kamote', 'Sweet Corn (Cooked)', 'Steamed Brown Rice'], 'carb_quantity_g' => 150,
                'vegetables' => ['Bok Choy / Pechay (Cooked)', 'Eggplant / Talong (Cooked)', 'Squash / Kalabasa (Cooked)'],
            ],
            'custom' => [
                'label' => 'Custom Balanced Filipino', 'stage' => null,
                'description' => 'Balanced Filipino base pattern using separate main, carbohydrate, and vegetable components; the RND prescription and limits remain authoritative.',
                'breakfasts' => ['Oatmeal with Banana', 'Pandesal with Egg'],
                'mains' => ['Boiled Chicken Breast', 'Steamed Tilapia', 'Steamed Milkfish (Bangus)', 'Plain Ginisang Monggo'],
                'snacks' => ['Guava Snack', 'Papaya Snack'],
                'carbs' => ['Sweet Potato / Kamote', 'Sweet Corn (Cooked)', 'Steamed Brown Rice'], 'carb_quantity_g' => 150,
                'vegetables' => ['Bok Choy / Pechay (Cooked)', 'Eggplant / Talong (Cooked)', 'Squash / Kalabasa (Cooked)'],
            ],
            'pregnant' => [
                'goal_type' => null, 'maternal_status' => 'pregnant',
                'label' => 'Maternal Nutrition — Pregnancy', 'stage' => null,
                'description' => 'Pregnancy example derived from the NNC/DOH 2022 maternal guide: varied carbohydrate and protein foods, vegetables, fruit, and daily milk; scale to the RND-confirmed trimester prescription and clinical goal.',
                'breakfasts' => ['Oatmeal with Banana', 'Pandesal with Egg', 'Lugaw with Chicken (Soft Diet)'],
                'mains' => ['Steamed Tilapia', 'Chicken Breast with Pechay', 'Ginisang Monggo with Sardines', 'Tinolang Manok (Chicken Ginger Soup)'],
                'snacks' => ['Papaya Snack'],
                'am_snack_sets' => [
                    ['Low-fat Milk (1%)'],
                ],
                'pm_snack_sets' => [
                    ['Papaya Snack'],
                    ['Banana Snack'],
                ],
                'carbs' => ['Steamed Brown Rice', 'Sweet Potato / Kamote', 'Sweet Corn (Cooked)'], 'carb_quantity_g' => 200,
                'vegetables' => ['Bok Choy / Pechay (Cooked)', 'Carrots (Cooked)', 'Squash / Kalabasa (Cooked)', 'String Beans / Sitaw'],
                'item_quantities' => [
                    'Low-fat Milk (1%)' => 240,
                    'Papaya Snack' => 90,
                    'Banana Snack' => 80,
                ],
            ],
            'lactating' => [
                'goal_type' => null, 'maternal_status' => 'lactating',
                'label' => 'Maternal Nutrition — Lactation', 'stage' => null,
                'description' => 'Lactation example derived from current NNC/DOH breastfeeding guidance: two daily milk servings, fruit, varied vegetables, protein foods, and carbohydrate exchanges; scale to the final RND prescription and clinical goal.',
                'breakfasts' => ['Oatmeal with Banana', 'Pandesal with Egg', 'Lugaw with Chicken (Soft Diet)'],
                'mains' => ['Tinolang Manok (Chicken Ginger Soup)', 'Ginisang Monggo with Sardines', 'Steamed Milkfish (Bangus)', 'Steamed Tilapia'],
                'snacks' => ['Papaya Snack'],
                'am_snack_sets' => [
                    ['Low-fat Milk (1%)', 'Papaya Snack'],
                ],
                'pm_snack_sets' => [
                    ['Low-fat Milk (1%)', 'Banana Snack'],
                ],
                'carbs' => ['Steamed Brown Rice', 'Sweet Corn (Cooked)', 'Sweet Potato / Kamote'], 'carb_quantity_g' => 200,
                'vegetables' => ['Water Spinach (Kangkong)', 'Bok Choy / Pechay (Cooked)', 'Squash / Kalabasa (Cooked)', 'String Beans / Sitaw'],
                'item_quantities' => [
                    'Low-fat Milk (1%)' => 240,
                    'Papaya Snack' => 90,
                    'Banana Snack' => 80,
                ],
            ],
        ];
    }
}
