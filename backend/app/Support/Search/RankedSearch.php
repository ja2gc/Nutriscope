<?php

namespace App\Support\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class RankedSearch
{
    private const MAX_FUZZY_CANDIDATES = 500;

    /**
     * Apply exact > prefix > substring relevance, with a bounded typo fallback.
     *
     * @param  list<string>  $columns
     * @return Builder<Model>
     */
    public static function apply(Builder $query, ?string $term, array $columns): Builder
    {
        $term = FuzzyText::normalize($term);

        if ($term === '' || $columns === []) {
            return $query;
        }

        $escaped = addcslashes($term, '\\%_');
        $matching = clone $query;
        $matching->where(function (Builder $where) use ($columns, $escaped): void {
            foreach ($columns as $column) {
                $where->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '\\\\'", ['%'.$escaped.'%']);
            }
        });

        if ($matching->exists()) {
            $query->where(function (Builder $where) use ($columns, $escaped): void {
                foreach ($columns as $column) {
                    $where->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '\\\\'", ['%'.$escaped.'%']);
                }
            });

            $bindings = [];
            $scores = [];
            $columnCount = count($columns);
            foreach ($columns as $priority => $column) {
                $prefixScore = $columnCount + $priority;
                $substringScore = ($columnCount * 2) + $priority;
                $scores[] = "CASE WHEN LOWER({$column}) = ? THEN {$priority} WHEN LOWER({$column}) LIKE ? ESCAPE '\\\\' THEN {$prefixScore} WHEN LOWER({$column}) LIKE ? ESCAPE '\\\\' THEN {$substringScore} ELSE 999 END";
                $bindings[] = $term;
                $bindings[] = $escaped.'%';
                $bindings[] = '%'.$escaped.'%';
            }

            $ranking = count($scores) === 1 ? $scores[0] : 'LEAST('.implode(', ', $scores).')';

            return $query->orderByRaw($ranking, $bindings);
        }

        return self::applyFuzzyFallback($query, $term, $columns);
    }

    /** @param list<string> $columns */
    private static function applyFuzzyFallback(Builder $query, string $term, array $columns): Builder
    {
        $model = $query->getModel();
        $key = $model->getKeyName();
        $candidates = (clone $query)
            ->reorder()
            ->limit(self::MAX_FUZZY_CANDIDATES)
            ->get(array_values(array_unique([$key, ...$columns])));

        $matches = $candidates->map(function (Model $candidate) use ($term, $columns, $key): ?array {
            $best = null;
            foreach ($columns as $column) {
                $score = FuzzyText::score($term, (string) $candidate->getAttribute($column));
                $best = $score !== null && ($best === null || $score < $best) ? $score : $best;
            }

            return $best === null ? null : ['id' => $candidate->getAttribute($key), 'score' => $best];
        })->filter()->sortBy(['score', 'id'])->values();

        if ($matches->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $ids = $matches->pluck('id')->all();
        $qualifiedKey = $model->qualifyColumn($key);
        $cases = implode(' ', array_map(
            fn (int $position): string => "WHEN ? THEN {$position}",
            array_keys($ids),
        ));

        return $query
            ->whereKey($ids)
            ->orderByRaw("CASE {$qualifiedKey} {$cases} ELSE ".count($ids).' END', $ids);
    }
}
