<?php

namespace App\Services\Audit\Revisions\Serializers;

use App\Data\AuditHistoryFieldDto;
use App\Data\AuditHistorySnapshotDto;
use App\Data\AuditHistoryTableDto;
use App\Data\AuditHistoryTableRowDto;
use App\Data\AuditRevisionSnapshot;
use App\Data\AuditValueDto;
use App\Models\FoodServiceRecipe;
use App\Services\Audit\Contracts\AuditRevisionSerializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FoodServiceRecipeRevisionSerializer implements AuditRevisionSerializer
{
    public function key(): string
    {
        return 'food_service_recipe';
    }

    public function subjectType(): string
    {
        return FoodServiceRecipe::class;
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function capture(Model $subject): AuditRevisionSnapshot
    {
        if (! $subject instanceof FoodServiceRecipe) {
            throw new InvalidArgumentException('Food service recipe serializer requires a food service recipe.');
        }
        $subject->loadMissing('ingredients.fsItem');
        $occurrences = [];
        $ingredients = $subject->ingredients->values()->map(function ($ingredient, int $index) use (&$occurrences): array {
            $item = $ingredient->fsItem;
            $reference = $item?->uuid;
            $baseKey = is_string($reference) && Str::isUuid($reference)
                ? strtolower($reference)
                : 'ingredient-'.($index + 1);
            $occurrences[$baseKey] = ($occurrences[$baseKey] ?? 0) + 1;

            return [
                'key' => $baseKey.'-'.$occurrences[$baseKey],
                'ingredient' => $item?->name ?? 'Unavailable ingredient',
                'reference' => is_string($reference) && Str::isUuid($reference) ? strtolower($reference) : null,
                'quantity' => (float) $ingredient->quantity,
                'unit' => (string) $ingredient->unit,
                'catalog_unit' => $item?->base_unit,
                'unit_cost' => $item !== null ? (float) $item->unit_cost : null,
            ];
        })->all();

        return new AuditRevisionSnapshot(
            serializer: $this->key(),
            subjectType: FoodServiceRecipe::class,
            subjectPublicId: (string) $subject->uuid,
            schemaVersion: $this->schemaVersion(),
            payload: [
                'title' => (string) $subject->name,
                'name' => (string) $subject->name,
                'reference' => (string) $subject->uuid,
                'category' => $subject->category,
                'servings' => (int) $subject->servings,
                'prep_notes' => $subject->prep_notes,
                'cost' => (float) $subject->cost,
                'ingredients' => $ingredients,
            ],
        );
    }

    public function present(array $snapshot): AuditHistorySnapshotDto
    {
        $this->assertValidPayload($snapshot);

        return new AuditHistorySnapshotDto(
            type: $this->key(),
            title: $snapshot['title'],
            reference: $snapshot['reference'],
            fields: [
                new AuditHistoryFieldDto('name', 'Name', new AuditValueDto('text', $snapshot['name'])),
                new AuditHistoryFieldDto('category', 'Category', new AuditValueDto('enum', $snapshot['category'])),
                new AuditHistoryFieldDto('servings', 'Servings', new AuditValueDto('number', $snapshot['servings'])),
                new AuditHistoryFieldDto('prep_notes', 'Preparation notes', new AuditValueDto('text', $snapshot['prep_notes'])),
                new AuditHistoryFieldDto('cost', 'Estimated cost', new AuditValueDto('currency', $snapshot['cost'], currency: 'PHP')),
            ],
            tables: [
                new AuditHistoryTableDto(
                    key: 'ingredients',
                    label: 'Ingredients',
                    columns: [
                        'ingredient' => 'Ingredient',
                        'quantity' => 'Quantity',
                        'unit' => 'Unit',
                        'catalog_unit' => 'Catalog unit',
                        'unit_cost' => 'Unit cost',
                    ],
                    rows: array_map(fn (array $ingredient): AuditHistoryTableRowDto => new AuditHistoryTableRowDto(
                        key: $ingredient['key'],
                        values: [
                            'ingredient' => new AuditValueDto('text', $ingredient['ingredient']),
                            'quantity' => new AuditValueDto('number', $ingredient['quantity']),
                            'unit' => new AuditValueDto('text', $ingredient['unit']),
                            'catalog_unit' => new AuditValueDto('text', $ingredient['catalog_unit']),
                            'unit_cost' => new AuditValueDto('currency', $ingredient['unit_cost'], currency: 'PHP'),
                        ],
                    ), $snapshot['ingredients']),
                ),
            ],
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function assertValidPayload(array $snapshot): void
    {
        $valid = $this->hasExactKeys($snapshot, ['title', 'name', 'reference', 'category', 'servings', 'prep_notes', 'cost', 'ingredients'])
            && is_string($snapshot['title']) && trim($snapshot['title']) !== ''
            && is_string($snapshot['name']) && trim($snapshot['name']) !== ''
            && is_string($snapshot['reference']) && Str::isUuid($snapshot['reference'])
            && ($snapshot['category'] === null || is_string($snapshot['category']))
            && is_int($snapshot['servings']) && $snapshot['servings'] > 0
            && ($snapshot['prep_notes'] === null || is_string($snapshot['prep_notes']))
            && (is_int($snapshot['cost']) || is_float($snapshot['cost']))
            && is_array($snapshot['ingredients']) && array_is_list($snapshot['ingredients'])
            && collect($snapshot['ingredients'])->every(fn (mixed $ingredient): bool => is_array($ingredient)
                && $this->hasExactKeys($ingredient, ['key', 'ingredient', 'reference', 'quantity', 'unit', 'catalog_unit', 'unit_cost'])
                && is_string($ingredient['key']) && trim($ingredient['key']) !== ''
                && is_string($ingredient['ingredient']) && trim($ingredient['ingredient']) !== ''
                && ($ingredient['reference'] === null || (is_string($ingredient['reference']) && Str::isUuid($ingredient['reference'])))
                && (is_int($ingredient['quantity']) || is_float($ingredient['quantity']))
                && is_string($ingredient['unit']) && trim($ingredient['unit']) !== ''
                && ($ingredient['catalog_unit'] === null || (is_string($ingredient['catalog_unit']) && trim($ingredient['catalog_unit']) !== ''))
                && ($ingredient['unit_cost'] === null || is_int($ingredient['unit_cost']) || is_float($ingredient['unit_cost'])));

        if (! $valid) {
            throw new InvalidArgumentException('Invalid food service recipe revision payload.');
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
