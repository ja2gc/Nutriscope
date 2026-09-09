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
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function run(): void
    {
        $rnd = User::query()->where('role', 'RND')->first();
        if (! $rnd) {
            throw new RuntimeException('Intervention templates require a seeded RND user.');
        }

        $recipes = Recipe::query()->get()->keyBy('name');

        DB::transaction(function () use ($rnd, $recipes): void {
            foreach ($this->profiles() as $goal => $profile) {
                $variants = $goal === 'liver_disease'
                    ? [['Frequent Meals', false, 0], ['Soft Frequent Meals', false, 1]]
                    : [['Standard', false, 0], ['No Snacks', true, 1]];

                foreach ($variants as [$variantName, $excludeSnacks, $offset]) {
                    $name = "Demo — {$profile['label']} — {$variantName}";
                    $template = MealPlanTemplate::updateOrCreate(
                        ['rnd_user_id' => $rnd->id, 'name' => $name],
                        [
                            'description' => $profile['description'].($excludeSnacks
                                ? ' Snack slots remain empty; main-meal quantities are modestly increased and must be scaled to the patient prescription.'
                                : ' Includes between-meal nourishment for a five-slot clinical day.'),
                            'goal_type' => $goal,
                            'disease_stage' => $profile['stage'],
                        ],
                    );
                    $template->days()->delete();

                    foreach (self::DAYS as $dayIndex => $dayName) {
                        $slotRecipes = [
                            'breakfast' => [$this->rotate($profile['breakfasts'], $dayIndex + $offset)],
                            'am_snack' => $excludeSnacks ? [] : [$this->rotate($profile['snacks'], $dayIndex + $offset)],
                            'lunch' => array_values(array_filter([
                                $this->rotate($profile['mains'], $dayIndex + $offset),
                                $profile['staple'],
                            ])),
                            'pm_snack' => $excludeSnacks ? [] : [$this->rotate($profile['snacks'], $dayIndex + $offset + 1)],
                            'dinner' => array_values(array_filter([
                                $this->rotate($profile['mains'], $dayIndex + $offset + 1),
                                $profile['staple'],
                            ])),
                        ];

                        foreach ($slotRecipes as $mealType => $recipeNames) {
                            $resolved = collect($recipeNames)->map(function (string $recipeName) use ($recipes, $name, $excludeSnacks, $mealType): array {
                                $recipe = $recipes->get($recipeName);
                                if (! $recipe) {
                                    throw new RuntimeException("Template '{$name}' requires missing recipe '{$recipeName}'.");
                                }

                                $quantity = $this->templateQuantity($recipe, $excludeSnacks && in_array($mealType, ['lunch', 'dinner'], true));

                                return [
                                    'recipe' => $recipe,
                                    'quantity' => $quantity,
                                    'unit' => $recipe->prepared_portion_unit ?: 'serving',
                                    'snapshot' => $this->recipeSnapshot($recipe),
                                ];
                            })->values();
                            $first = $resolved->first();

                            $templateDay = $template->days()->create([
                                'day_of_week' => $dayName,
                                'meal_type' => $mealType,
                                'recipe_id' => $first['recipe']->id ?? null,
                                'quantity' => $first['quantity'] ?? 1,
                                'unit' => $first['unit'] ?? 'serving',
                            ]);

                            foreach ($resolved as $lineIndex => $item) {
                                $templateDay->items()->create([
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

    private function rotate(array $values, int $index): string
    {
        return $values[$index % count($values)];
    }

    private function templateQuantity(Recipe $recipe, bool $largerMainMeal): float
    {
        $amount = (float) ($recipe->prepared_portion_amount ?: 1);
        if (! $largerMainMeal) {
            return $amount;
        }

        return match ($recipe->prepared_portion_unit) {
            'g' => $amount + 25,
            'cup' => $amount + 0.5,
            default => $amount,
        };
    }

    /** @return array<string,mixed> */
    private function recipeSnapshot(Recipe $recipe): array
    {
        $servings = max(1.0, (float) $recipe->servings);

        return [
            'name' => $recipe->name,
            'calories' => round((float) $recipe->total_calories / $servings, 2),
            'protein' => round((float) $recipe->total_protein / $servings, 2),
            'carbs' => round((float) $recipe->total_carbs / $servings, 2),
            'fat' => round((float) $recipe->total_fat / $servings, 2),
            'water_g' => round((float) $recipe->total_water / $servings, 2),
            'micronutrients' => collect($recipe->micronutrients ?? [])->map(
                fn ($value): float => round((float) $value / $servings, 3)
            )->all(),
            'serving_size' => (float) ($recipe->prepared_portion_amount ?: 1),
            'serving_unit' => $recipe->prepared_portion_unit ?: 'serving',
            'source' => 'recipe',
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function profiles(): array
    {
        return [
            'renal_diet' => [
                'label' => 'Renal Diet', 'stage' => 'stage_4',
                'description' => 'Moderate-protein, lower-sodium Filipino pattern for CKD stage 4; potassium, phosphorus, and fluid still require patient-specific review.',
                'breakfasts' => ['Pandesal with Egg', 'Lugaw with Egg (Soft Diet)'],
                'mains' => ['Boiled Chicken Breast', 'Steamed Tilapia', 'Paksiw na Bangus', 'Chicken Breast with Pechay'],
                'snacks' => ['Pineapple Snack', 'Guava Snack'], 'staple' => 'Plain White Rice Meal',
            ],
            'diabetic_control' => [
                'label' => 'Diabetic Control', 'stage' => 'stage_2',
                'description' => 'Consistent-carbohydrate Filipino pattern emphasizing vegetables, lean protein, and unsweetened high-fiber choices.',
                'breakfasts' => ['Oatmeal with Banana', 'Pandesal with Egg'],
                'mains' => ['Ampalaya with Tokwa', 'Pinakbet (Hospital Version)', 'Chicken Breast with Pechay', 'Paksiw na Bangus'],
                'snacks' => ['Guava Snack', 'Papaya Snack'], 'staple' => 'Plain Brown Rice Meal',
            ],
            'cardiac_diet' => [
                'label' => 'Cardiac Diet', 'stage' => 'moderate',
                'description' => 'Lower-sodium, lean-protein Filipino pattern with vegetables and higher-fiber staple choices.',
                'breakfasts' => ['Oatmeal with Banana', 'Pandesal with Egg'],
                'mains' => ['Steamed Tilapia', 'Paksiw na Bangus', 'Bulanglang (Vegetable Soup)', 'Chicken Breast with Pechay'],
                'snacks' => ['Papaya with Calamansi', 'Guava Snack'], 'staple' => 'Plain Brown Rice Meal',
            ],
            'weight_loss' => [
                'label' => 'Weight Loss', 'stage' => 'class_1',
                'description' => 'Energy-controlled pattern centered on lean protein, vegetables, fruit, and practical portions.',
                'breakfasts' => ['Oatmeal with Banana', 'Pandesal with Egg'],
                'mains' => ['Boiled Chicken Breast', 'Steamed Tilapia', 'Mixed Vegetable Plate', 'Ampalaya with Tokwa'],
                'snacks' => ['Guava Snack', 'Fresh Watermelon Snack'], 'staple' => null,
            ],
            'weight_gain' => [
                'label' => 'Weight Gain', 'stage' => 'moderate',
                'description' => 'Energy-dense pattern with regular meals, staples, and planned between-meal nourishment.',
                'breakfasts' => ['Champorado (Chocolate Rice Porridge)', 'Pandesal with Egg', 'Arroz Caldo (Chicken Rice Soup)'],
                'mains' => ['Roasted Pork Loin', 'Tinolang Manok (Chicken Ginger Soup)', 'Ginisang Monggo with Sardines', 'Steamed Milkfish (Bangus)'],
                'snacks' => ['Crackers and Milk Snack', 'Fresh Mango Serving'], 'staple' => 'Plain White Rice Meal',
            ],
            'high_protein' => [
                'label' => 'High Protein', 'stage' => 'moderate_stress',
                'description' => 'Protein-forward Filipino pattern for increased needs, with energy sources that help spare dietary protein.',
                'breakfasts' => ['Lugaw with Chicken (Soft Diet)', 'Pandesal with Egg'],
                'mains' => ['Boiled Chicken Breast', 'Steamed Tilapia', 'Steamed Milkfish (Bangus)', 'Ginisang Monggo with Sardines'],
                'snacks' => ['Crackers and Milk Snack', 'Hard-Boiled Egg'], 'staple' => 'Plain Brown Rice Meal',
            ],
            'liver_disease' => [
                'label' => 'Liver Disease', 'stage' => 'decompensated',
                'description' => 'Frequent-meal liver pattern that avoids prolonged fasting; retain between-meal nourishment and arrange the prescribed late-evening snack with clinician review.',
                'breakfasts' => ['Lugaw with Chicken (Soft Diet)', 'Oatmeal with Banana', 'Arroz Caldo (Chicken Rice Soup)'],
                'mains' => ['Boiled Chicken Breast', 'Steamed Tilapia', 'Plain Ginisang Monggo', 'Tinolang Manok (Chicken Ginger Soup)'],
                'snacks' => ['Crackers and Milk Snack', 'Papaya Snack'], 'staple' => 'Plain White Rice Meal',
            ],
            'malnutrition' => [
                'label' => 'Malnutrition', 'stage' => 'severe',
                'description' => 'Energy- and protein-dense rehabilitation pattern using frequent, culturally familiar meals and snacks.',
                'breakfasts' => ['Lugaw with Chicken (Soft Diet)', 'Pandesal with Egg', 'Arroz Caldo (Chicken Rice Soup)'],
                'mains' => ['Boiled Chicken Breast', 'Ginisang Monggo with Sardines', 'Steamed Milkfish (Bangus)', 'Roasted Pork Loin'],
                'snacks' => ['Crackers and Milk Snack', 'Fresh Mango Serving'], 'staple' => 'Plain White Rice Meal',
            ],
        ];
    }
}
