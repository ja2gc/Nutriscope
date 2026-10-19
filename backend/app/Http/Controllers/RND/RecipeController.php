<?php

namespace App\Http\Controllers\RND;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\FoodItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\FoodItemAuditValues;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use App\Services\Audit\Revisions\AuditRevisionWriter;
use App\Support\Search\RankedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AuditRevisionRegistry $revisionRegistry,
        private readonly AuditRevisionWriter $revisionWriter,
    ) {}

    public function index(PaginatedRequest $request): AnonymousResourceCollection
    {
        $query = Recipe::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        RankedSearch::apply($query, $request->string('search')->toString(), ['name']);

        $recipes = $query->orderBy('name')->orderBy('id')->paginate($request->perPage())->withQueryString();

        return RecipeResource::collection($recipes);
    }

    public function store(StoreRecipeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $recipe = $this->audited(function () use ($request, $data): Recipe {
            if (isset($data['nutrition_label'])) {
                $food = FoodItem::create(['name' => $data['name'], ...$data['nutrition_label']]);
                $auditValues = app(FoodItemAuditValues::class);
                $values = $auditValues->values($food);
                $this->auditLogger->recordMutation(
                    AuditAction::Created,
                    AuditDomain::NutritionLibrary,
                    $food,
                    array_keys(array_filter($values, fn (mixed $value): bool => $value !== null)),
                    ['source' => $auditValues->source($food), 'entity_name' => $food->name],
                    oldValues: array_fill_keys(array_keys($values), null),
                    newValues: $values,
                );
                $data['ingredients'] = [[
                    'food_item_id' => $food->id,
                    'quantity' => round((float) $food->serving_size * (int) $data['servings'], 6),
                    'unit' => $food->serving_unit,
                ]];
            }
            $recipe = Recipe::create([
                'rnd_user_id' => $request->user()->id,
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'meal_types' => $data['meal_types'] ?? null,
                'prep_notes' => $data['prep_notes'] ?? null,
                'servings' => $data['servings'],
            ]);

            $this->syncIngredients($recipe, $data['ingredients'] ?? []);
            $recipe->recalculateTotals();
            $recipe->load('ingredients.foodItem');
            $fields = array_keys($recipe->getAttributes());
            if ($recipe->ingredients()->exists()) {
                $fields[] = 'ingredients';
            }
            $activity = $this->auditLogger->recordMutation(
                AuditAction::Created,
                AuditDomain::NutritionLibrary,
                $recipe,
                array_map(fn (string $field): string => $field === 'prep_notes' ? 'content' : $field, $fields),
                ['entity_name' => $recipe->name],
            );
            if ($activity !== null) {
                $this->revisionWriter->write($activity, null, $this->revisionRegistry->capture($recipe));
            }

            return $recipe;
        });

        $recipe->load('ingredients.foodItem');

        return (new RecipeResource($recipe))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Recipe $recipe): RecipeResource
    {
        $recipe->load('ingredients.foodItem');

        return new RecipeResource($recipe);
    }

    public function update(StoreRecipeRequest $request, Recipe $recipe): RecipeResource
    {
        $data = $request->validated();
        $this->audited(function () use ($recipe, $data): void {
            $oldValues = $this->auditValues($recipe);
            $ingredientsSubmitted = array_key_exists('ingredients', $data);
            $mealTypesSubmitted = array_key_exists('meal_types', $data);
            $structural = $ingredientsSubmitted || $mealTypesSubmitted;
            $beforeIngredients = $ingredientsSubmitted ? $this->ingredientSignature($recipe) : [];
            $beforeMealTypes = $mealTypesSubmitted ? ($recipe->meal_types ?? []) : [];
            $before = $structural
                ? $this->revisionRegistry->capture($recipe->load('ingredients.foodItem'))
                : null;
            $recipe->update([
                'name' => $data['name'] ?? $recipe->name,
                'category' => $data['category'] ?? $recipe->category,
                'meal_types' => $data['meal_types'] ?? $recipe->meal_types,
                'prep_notes' => array_key_exists('prep_notes', $data) ? $data['prep_notes'] : $recipe->prep_notes,
                'servings' => $data['servings'] ?? $recipe->servings,
            ]);

            $fields = array_keys($recipe->getChanges());
            $structureChanged = $mealTypesSubmitted && $beforeMealTypes !== ($recipe->meal_types ?? []);
            if ($ingredientsSubmitted) {
                $this->syncIngredients($recipe, $data['ingredients']);
                $recipe->recalculateTotals();
                $ingredientsChanged = $beforeIngredients !== $this->ingredientSignature($recipe);
                $structureChanged = $structureChanged || $ingredientsChanged;
                if ($ingredientsChanged) {
                    $fields[] = 'ingredients';
                }
            }
            $presentedFields = array_map(fn (string $field): string => $field === 'prep_notes' ? 'content' : $field, $fields);
            $safeFieldMap = array_flip(array_intersect(array_keys($oldValues), $presentedFields));
            $activity = $this->auditLogger->recordMutation(
                AuditAction::Updated,
                AuditDomain::NutritionLibrary,
                $recipe,
                $presentedFields,
                ['entity_name' => $recipe->name],
                oldValues: array_intersect_key($oldValues, $safeFieldMap),
                newValues: array_intersect_key($this->auditValues($recipe), $safeFieldMap),
            );
            if ($activity !== null && $structureChanged && $before !== null) {
                $afterRecipe = $recipe->fresh(['ingredients.foodItem']);
                $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($afterRecipe));
            }
        });

        $recipe->load('ingredients.foodItem');

        return new RecipeResource($recipe->fresh(['ingredients.foodItem']));
    }

    public function destroy(Recipe $recipe): JsonResponse
    {
        $this->audited(function () use ($recipe): void {
            $before = $this->revisionRegistry->capture($recipe->load('ingredients.foodItem'));
            $referenced = collect(['meal_plan_items', 'meal_plan_template_items', 'meal_plan_template_days'])
                ->contains(fn (string $table): bool => DB::table($table)->where('recipe_id', $recipe->id)->exists());
            $referenced ? $recipe->delete() : $recipe->forceDelete();
            $activity = $this->auditLogger->recordMutation(
                AuditAction::Deleted,
                AuditDomain::NutritionLibrary,
                $recipe,
                [],
                ['entity_name' => $recipe->name],
            );
            if ($activity !== null) {
                $this->revisionWriter->write($activity, $before, null);
            }
        });

        return response()->json(null, 204);
    }

    private function syncIngredients(Recipe $recipe, array $ingredients): void
    {
        $recipe->ingredients()->delete();

        foreach ($ingredients as $ing) {
            RecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'food_item_id' => $ing['food_item_id'],
                'quantity' => $ing['quantity'],
                'unit' => $ing['unit'],
            ]);
        }
    }

    private function ingredientSignature(Recipe $recipe): array
    {
        return $recipe->ingredients()->orderBy('food_item_id')->orderBy('id')
            ->get(['food_item_id', 'quantity', 'unit'])
            ->map->only(['food_item_id', 'quantity', 'unit'])
            ->values()->all();
    }

    /** @return array{name: string, category: ?string, servings: int} */
    private function auditValues(Recipe $recipe): array
    {
        return [
            'name' => (string) $recipe->name,
            'category' => $recipe->category,
            'servings' => (int) $recipe->servings,
        ];
    }
}
