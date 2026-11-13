<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use Illuminate\Console\Command;

/**
 * Backfill / refresh recipe nutrient totals (macros + water + micronutrients)
 * from their ingredients. Current seeders calculate these totals on write;
 * this command refreshes older records after verified ingredient corrections.
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
