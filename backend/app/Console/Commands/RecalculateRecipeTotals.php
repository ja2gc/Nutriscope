<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use Illuminate\Console\Command;

/**
 * Backfill / refresh recipe nutrient totals (macros + water + micronutrients)
 * from their ingredients. Seeded recipes ship with macro totals but no aggregated
 * micronutrients or water, which left meal-plan micro validation reporting
 * "cannot_validate" and the water-with-macros display blank. Idempotent — safe to
 * re-run after any reseed or ingredient edit.
 */
class RecalculateRecipeTotals extends Command
{
    protected $signature = 'recipes:recalculate-totals {--id= : Only recalculate a single recipe id}';

    protected $description = 'Recompute and persist recipe macro/water/micronutrient totals from ingredients';

    public function handle(): int
    {
        $query = Recipe::with('ingredients.foodItem');
        if ($id = $this->option('id')) {
            $query->whereKey($id);
        }

        $recipes = $query->get();
        if ($recipes->isEmpty()) {
            $this->warn('No recipes found.');
            return self::SUCCESS;
        }

        $updated = 0;
        $failures = [];
        $this->withProgressBar($recipes, function (Recipe $recipe) use (&$updated, &$failures) {
            if ($recipe->ingredients->isEmpty()) {
                return; // nothing to aggregate
            }
            try {
                $recipe->recalculateTotals();
                $updated++;
            } catch (\InvalidArgumentException $e) {
                // Unrecognised ingredient unit — surface it rather than corrupt totals.
                $failures[] = "#{$recipe->id} {$recipe->name}: {$e->getMessage()}";
            }
        });
        $this->newLine(2);

        $this->info("Recalculated totals for {$updated} of {$recipes->count()} recipe(s).");

        if (! empty($failures)) {
            $this->warn('Skipped (fix the ingredient/serving units, then re-run):');
            foreach ($failures as $f) {
                $this->line("  • {$f}");
            }
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
