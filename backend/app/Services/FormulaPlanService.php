<?php

namespace App\Services;

use App\Http\Requests\StoreRecipeRequest;
use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlanTemplate;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class FormulaPlanService
{
    public static function rules(): array
    {
        return [
            'formula_items' => ['sometimes', 'nullable', 'array', 'list', 'max:350'],
            'formula_items.*' => ['array:id,recipe_id,portion_multiplier,ingredients,source_template_id,source_template_item_id,source_intervention_id,source_intervention_item_id,reset_recipe,new_formula'],
            'formula_items.*.id' => ['required', 'uuid', 'distinct'],
            'formula_items.*.recipe_id' => ['nullable', 'uuid'],
            'formula_items.*.new_formula' => ['sometimes', 'array:name,category,servings,prep_notes,ingredients,nutrition_label'],
            'formula_items.*.portion_multiplier' => ['required', 'numeric', 'between:0.000001,100000', 'decimal:0,6'],
            'formula_items.*.reset_recipe' => ['sometimes', 'boolean'],
            'formula_items.*.source_template_id' => ['sometimes', 'nullable', 'uuid'],
            'formula_items.*.source_template_item_id' => ['sometimes', 'nullable', 'uuid'],
            'formula_items.*.source_intervention_id' => ['sometimes', 'nullable', 'uuid'],
            'formula_items.*.source_intervention_item_id' => ['sometimes', 'nullable', 'uuid'],
            'formula_items.*.ingredients' => ['sometimes', 'array', 'list', 'min:1'],
            'formula_items.*.ingredients.*' => ['array:food_item_id,quantity,unit'],
            'formula_items.*.ingredients.*.food_item_id' => ['required', 'uuid'],
            'formula_items.*.ingredients.*.quantity' => ['required', 'numeric', 'between:0.000001,100000', 'decimal:0,6'],
            'formula_items.*.ingredients.*.unit' => ['required', 'string', 'max:50'],
        ];
    }

    public function normalize(array $items, array $previous, int $actorId, ?int $ncpRecordId = null): array
    {
        $saved = collect($previous)->keyBy('id');

        return collect($items)->map(function (array $line, int $index) use ($saved, $actorId, $ncpRecordId): array {
            $source = $saved->get($line['id']);
            if ($source && $source['recipe_id'] !== ($line['recipe_id'] ?? null)) {
                throw ValidationException::withMessages(["formula_items.{$index}" => 'Remove this item before choosing a different formula.']);
            }
            $fromTemplate = false;
            if (! $source && ! empty($line['new_formula']) && ! empty($line['recipe_id'])) {
                throw ValidationException::withMessages(["formula_items.{$index}" => 'Choose an existing formula or create a new one.']);
            }
            if (! $source && ! empty($line['source_intervention_id'])) {
                $plan = Intervention::where('uuid', $line['source_intervention_id'])->where('nutrition_support', 'tube_feeding')
                    ->whereHas('ncpRecord', fn ($query) => $query->where('rnd_user_id', $actorId))
                    ->when($ncpRecordId !== null, fn ($query) => $query->where('ncp_record_id', $ncpRecordId))->firstOrFail();
                $source = collect($plan->formula_items ?? [])->firstWhere('id', $line['source_intervention_item_id'] ?? '');
                if (! $source || $source['recipe_id'] !== ($line['recipe_id'] ?? null)) {
                    throw ValidationException::withMessages(["formula_items.{$index}" => 'Choose a formula belonging to this intervention.']);
                }
                $fromTemplate = true;
            }
            if (! $source && ! empty($line['source_template_id'])) {
                $template = MealPlanTemplate::query()->where('uuid', $line['source_template_id'])->where('rnd_user_id', $actorId)->where('nutrition_support', 'tube_feeding')->firstOrFail();
                $source = collect($template->formula_items ?? [])->firstWhere('id', $line['source_template_item_id'] ?? '');
                if (! $source || $source['recipe_id'] !== ($line['recipe_id'] ?? null)) {
                    throw ValidationException::withMessages(["formula_items.{$index}" => 'Choose a formula belonging to this template.']);
                }
                $fromTemplate = true;
            }
            $recipe = $source ? new Recipe : (! empty($line['new_formula'])
                ? $this->draftRecipe($line['new_formula'])
                : Recipe::with('ingredients.foodItem')->where('uuid', $line['recipe_id'] ?? '')->where('category', 'tube_feeding')->first());
            if (! $recipe) {
                throw ValidationException::withMessages(["formula_items.{$index}" => 'Choose a Tube Feeding Menu Item with measured ingredients and a valid yield.']);
            }
            try {
                $snapshot = $source['nutrient_snapshot'] ?? $recipe->planSnapshot();
                $original = $fromTemplate ? $snapshot : ($source['original_snapshot'] ?? $snapshot);
                if (! empty($line['reset_recipe'])) {
                    $snapshot = $original;
                } elseif (isset($line['ingredients'])) {
                    if (! empty($line['new_formula']['nutrition_label'])) {
                        $line['ingredients'] = array_map(fn ($row) => [...$row, 'food_item_id' => $snapshot['recipe_original_ingredients'][0]['food_item']['id']], $line['ingredients']);
                    }
                    $snapshot = $recipe->planSnapshot([], $snapshot, $line['ingredients']);
                }
                if (($snapshot['recipe_yield'] ?? 0) <= 0 || empty($snapshot['recipe_original_ingredients'])) {
                    throw new InvalidArgumentException('Correct the formula yield and measured ingredients before using it.');
                }
            } catch (InvalidArgumentException $error) {
                throw ValidationException::withMessages(["formula_items.{$index}" => $error->getMessage()]);
            }

            return ['id' => $line['id'], 'recipe_id' => $line['recipe_id'] ?? null,
                'portion_multiplier' => ! empty($line['reset_recipe']) ? 1.0 : (float) $line['portion_multiplier'],
                'nutrient_snapshot' => $snapshot, 'original_snapshot' => $original];
        })->all();
    }

    private function draftRecipe(array $input): Recipe
    {
        $request = StoreRecipeRequest::create('/', 'POST', [...$input, 'category' => 'tube_feeding']);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();
        $data = $request->validated();
        $recipe = new Recipe(collect($data)->only(['name', 'category', 'servings', 'prep_notes'])->all());
        if (isset($data['nutrition_label'])) {
            $food = new FoodItem(['name' => $data['name'], ...$data['nutrition_label']]);
            $food->forceFill(['uuid' => (string) Str::uuid()]);
            $rows = [(new RecipeIngredient(['quantity' => (float) $food->serving_size * $data['servings'], 'unit' => $food->serving_unit]))->setRelation('foodItem', $food)];
        } else {
            $foods = FoodItem::whereIn('id', array_column($data['ingredients'], 'food_item_id'))->get()->keyBy('id');
            $rows = array_map(fn (array $row) => (new RecipeIngredient($row))->setRelation('foodItem', $foods->get($row['food_item_id'])), $data['ingredients']);
        }
        foreach ($rows as $index => $row) {
            $row->forceFill(['id' => $index + 1]);
        }

        return $recipe->setRelation('ingredients', new Collection($rows));
    }
}
