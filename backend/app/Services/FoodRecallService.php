<?php

namespace App\Services;

use App\Models\FoodItem;
use App\Models\Recipe;
use App\Support\UnitConverter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class FoodRecallService
{
    public static function intakeRules(): array
    {
        return [
            'intake_nutrients' => ['sometimes', 'nullable', 'array', 'max:40', function (string $attribute, mixed $values, \Closure $fail): void {
                foreach (array_keys(is_array($values) ? $values : []) as $key) {
                    if (! preg_match('/^(?:energy_kcal|protein_g|carbs_g|fat_g|fluid_ml|micro_[a-z0-9_]{1,80})$/D', (string) $key)) {
                        $fail('Choose a supported intake nutrient.');
                    }
                }
            }],
            'intake_nutrients.*' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', function (string $attribute, mixed $value, \Closure $fail): void {
                $key = substr($attribute, strlen('intake_nutrients.'));
                $max = match ($key) {
                    'energy_kcal', 'fluid_ml' => 10000,
                    'protein_g', 'carbs_g', 'fat_g' => 1000,
                    default => preg_match('/^micro_[a-z0-9_]{1,80}$/D', $key) ? 100000 : null,
                };
                if ($max === null || ($value !== null && (! is_numeric($value) || (float) $value > $max))) {
                    $fail('Choose a supported nutrient and enter an amount within its allowed range.');
                }
            }],
        ];
    }

    public static function rules(): array
    {
        return [
            'food_recall' => ['sometimes', 'nullable', 'array:days_count,displayed_micros,days'],
            'food_recall.days_count' => ['required_with:food_recall', 'integer', 'min:1'],
            'food_recall.displayed_micros' => ['present_with:food_recall', 'array'],
            'food_recall.displayed_micros.*' => ['string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/D', 'distinct'],
            'food_recall.days' => ['required_with:food_recall', 'array', 'min:1', 'list'],
            'food_recall.days.*' => ['array:kind,items'],
            'food_recall.days.*.kind' => ['sometimes', 'string', 'in:weekday,weekend'],
            'food_recall.days.*.items' => ['required', 'array', 'min:1', 'list'],
            'food_recall.days.*.items.*' => ['array:id,food_item_id,recipe_id,quantity,unit,ingredients'],
            'food_recall.days.*.items.*.id' => ['sometimes', 'uuid', 'distinct'],
            'food_recall.days.*.items.*.food_item_id' => ['nullable', 'uuid'],
            'food_recall.days.*.items.*.recipe_id' => ['nullable', 'uuid'],
            'food_recall.days.*.items.*.quantity' => ['required', 'numeric', 'between:0.000001,100000', 'decimal:0,6'],
            'food_recall.days.*.items.*.unit' => ['required', 'string', 'max:50'],
            'food_recall.days.*.items.*.ingredients' => ['sometimes', 'array', 'min:1', 'list'],
            'food_recall.days.*.items.*.ingredients.*' => ['array:food_item_id,quantity,unit'],
            'food_recall.days.*.items.*.ingredients.*.food_item_id' => ['required', 'uuid'],
            'food_recall.days.*.items.*.ingredients.*.quantity' => ['required', 'numeric', 'between:0.000001,100000', 'decimal:0,6'],
            'food_recall.days.*.items.*.ingredients.*.unit' => ['required', 'string', 'max:50'],
        ];
    }

    public function normalize(?array $plan, ?array $previous = null): ?array
    {
        if ($plan === null) {
            return null;
        }
        if (count($plan['days']) !== (int) $plan['days_count']) {
            throw ValidationException::withMessages(['food_recall.days' => 'Add at least one item to every selected day.']);
        }
        $saved = collect($previous['days'] ?? [])->flatMap(fn (array $day): array => $day['items'])->keyBy('id');
        $days = [];
        foreach ($plan['days'] as $dayIndex => $day) {
            $items = [];
            foreach ($day['items'] as $itemIndex => $line) {
                $path = "food_recall.days.{$dayIndex}.items.{$itemIndex}";
                try {
                    $items[] = $this->item($line, $saved->get($line['id'] ?? ''), $path);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([$path => $exception->getMessage()]);
                }
            }
            $days[] = ['kind' => $day['kind'] ?? 'weekday', 'items' => $items];
        }

        usort($days, fn (array $a, array $b): int => ($a['kind'] === 'weekend') <=> ($b['kind'] === 'weekend'));

        return ['days_count' => (int) $plan['days_count'], 'displayed_micros' => $plan['displayed_micros'], 'days' => $days];
    }

    private function item(array $line, ?array $saved, string $path): array
    {
        $foodId = $line['food_item_id'] ?? null;
        $recipeId = $line['recipe_id'] ?? null;
        if (($foodId === null) === ($recipeId === null)) {
            throw new InvalidArgumentException('Choose one Ingredient or Menu Item for each recall item.');
        }
        if ($saved && ($saved['food_item_id'] !== $foodId || $saved['recipe_id'] !== $recipeId)) {
            throw new InvalidArgumentException('Add a new item to change its Food Library source.');
        }
        $snapshot = $saved['nutrient_snapshot'] ?? null;
        if ($foodId !== null) {
            if (isset($line['ingredients'])) {
                throw new InvalidArgumentException('Ingredient adjustments apply only to a Menu Item.');
            }
            if ($snapshot === null) {
                $food = FoodItem::where('uuid', $foodId)->first();
                if (! $food) {
                    throw new InvalidArgumentException('Choose an existing Ingredient.');
                }
                $snapshot = ['name' => $food->name, 'fdc_id' => $food->usda_fdc_id,
                    'calories' => (float) $food->calories, 'protein' => (float) $food->protein,
                    'carbs' => (float) $food->carbs, 'fat' => (float) $food->fat,
                    'water_g' => $food->water_g, 'micronutrients' => $food->micronutrients ?? [],
                    'serving_size' => (float) $food->serving_size, 'serving_unit' => $food->serving_unit];
            }
        } else {
            $recipe = $snapshot ? new Recipe : Recipe::with('ingredients.foodItem')->where('uuid', $recipeId)->first();
            if (! $recipe) {
                throw new InvalidArgumentException('Choose an existing Menu Item.');
            }
            if ($snapshot === null) {
                $snapshot = [...$recipe->planSnapshot(),
                    'prepared_portion_amount' => $recipe->prepared_portion_amount,
                    'prepared_portion_unit' => $recipe->prepared_portion_unit];
            }
            if (isset($line['ingredients'])) {
                $snapshot = [...$snapshot, ...$recipe->planSnapshot([], $snapshot, $line['ingredients'])];
            }
        }
        $unit = UnitConverter::canonical($line['unit']);
        if ($recipeId !== null && $unit !== 'serving') {
            UnitConverter::scalingFactor((float) $line['quantity'], $unit,
                (float) ($snapshot['prepared_portion_amount'] ?? 0), (string) ($snapshot['prepared_portion_unit'] ?? ''));
        } else {
            UnitConverter::scalingFactor((float) $line['quantity'], $unit, (float) $snapshot['serving_size'], (string) $snapshot['serving_unit']);
        }

        return ['id' => $saved['id'] ?? (string) Str::uuid(), 'food_item_id' => $foodId, 'recipe_id' => $recipeId,
            'quantity' => (float) $line['quantity'], 'unit' => $unit, 'nutrient_snapshot' => $snapshot,
            ...(isset($line['ingredients']) ? ['ingredients' => $line['ingredients']] : [])];
    }
}
