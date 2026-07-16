<?php

namespace App\Services\Audit\Revisions\Serializers;

use App\Data\AuditHistoryFieldDto;
use App\Data\AuditHistorySnapshotDto;
use App\Data\AuditHistoryTableDto;
use App\Data\AuditHistoryTableRowDto;
use App\Data\AuditRevisionSnapshot;
use App\Data\AuditValueDto;
use App\Models\Recipe;
use App\Services\Audit\Contracts\AuditRevisionSerializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RndRecipeRevisionSerializer implements AuditRevisionSerializer
{
    public function key(): string
    {
        return 'rnd_recipe';
    }

    public function subjectType(): string
    {
        return Recipe::class;
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function capture(Model $subject): AuditRevisionSnapshot
    {
        if (! $subject instanceof Recipe) {
            throw new InvalidArgumentException('RND recipe serializer requires a recipe.');
        }
        $subject->loadMissing('ingredients.foodItem');
        $occurrences = [];
        $ingredients = $subject->ingredients->values()->map(function ($ingredient, int $index) use (&$occurrences): array {
            $reference = $ingredient->foodItem?->uuid;
            $baseKey = is_string($reference) && Str::isUuid($reference) ? strtolower($reference) : 'ingredient-'.($index + 1);
            $occurrences[$baseKey] = ($occurrences[$baseKey] ?? 0) + 1;

            return [
                'key' => $baseKey.'-'.$occurrences[$baseKey],
                'ingredient' => $ingredient->foodItem?->name ?? 'Unavailable ingredient',
                'reference' => is_string($reference) && Str::isUuid($reference) ? strtolower($reference) : null,
                'quantity' => (float) $ingredient->quantity,
                'unit' => (string) $ingredient->unit,
            ];
        })->all();

        return new AuditRevisionSnapshot(
            serializer: $this->key(),
            subjectType: Recipe::class,
            subjectPublicId: (string) $subject->uuid,
            schemaVersion: $this->schemaVersion(),
            payload: [
                'title' => (string) $subject->name,
                'name' => (string) $subject->name,
                'reference' => (string) $subject->uuid,
                'category' => $subject->category,
                'meal_types' => collect($subject->meal_types ?? [])
                    ->filter(fn (mixed $mealType): bool => is_string($mealType))
                    ->unique()->values()->all(),
                'servings' => (int) $subject->servings,
                'prep_notes' => $subject->prep_notes,
                'totals' => [
                    'calories' => (float) $subject->total_calories,
                    'protein' => (float) $subject->total_protein,
                    'carbs' => (float) $subject->total_carbs,
                    'fat' => (float) $subject->total_fat,
                    'cost' => (float) $subject->cost,
                ],
                'ingredients' => $ingredients,
            ],
        );
    }

    public function present(array $snapshot): AuditHistorySnapshotDto
    {
        if (! array_key_exists('meal_types', $snapshot)) {
            $snapshot['meal_types'] = [];
        }
        $this->assertValidPayload($snapshot);
        $totals = $snapshot['totals'];

        return new AuditHistorySnapshotDto(
            type: $this->key(),
            title: $snapshot['title'],
            reference: $snapshot['reference'],
            fields: [
                new AuditHistoryFieldDto('name', 'Name', new AuditValueDto('text', $snapshot['name'])),
                new AuditHistoryFieldDto('category', 'Category', new AuditValueDto('enum', $snapshot['category'])),
                new AuditHistoryFieldDto('meal_types', 'Meal types', new AuditValueDto('field_list', $snapshot['meal_types'])),
                new AuditHistoryFieldDto('servings', 'Servings', new AuditValueDto('number', $snapshot['servings'])),
                new AuditHistoryFieldDto('prep_notes', 'Preparation notes', new AuditValueDto('text', $snapshot['prep_notes'])),
                new AuditHistoryFieldDto('calories', 'Energy', new AuditValueDto('quantity', $totals['calories'], 'kcal')),
                new AuditHistoryFieldDto('protein', 'Protein', new AuditValueDto('quantity', $totals['protein'], 'g')),
                new AuditHistoryFieldDto('carbs', 'Carbohydrate', new AuditValueDto('quantity', $totals['carbs'], 'g')),
                new AuditHistoryFieldDto('fat', 'Fat', new AuditValueDto('quantity', $totals['fat'], 'g')),
                new AuditHistoryFieldDto('cost', 'Estimated cost', new AuditValueDto('currency', $totals['cost'], currency: 'PHP')),
            ],
            tables: [
                new AuditHistoryTableDto(
                    key: 'ingredients',
                    label: 'Ingredients',
                    columns: ['ingredient' => 'Ingredient', 'quantity' => 'Quantity', 'unit' => 'Unit'],
                    rows: array_map(fn (array $ingredient): AuditHistoryTableRowDto => new AuditHistoryTableRowDto(
                        key: $ingredient['key'],
                        values: [
                            'ingredient' => new AuditValueDto('text', $ingredient['ingredient']),
                            'quantity' => new AuditValueDto('number', $ingredient['quantity']),
                            'unit' => new AuditValueDto('text', $ingredient['unit']),
                        ],
                    ), $snapshot['ingredients']),
                ),
            ],
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function assertValidPayload(array $snapshot): void
    {
        $valid = $this->hasExactKeys($snapshot, ['title', 'name', 'reference', 'category', 'meal_types', 'servings', 'prep_notes', 'totals', 'ingredients'])
            && is_string($snapshot['title']) && trim($snapshot['title']) !== ''
            && is_string($snapshot['name']) && trim($snapshot['name']) !== ''
            && is_string($snapshot['reference']) && Str::isUuid($snapshot['reference'])
            && ($snapshot['category'] === null || is_string($snapshot['category']))
            && is_array($snapshot['meal_types']) && array_is_list($snapshot['meal_types'])
            && collect($snapshot['meal_types'])->every(fn (mixed $mealType): bool => is_string($mealType)
                && preg_match('/^[a-z_]{1,32}$/D', $mealType) === 1)
            && is_int($snapshot['servings']) && $snapshot['servings'] > 0
            && ($snapshot['prep_notes'] === null || is_string($snapshot['prep_notes']))
            && is_array($snapshot['totals'])
            && $this->hasExactKeys($snapshot['totals'], ['calories', 'protein', 'carbs', 'fat', 'cost'])
            && collect($snapshot['totals'])->every(fn (mixed $value): bool => is_int($value) || is_float($value))
            && is_array($snapshot['ingredients']) && array_is_list($snapshot['ingredients'])
            && collect($snapshot['ingredients'])->every(fn (mixed $ingredient): bool => is_array($ingredient)
                && $this->hasExactKeys($ingredient, ['key', 'ingredient', 'reference', 'quantity', 'unit'])
                && is_string($ingredient['key']) && trim($ingredient['key']) !== ''
                && is_string($ingredient['ingredient']) && trim($ingredient['ingredient']) !== ''
                && ($ingredient['reference'] === null || (is_string($ingredient['reference']) && Str::isUuid($ingredient['reference'])))
                && (is_int($ingredient['quantity']) || is_float($ingredient['quantity']))
                && is_string($ingredient['unit']) && trim($ingredient['unit']) !== '');

        if (! $valid) {
            throw new InvalidArgumentException('Invalid RND recipe revision payload.');
        }
    }

    /** @param list<string> $expected */
    private function hasExactKeys(array $value, array $expected): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }
}
