<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Recipe;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MealPlanService
{
    private const FOOD_MATCHING_TARGET_KEYS = ['energy', 'protein', 'carbs', 'fat'];

    /**
     * Flat penalty added to macro-distance score for each micronutrient
     * that exceeds its 'max' limit in the intervention's micronutrient_limits.
     * Using 1.0 ensures any single limit violation pushes a recipe below
     * a perfectly-matched macro recipe (macro-ratio distance is 0–√3 ≈ 1.73).
     */
    private const MICRO_PENALTY_PER_EXCESS = 1.0;

    /**
     * Per-day recency penalty weight. A recipe used N days ago receives
     * (RECENCY_LOOK_BACK - N) * RECENCY_PENALTY added to its score.
     * Macro distance is 0–√3 ≈ 1.73, so 0.40/day is enough to push
     * a recently-used recipe out of the top-3 window without excluding
     * it entirely when the pool is small.
     */
    private const RECENCY_LOOK_BACK = 3;

    private const RECENCY_PENALTY = 0.40;

    // Approximate % of daily energy per meal slot
    private const SLOT_DISTRIBUTION = [
        'breakfast' => 0.25,
        'am_snack' => 0.10,
        'lunch' => 0.30,
        'pm_snack' => 0.10,
        'dinner' => 0.25,
    ];

    private const NO_SNACK_SLOT_DISTRIBUTION = [
        'breakfast' => 0.3125,
        'lunch' => 0.375,
        'dinner' => 0.3125,
    ];

    // Tolerance threshold for post-generation validation (10%)
    private const TOLERANCE = 0.10;

    // Maximum reconciliation iterations per flagged day
    private const MAX_RECONCILE_ITERATIONS = 3;

    /**
     * Optional RNG seed. Set before calling generate() in tests for reproducibility.
     */
    private ?int $rngSeed = null;

    public function setRngSeed(?int $seed): void
    {
        $this->rngSeed = $seed;
    }

    /**
     * Scale only the quantities already present in a plan. Foods are never inserted
     * or substituted; practical-unit rounding is followed by a bounded local search.
     * Fluid is deliberately excluded because a food plan is not a beverage schedule.
     *
     * @return array{changed_items:int,inserted_items:int,substituted_items:int,problem_days:list<string>,variance:array<string,array<string,mixed>>}
     */
    public function scaleToPrescription(MealPlan $mealPlan, Intervention $intervention, bool $persist = true): array
    {
        $targets = array_filter([
            'energy' => (float) $intervention->energy_kcal,
            'protein' => (float) $intervention->protein_g,
            'carbs' => (float) $intervention->carbs_g,
            'fat' => (float) $intervention->fat_g,
        ], fn (float $value): bool => $value > 0);
        $microLimits = is_array($intervention->micronutrient_limits) ? $intervention->micronutrient_limits : [];

        return DB::transaction(function () use ($mealPlan, $targets, $microLimits, $persist): array {
            if ($persist) {
                $mealPlan->load('days.items');
            }
            $changed = 0;
            $problemDays = [];
            $varianceByDay = [];

            foreach ($mealPlan->days->groupBy('day_of_week') as $dayName => $slots) {
                $items = $slots->flatMap->items->values();
                $overrides = $items->mapWithKeys(fn (MealPlanItem $item): array => [
                    $item->id => (float) $item->quantity,
                ])->all();

                if ($items->isNotEmpty()) {
                    $totals = $this->nutrientTotals($items, $overrides);
                    $ratios = collect($targets)->map(
                        fn (float $target, string $key): float => ($totals[$key] ?? 0) / $target
                    )->filter(fn (float $ratio): bool => $ratio > 0)->values();
                    $denominator = $ratios->sum(fn (float $ratio): float => $ratio ** 2);
                    $scale = $denominator > 0
                        ? min(3.0, max(0.5, $ratios->sum() / $denominator))
                        : 1.0;

                    foreach ($items as $item) {
                        $overrides[$item->id] = $this->snapQuantity($item, (float) $item->quantity * $scale);
                    }

                    // Search neighboring permitted increments without changing plan composition.
                    for ($pass = 0; $pass < 3; $pass++) {
                        foreach ($items as $item) {
                            $step = $this->quantityStep($item);
                            $current = $overrides[$item->id];
                            $best = $current;
                            $bestScore = $this->scalingScore($items, $overrides, $targets, $microLimits);
                            $minimum = $this->minimumQuantity($item);
                            foreach (array_unique([max($minimum, $current - $step), $current, $current + $step]) as $candidate) {
                                $trial = $overrides;
                                $trial[$item->id] = $candidate;
                                $score = $this->scalingScore($items, $trial, $targets, $microLimits);
                                if ($score + 0.000001 < $bestScore) {
                                    $best = $candidate;
                                    $bestScore = $score;
                                }
                            }
                            $overrides[$item->id] = $best;
                        }
                    }

                    foreach ($items as $item) {
                        $next = $overrides[$item->id];
                        if (abs((float) $item->quantity - $next) > 0.0001) {
                            $item->fill(['quantity' => $next]);
                            if ($persist) {
                                $item->saveQuietly();
                            }
                            $changed++;
                        }
                    }
                }

                if ($persist) {
                    $slots->each->load('items');
                }
                $variance = $this->computeDayVariance($slots, $targets, $microLimits);
                $flagged = $this->isFlagged($variance);
                foreach ($slots as $slot) {
                    $slot->fill(['flagged' => $flagged, 'variance' => $variance]);
                    if ($persist) {
                        $slot->saveQuietly();
                    }
                }
                $varianceByDay[$dayName] = $variance;
                if ($flagged) {
                    $problemDays[] = $dayName;
                }
            }

            $mealPlan->fill(['needs_rescaling' => false, 'scaled_at' => now()]);
            if ($persist) {
                $mealPlan->saveQuietly();
            }

            return [
                'changed_items' => $changed,
                'inserted_items' => 0,
                'substituted_items' => 0,
                'problem_days' => array_values($problemDays),
                'variance' => $varianceByDay,
            ];
        });
    }

    public function generate(
        NcpRecord $ncpRecord,
        string $weekStartDate,
        array $conditions = [],
        array $allergens = [],
        bool $excludeSnacks = false,
        bool $excludeCarbs = false,
        ?Intervention $intervention = null,
        ?string $carbMenuItemId = null,
    ): array|MealPlan {
        $intervention ??= $ncpRecord->intervention()->firstOrFail();
        abort_unless($intervention->ncp_record_id === $ncpRecord->id, 404);
        $goalType = (string) ($intervention->goal_type ?? '');
        $assessmentAllergens = $ncpRecord->assessment?->allergies ?? [];
        $allergens = array_map(fn ($value): string => strtolower(trim((string) $value)), array_merge(
            is_array($assessmentAllergens) ? $assessmentAllergens : [], $allergens,
        ));
        $safe = static function (Recipe $recipe) use ($allergens): bool {
            foreach ($recipe->ingredients as $ingredient) {
                $foodAllergens = $ingredient->foodItem?->allergens ?? [];
                if (array_intersect($allergens, array_map('strtolower', is_array($foodAllergens) ? $foodAllergens : [])) !== []) {
                    return false;
                }
            }

            return true;
        };
        $recipeModels = Recipe::with('ingredients.foodItem')->get();
        $selectedCarb = null;
        if (! $excludeCarbs) {
            $selected = $recipeModels->firstWhere('uuid', $carbMenuItemId);
            if (! $selected || $selected->category !== 'carb' || ! $selected->hasValidPortionData() || (float) $selected->total_calories <= 0) {
                return ['invalid_carb' => true, 'message' => 'Choose a Carb Menu Item with valid measured Ingredients, yield, and positive energy, or select Exclude carbs.'];
            }
            if (! $safe($selected)) {
                return ['invalid_carb' => true, 'message' => 'The selected Carb contains a patient allergen. Choose another Carb Menu Item or select Exclude carbs.'];
            }
            $selectedCarb = $this->recipeToCandidate($selected);
        }
        $recipeModels = $recipeModels->filter(fn (Recipe $recipe): bool => in_array($recipe->category, ['meal', 'carb', 'snack'], true)
            && $recipe->hasValidPortionData() && (float) $recipe->total_calories > 0 && $safe($recipe))->values();
        if ($recipeModels->count() < 5) {
            return [
                'insufficient_recipes' => true, 'count' => $recipeModels->count(),
                'message' => "Only {$recipeModels->count()} matching Menu Items have valid measured Ingredients, positive energy, and yield (need at least 5). Correct or add Menu Items, then regenerate.",
            ];
        }
        $recipes = $recipeModels->map(fn (Recipe $recipe): object => $this->recipeToCandidate($recipe));
        $activeMealTypes = array_keys($this->slotDistribution($excludeSnacks));
        $missingMealTypes = collect($activeMealTypes)->filter(fn (string $slot): bool => $this->filterByMealType($recipes, $slot)->isEmpty())->values()->all();
        if ($missingMealTypes !== []) {
            return [
                'insufficient_suitable_foods' => true, 'missing_meal_types' => $missingMealTypes,
                'message' => 'No suitable Meal or Snack Menu Items are available for: '.implode(', ', $missingMealTypes).'. Correct or add measured Menu Items, then regenerate.',
            ];
        }

        // Daily targets
        $dailyKcal = (float) $intervention->energy_kcal;
        $dailyProtein = (float) $intervention->protein_g;
        $dailyCarbs = (float) $intervention->carbs_g;
        $dailyFat = (float) $intervention->fat_g;

        if (min($dailyKcal, $dailyProtein, $dailyCarbs, $dailyFat) <= 0) {
            return ['message' => 'Complete positive energy and macro targets before generating a meal plan.'];
        }
        if ($selectedCarb !== null) {
            foreach ($this->slotDistribution($excludeSnacks) as $slot => $pct) {
                if ($this->isSnackSlot($slot)) {
                    continue;
                }
                if ($this->filterByMealType($recipes, $slot)->every(fn ($meal): bool => ((float) $meal->total_calories + (float) $selectedCarb->total_calories) * 0.01 > $dailyKcal * $pct * (1 + self::TOLERANCE))) {
                    return ['invalid_carb' => true, 'message' => 'No positive measured Meal and selected Carb portions fit this prescription. Choose another Carb Menu Item or select Exclude carbs.'];
                }
            }
        }

        // Micronutrient limits (e.g. ['sodium_mg' => ['max' => 1500, 'unit' => 'mg']])
        $microLimits = $intervention->micronutrient_limits ?? [];

        // Target macro ratios for scoring
        $targetProteinRatio = ($dailyProtein * 4) / $dailyKcal;
        $targetCarbsRatio = ($dailyCarbs * 4) / $dailyKcal;
        $targetFatRatio = ($dailyFat * 9) / $dailyKcal;

        return DB::transaction(function () use ($ncpRecord, $intervention, $weekStartDate, $recipes, $selectedCarb, $excludeSnacks, $dailyKcal, $dailyProtein, $dailyCarbs, $dailyFat, $microLimits, $targetProteinRatio, $targetCarbsRatio, $targetFatRatio, $goalType): MealPlan {
            $lockedIntervention = $ncpRecord->interventions()->whereKey($intervention->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedIntervention->mealPlan()->exists(), 409, 'This intervention already has a meal plan.');
            $mealPlan = MealPlan::create([
                'intervention_id' => $intervention->id,
                'patient_id' => $ncpRecord->patient_id,
                'week_start_date' => $weekStartDate,
                'generation_type' => 'auto',
                'needs_rescaling' => true,
                'status' => 'draft',
            ]);

            $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $mealTypes = array_keys(self::SLOT_DISTRIBUTION);
            $now = now();

            // Bulk-insert days
            $dayRows = [];
            foreach ($daysOfWeek as $day) {
                foreach ($mealTypes as $mealType) {
                    $dayRows[] = [
                        'uuid' => (string) Str::uuid(),
                        'meal_plan_id' => $mealPlan->id, 'day_of_week' => $day, 'meal_type' => $mealType, 'flagged' => false,
                    ];
                }
            }
            MealPlanDay::insert($dayRows);

            $days = MealPlanDay::where('meal_plan_id', $mealPlan->id)->orderBy('id')->get();

            // Shuffle recipe pool using seed if set (for test reproducibility)
            $seededShuffle = function (Collection $pool, int $daySeed) {
                if ($this->rngSeed !== null) {
                    srand($this->rngSeed + $daySeed);
                    $arr = $pool->all();
                    $count = count($arr);
                    for ($i = $count - 1; $i > 0; $i--) {
                        $j = rand(0, $i);
                        [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
                    }

                    return collect(array_values($arr));
                }

                return $pool->shuffle()->values();
            };

            $itemRows = [];
            $crossDayUsed = []; // recipeUid => dayIndex of most-recent use (for recency penalty)

            foreach ($daysOfWeek as $dayIndex => $dayName) {
                $dayPool = $seededShuffle($recipes, $dayIndex);
                $daySlots = $days->where('day_of_week', $dayName)->sortBy('id')->values();
                $usedThisDay = [];

                foreach ($daySlots as $slotIndex => $dayRecord) {
                    $mealType = $dayRecord->meal_type;
                    if ($excludeSnacks && $this->isSnackSlot($mealType)) {
                        continue;
                    }
                    $distribution = $this->slotDistribution($excludeSnacks);
                    $slotPct = $distribution[$mealType] ?? 0.20;
                    $targetKcal = $dailyKcal * $slotPct;

                    $eligible = $this->filterByMealType($dayPool, $mealType);
                    if ($selectedCarb !== null && ! $this->isSnackSlot($mealType)) {
                        $eligible = $eligible->filter(fn ($meal): bool => ((float) $meal->total_calories + (float) $selectedCarb->total_calories) * 0.01 <= $targetKcal * (1 + self::TOLERANCE))->values();
                    }

                    if ($eligible->isEmpty()) {
                        throw new \RuntimeException("Suitable-food preflight drifted for {$mealType}.");
                    }

                    $best = $this->pickBest(
                        $eligible,
                        $usedThisDay,
                        $targetProteinRatio,
                        $targetCarbsRatio,
                        $targetFatRatio,
                        $microLimits,
                        $slotIndex,
                        $crossDayUsed,
                        $dayIndex,
                        $goalType,
                    );
                    $usedThisDay[] = $best->uid;
                    $crossDayUsed[$best->uid] = $dayIndex;

                    $quantity = $this->practicalCandidateQuantity($best, $targetKcal);

                    $itemRows[] = $this->buildItemRow($dayRecord->id, $best, $quantity, $now);

                    if ($selectedCarb !== null && ! $this->isSnackSlot($mealType)) {
                        [, $carbQuantity] = $this->pickCarbAddition($best, $quantity, collect([$selectedCarb]), [
                            'energy' => $dailyKcal * $slotPct, 'protein' => $dailyProtein * $slotPct,
                            'carbs' => $dailyCarbs * $slotPct, 'fat' => $dailyFat * $slotPct,
                        ], $microLimits, $slotPct);
                        $itemRows[] = $this->buildItemRow($dayRecord->id, $selectedCarb, $carbQuantity, $now);
                    }
                }
            }
            MealPlanItem::insert($itemRows);

            // Reload days with items for validation
            $mealPlan->load('days.items');

            // Reconcile by changing practical quantities only. Composition is frozen:
            // no filler foods, substitutions, or fluid-driven adjustments are permitted.
            $this->scaleToPrescription($mealPlan, $intervention);

            return $mealPlan->load('days');
        });
    }

    // ── Slot eligibility (4.2) ────────────────────────────────────────────────

    /**
     * Automatic placement uses the canonical Menu Item category.
     */
    private function filterByMealType(Collection $recipes, string $slotType): Collection
    {
        return $recipes->where('category', $this->isSnackSlot($slotType) ? 'snack' : 'meal')->values();
    }

    private function isSnackSlot(string $slotType): bool
    {
        return in_array($slotType, ['am_snack', 'pm_snack'], true);
    }

    // ── Recipe scoring + picking ──────────────────────────────────────────────

    private function pickBest(
        Collection $pool,
        array $usedIds,
        float $targetProteinRatio,
        float $targetCarbsRatio,
        float $targetFatRatio,
        array $microLimits,
        int $fallbackIndex,
        array $crossDayUsed = [],
        int $currentDayIndex = 0,
        string $goalType = '',
    ): object {
        $scored = [];
        foreach ($pool as $r) {
            if (in_array($r->uid, $usedIds)) {
                continue;
            }
            $rKcal = max((float) $r->total_calories, 1);
            $rProteinRatio = ((float) $r->total_protein * 4) / $rKcal;
            $rCarbsRatio = ((float) $r->total_carbs * 4) / $rKcal;
            $rFatRatio = ((float) $r->total_fat * 9) / $rKcal;

            $score = sqrt(
                pow($rProteinRatio - $targetProteinRatio, 2) +
                pow($rCarbsRatio - $targetCarbsRatio, 2) +
                pow($rFatRatio - $targetFatRatio, 2)
            );
            if (! empty($microLimits)) {
                $recipeMicros = is_array($r->micronutrients) ? $r->micronutrients : [];
                $score += $this->calcMicroPenalty($recipeMicros, $microLimits);
            }
            $score += $this->goalPenalty($r, $goalType);

            // Cross-day recency penalty: push recently-used recipes down the ranking
            // without hard-excluding them (important for small pools).
            if (isset($crossDayUsed[$r->uid])) {
                $daysSince = $currentDayIndex - $crossDayUsed[$r->uid];
                if ($daysSince < self::RECENCY_LOOK_BACK) {
                    $score += (self::RECENCY_LOOK_BACK - $daysSince) * self::RECENCY_PENALTY;
                }
            }

            $scored[] = ['recipe' => $r, 'score' => $score];
        }

        if (empty($scored)) {
            return $pool[$fallbackIndex % $pool->count()];
        }

        usort($scored, fn ($a, $b) => $a['score'] <=> $b['score']);

        $window = 3;

        $topN = array_slice($scored, 0, min($window, count($scored)));

        return $topN[array_rand($topN)]['recipe'];
    }

    private function practicalCandidateQuantity(object $candidate, float $targetKcal): float
    {
        $reference = max((float) ($candidate->reference_amount ?? 1), 0.0001);
        $minimum = 0.01;
        $maximum = $candidate->component_type === 'main_dish' ? 1.5 : 2.5;
        $factor = min($maximum, max($minimum, $targetKcal / max((float) $candidate->total_calories, 1)));
        $step = $this->candidateStep((string) ($candidate->serving_unit ?? 'serving'));

        return max($step, round(($reference * $factor) / $step) * $step);
    }

    /** @return array{0:object,1:float}|null */
    private function pickCarbAddition(
        object $main,
        float $mainQuantity,
        Collection $carbs,
        array $targets,
        array $microLimits,
        float $slotPct,
    ): ?array {
        if ($carbs->isEmpty()) {
            return null;
        }

        $best = null;
        $bestScore = INF;
        $mainFactor = $mainQuantity / max((float) $main->reference_amount, 0.0001);
        $remainingKcal = max(0, $targets['energy'] - ((float) $main->total_calories * $mainFactor));
        $remainingCarbs = max(0, $targets['carbs'] - ((float) $main->total_carbs * $mainFactor));

        foreach ($carbs as $carb) {
            $energyFactor = $remainingKcal / max((float) $carb->total_calories, 1);
            $carbFactor = $remainingCarbs / max((float) $carb->total_carbs, 1);
            $factor = min(2.0, max(0.01, ($energyFactor + $carbFactor) / 2));
            $step = $this->candidateStep((string) $carb->serving_unit);
            $quantity = max($step, round(((float) $carb->reference_amount * $factor) / $step) * $step);
            $score = $this->candidateMealScore(
                [[$main, $mainQuantity], [$carb, $quantity]],
                $targets,
                $microLimits,
                $slotPct,
            );
            if ($score + 0.000001 < $bestScore) {
                $best = [$carb, $quantity];
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function candidateMealScore(array $portions, array $targets, array $microLimits, float $slotPct): float
    {
        $targets = $this->foodMatchingTargets($targets);
        $totals = ['energy' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];
        $micros = [];
        foreach ($portions as [$candidate, $quantity]) {
            $factor = $quantity / max((float) $candidate->reference_amount, 0.0001);
            foreach (['energy' => 'total_calories', 'protein' => 'total_protein', 'carbs' => 'total_carbs', 'fat' => 'total_fat'] as $key => $field) {
                $totals[$key] += (float) $candidate->{$field} * $factor;
            }
            foreach (($candidate->micronutrients ?? []) as $key => $value) {
                $micros[$key] = ($micros[$key] ?? 0) + ((float) $value * $factor);
            }
        }

        $score = 0.0;
        foreach ($targets as $key => $target) {
            if ($target > 0) {
                $score += (($totals[$key] - $target) / $target) ** 2;
            }
        }
        foreach ($microLimits as $key => $limit) {
            $allowed = isset($limit['max']) ? (float) $limit['max'] * $slotPct : null;
            if ($allowed !== null && isset($micros[$key]) && $micros[$key] > $allowed) {
                $score += (($micros[$key] - $allowed) / max($allowed, 0.001)) ** 2 * 4;
            }
        }

        return $score;
    }

    private function candidateStep(string $unit): float
    {
        return match (strtolower(trim($unit))) {
            'g', 'gram', 'grams' => 25.0,
            'cup', 'cups' => 0.5,
            'piece', 'pieces', 'pc', 'pcs' => 1.0,
            'ml' => 50.0,
            'serving', 'servings', 'portion', 'portions' => 0.01,
            default => 1.0,
        };
    }

    private function goalPenalty(object $candidate, string $goalType): float
    {
        $kcal = max((float) $candidate->total_calories, 1);
        $proteinDensity = ((float) $candidate->total_protein * 4) / $kcal;
        $fatDensity = ((float) $candidate->total_fat * 9) / $kcal;
        $micros = is_array($candidate->micronutrients) ? $candidate->micronutrients : [];
        $fiber = (float) ($micros['fiber'] ?? $micros['fiber_g'] ?? 0);
        $sodium = (float) ($micros['sodium'] ?? $micros['sodium_mg'] ?? 0);
        $cholesterol = (float) ($micros['cholesterol'] ?? $micros['cholesterol_mg'] ?? 0);
        $freeSugars = (float) ($micros['free_sugars'] ?? $micros['sugar'] ?? 0);

        return match ($goalType) {
            'diabetic_control' => min(0.4, $freeSugars / 50) - min(0.15, $fiber / 50),
            'cardiac_diet' => min(0.5, $sodium / 3000 + $cholesterol / 1000) - min(0.1, $fiber / 80),
            'weight_loss' => $fatDensity * 0.25 - min(0.15, $fiber / 50),
            'weight_gain' => 0.15 / max($kcal / 300, 0.25),
            'high_protein', 'liver_disease', 'malnutrition' => -min(0.2, $proteinDensity * 0.35),
            default => 0.0,
        };
    }

    /**
     * Pick the best replacement for a slot, biased toward closing the residual daily gap.
     * Used in the reconciliation pass (4.4).
     */
    private function pickResidual(
        Collection $pool,
        array $usedIds,
        array $residuals,
        string $mealType,
        float $slotPct
    ): ?object {
        $residuals = $this->foodMatchingTargets($residuals);
        $eligible = $this->filterByMealType($pool, $mealType);
        if ($this->isSnackSlot($mealType)) {
            $foodOnly = $eligible->where('source', 'food_item')->values();
            if ($foodOnly->isNotEmpty()) {
                $eligible = $foodOnly;
            }
        }
        if ($eligible->isEmpty()) {
            $eligible = $pool;
        }

        $scored = [];
        foreach ($eligible as $r) {
            if (in_array($r->uid, $usedIds)) {
                continue;
            }
            $portion = $slotPct; // scale factor = 1 serving at slot %

            // Score = distance between scaled recipe nutrients and residual targets
            $score = 0.0;
            foreach ($residuals as $key => $remaining) {
                $recipeVal = match ($key) {
                    'energy' => (float) $r->total_calories * $portion,
                    'protein' => (float) $r->total_protein * $portion,
                    'carbs' => (float) $r->total_carbs * $portion,
                    'fat' => (float) $r->total_fat * $portion,
                    default => (float) (is_array($r->micronutrients) ? ($r->micronutrients[$key] ?? 0) : 0) * $portion,
                };
                // Penalise distance from remaining target
                $score += abs($recipeVal - $remaining);
            }
            $scored[] = ['recipe' => $r, 'score' => $score];
        }

        if (empty($scored)) {
            return null;
        }

        usort($scored, fn ($a, $b) => $a['score'] <=> $b['score']);

        // For interchangeable ready-to-eat snacks, randomise among the closest few so the
        // bounded reconciliation pass doesn't collapse weekly snack variety to one item.
        $allFood = collect($scored)->every(fn ($s) => $s['recipe']->source === 'food_item');
        $window = $allFood ? min(5, count($scored)) : 1;

        return $scored[array_rand(array_slice($scored, 0, $window, true))]['recipe'];
    }

    // ── Post-generation ±10% validation (4.3) ────────────────────────────────

    private function validateDay(
        Collection $slots,
        Collection $allRecipes,
        array $targets,
        array $microLimits,
        float $targetProteinRatio,
        float $targetCarbsRatio,
        float $targetFatRatio,
        bool $excludeSnacks = false,
    ): void {
        $variance = $this->computeDayVariance($slots, $targets, $microLimits);
        $flagged = $this->isFlagged($variance);

        if ($flagged) {
            // ── Reconciliation pass (4.4) ──
            $variance = $this->reconcileDay(
                $slots,
                $allRecipes,
                $targets,
                $microLimits,
                $targetProteinRatio,
                $targetCarbsRatio,
                $targetFatRatio,
                $variance,
                $excludeSnacks,
            );
            $flagged = $this->isFlagged($variance);
        }

        // Persist flag + variance on each slot row for this day
        foreach ($slots as $slot) {
            MealPlanDay::where('id', $slot->id)->update([
                'flagged' => $flagged,
                'variance' => json_encode($variance),
            ]);
        }
    }

    /**
     * Compute per-day variance JSON.
     * Returns float (% deviation, e.g. 0.12 = +12%) or "cannot_validate" per nutrient.
     */
    private function computeDayVariance(Collection $slots, array $targets, array $microLimits): array
    {
        $targets = $this->foodMatchingTargets($targets);
        // Aggregate per-day totals across all slots
        $totals = ['energy' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];
        $microTotals = [];

        foreach ($slots as $slot) {
            foreach ($slot->items as $item) {
                $snap = $item->nutrient_snapshot ?? [];
                $qty = $this->itemFactor($item);

                $totals['energy'] += (float) ($snap['calories'] ?? 0) * $qty;
                $totals['protein'] += (float) ($snap['protein'] ?? 0) * $qty;
                $totals['carbs'] += (float) ($snap['carbs'] ?? 0) * $qty;
                $totals['fat'] += (float) ($snap['fat'] ?? 0) * $qty;

                $recipeMicros = $snap['micronutrients'] ?? [];
                foreach ($recipeMicros as $k => $v) {
                    $microTotals[$k] = ($microTotals[$k] ?? 0) + ((float) $v * $qty);
                }
            }
        }

        $variance = [];

        // Food energy and macronutrients only. Fluid remains prescription guidance.
        foreach ($targets as $key => $target) {
            if ($target <= 0) {
                continue;
            }
            $actual = $totals[$key] ?? 0.0;
            $variance[$key] = round(($actual - $target) / $target, 4);
        }

        // Micronutrients from micronutrient_limits
        foreach ($microLimits as $nutrientKey => $limit) {
            $targetVal = $limit['min'] ?? $limit['max'] ?? null;
            if ($targetVal === null) {
                continue;
            }

            if (! array_key_exists($nutrientKey, $microTotals)) {
                // Prescribed nutrient not reported by any recipe → data gap
                $variance[$nutrientKey] = 'cannot_validate';
            } elseif (isset($limit['max'])) {
                $variance[$nutrientKey] = round(max(0, $microTotals[$nutrientKey] - (float) $limit['max']) / max((float) $limit['max'], 0.001), 4);
            } elseif (isset($limit['min'])) {
                $variance[$nutrientKey] = round(min(0, $microTotals[$nutrientKey] - (float) $limit['min']) / max((float) $limit['min'], 0.001), 4);
            }
        }

        return $variance;
    }

    private function itemFactor(object $item, ?float $quantity = null): float
    {
        $snapshot = $item->nutrient_snapshot ?? [];
        $reference = max((float) ($snapshot['serving_size'] ?? 1), 0.0001);

        return ($quantity ?? (float) $item->quantity) / $reference;
    }

    /** @param array<int,float> $quantities */
    private function nutrientTotals(Collection $items, array $quantities): array
    {
        $totals = ['energy' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];
        foreach ($items as $item) {
            $snapshot = $item->nutrient_snapshot ?? [];
            $factor = $this->itemFactor($item, $quantities[$item->id] ?? null);
            $totals['energy'] += (float) ($snapshot['calories'] ?? 0) * $factor;
            $totals['protein'] += (float) ($snapshot['protein'] ?? 0) * $factor;
            $totals['carbs'] += (float) ($snapshot['carbs'] ?? 0) * $factor;
            $totals['fat'] += (float) ($snapshot['fat'] ?? 0) * $factor;
        }

        return $totals;
    }

    /** @param array<int,float> $quantities */
    private function scalingScore(Collection $items, array $quantities, array $targets, array $microLimits): float
    {
        $targets = $this->foodMatchingTargets($targets);
        $totals = $this->nutrientTotals($items, $quantities);
        $score = 0.0;
        foreach ($targets as $key => $target) {
            $score += (($totals[$key] - $target) / $target) ** 2;
        }

        foreach ($microLimits as $nutrient => $limit) {
            if (! isset($limit['max'])) {
                continue;
            }
            $actual = 0.0;
            $reported = false;
            foreach ($items as $item) {
                $snapshot = $item->nutrient_snapshot ?? [];
                if (array_key_exists($nutrient, $snapshot['micronutrients'] ?? [])) {
                    $reported = true;
                    $actual += (float) $snapshot['micronutrients'][$nutrient]
                        * $this->itemFactor($item, $quantities[$item->id] ?? null);
                }
            }
            if ($reported && $actual > (float) $limit['max']) {
                $score += (($actual - (float) $limit['max']) / max((float) $limit['max'], 0.001)) ** 2 * 4;
            }
        }

        return $score;
    }

    private function quantityStep(MealPlanItem $item): float
    {
        return match (strtolower(trim($item->unit))) {
            'g', 'gram', 'grams' => 25.0,
            'cup', 'cups' => 0.5,
            'piece', 'pieces', 'pc', 'pcs' => 1.0,
            'ml' => 50.0,
            'serving', 'servings', 'portion', 'portions' => 0.01,
            default => 0.25,
        };
    }

    private function snapQuantity(MealPlanItem $item, float $quantity): float
    {
        $step = $this->quantityStep($item);

        return max($this->minimumQuantity($item), round($quantity / $step) * $step);
    }

    private function minimumQuantity(MealPlanItem $item): float
    {
        return match (strtolower(trim($item->unit))) {
            'serving', 'servings', 'portion', 'portions' => 0.01,
            default => $this->quantityStep($item),
        };
    }

    /**
     * True if any numeric variance value is outside ±10%.
     * "cannot_validate" does NOT trigger flagging (it is surfaced for data-gap awareness).
     */
    private function isFlagged(array $variance): bool
    {
        foreach ($variance as $key => $v) {
            if (in_array($key, ['water', 'fluid', 'fluid_ml'], true)) {
                continue;
            }
            if (is_float($v) && abs($v) > self::TOLERANCE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bounded reconciliation pass (4.4): for a flagged day, re-pick the worst-
     * contributing slot using a residual-target score, up to MAX_RECONCILE_ITERATIONS.
     */
    private function reconcileDay(
        Collection $slots,
        Collection $allRecipes,
        array $targets,
        array $microLimits,
        float $targetProteinRatio,
        float $targetCarbsRatio,
        float $targetFatRatio,
        array $currentVariance,
        bool $excludeSnacks = false,
    ): array {
        $targets = $this->foodMatchingTargets($targets);
        $distribution = $this->slotDistribution($excludeSnacks);
        $variance = $currentVariance;
        unset($variance['water'], $variance['fluid'], $variance['fluid_ml']);

        for ($iter = 0; $iter < self::MAX_RECONCILE_ITERATIONS; $iter++) {
            if (! $this->isFlagged($variance)) {
                break;
            }

            // Find worst slot: the one whose individual nutrients deviate the most
            $worstSlot = null;
            $worstScore = -1.0;

            foreach ($slots as $slot) {
                $score = 0.0;
                foreach ($slot->items as $item) {
                    $snap = $item->nutrient_snapshot ?? [];
                    $qty = $this->itemFactor($item);
                    foreach ($targets as $key => $target) {
                        $val = match ($key) {
                            'energy' => (float) ($snap['calories'] ?? 0) * $qty,
                            'protein' => (float) ($snap['protein'] ?? 0) * $qty,
                            'carbs' => (float) ($snap['carbs'] ?? 0) * $qty,
                            'fat' => (float) ($snap['fat'] ?? 0) * $qty,
                            default => 0.0,
                        };
                        $slotTarget = $target * ($distribution[$slot->meal_type] ?? 0.20);
                        $score += $slotTarget > 0 ? abs($val - $slotTarget) / $slotTarget : 0;
                    }
                }
                if ($score > $worstScore) {
                    $worstScore = $score;
                    $worstSlot = $slot;
                }
            }

            if (! $worstSlot) {
                break;
            }

            // Compute residual targets for this slot
            $slotPct = $distribution[$worstSlot->meal_type] ?? 0.20;
            $residuals = [];
            foreach ($targets as $key => $target) {
                $residuals[$key] = $target * $slotPct;
            }

            // Exclude every candidate already used ANYWHERE in this day (not just the
            // worst slot) so reconciliation never reintroduces an intra-day duplicate.
            $dayUsedIds = [];
            foreach ($slots as $slot) {
                foreach ($slot->items as $it) {
                    if ($it->recipe_id) {
                        $dayUsedIds[] = 'recipe:'.$it->recipe_id;
                    }
                    if ($it->food_item_id) {
                        $dayUsedIds[] = 'food_item:'.$it->food_item_id;
                    }
                }
            }
            $dayUsedIds = array_values(array_unique($dayUsedIds));

            $replacement = $this->pickResidual(
                $allRecipes,
                $dayUsedIds,
                $residuals,
                $worstSlot->meal_type,
                $slotPct
            );

            if (! $replacement) {
                continue;
            }

            // Replace items in this slot
            $worstSlot->items()->delete();
            $recipeKcal = max((float) $replacement->total_calories, 1);
            $targetKcal = ($targets['energy'] ?? 2000) * $slotPct;
            $quantity = round(min(max($targetKcal / $recipeKcal, 1.0), 2.0), 2);

            $newItem = $this->buildItemRow($worstSlot->id, $replacement, $quantity, now());
            // buildItemRow JSON-encodes the snapshot for the bulk insert() path (which
            // bypasses casts). create() applies the array cast, so decode first to avoid
            // double-encoding the snapshot.
            $newItem['nutrient_snapshot'] = json_decode($newItem['nutrient_snapshot'], true);
            MealPlanItem::create($newItem);

            // Reload items for re-validation
            $worstSlot->load('items');
            // Update full slots collection with refreshed slot
            $slots = $slots->map(fn ($s) => $s->id === $worstSlot->id ? $worstSlot : $s);

            // Re-compute variance
            $variance = $this->computeDayVariance($slots, $targets, $microLimits);
        }

        return $variance;
    }

    private function foodMatchingTargets(array $targets): array
    {
        return array_intersect_key($targets, array_flip(self::FOOD_MATCHING_TARGET_KEYS));
    }

    /** @return array<string, float> */
    private function slotDistribution(bool $excludeSnacks): array
    {
        return $excludeSnacks ? self::NO_SNACK_SLOT_DISTRIBUTION : self::SLOT_DISTRIBUTION;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildItemRow(int $dayId, object $candidate, float $quantity, Carbon $now): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'meal_plan_day_id' => $dayId,
            'recipe_id' => $candidate->source_id,
            'food_item_id' => null,
            'quantity' => $quantity,
            'unit' => $candidate->serving_unit,
            'nutrient_snapshot' => json_encode([...$candidate->plan_snapshot, 'source' => $candidate->source]),
            'ai_suggested' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Normalize a Recipe into a source-agnostic candidate object.
     */
    private function recipeToCandidate(Recipe $recipe): object
    {
        $snapshot = $recipe->planSnapshot();

        return (object) [
            'uid' => 'recipe:'.$recipe->id,
            'source' => 'recipe',
            'source_id' => $recipe->id,
            'name' => $recipe->name,
            'category' => $recipe->category,
            'total_calories' => $snapshot['calories'],
            'total_protein' => $snapshot['protein'],
            'total_carbs' => $snapshot['carbs'],
            'total_fat' => $snapshot['fat'],
            'total_water' => $snapshot['water_g'],
            'micronutrients' => $snapshot['micronutrients'],
            'plan_snapshot' => $snapshot,
            'reference_amount' => 1.0,
            'serving_unit' => 'serving',
            'meal_types' => $recipe->meal_types,
            'component_type' => $recipe->component_type,
            'contains_vegetable' => $recipe->ingredients->contains(
                fn ($ingredient): bool => $ingredient->foodItem?->category === 'vegetable'
            ),
        ];
    }

    /**
     * Calculate a scoring penalty based on micronutrient limit violations.
     *
     * @param  array<string, float>  $recipeMicros  Micronutrient values from the recipe
     * @param  array<string, array{max?: int|float, min?: int|float, unit: string}>  $limits
     * @return float Additive penalty to append to the macro-distance score
     */
    private function calcMicroPenalty(array $recipeMicros, array $limits): float
    {
        $penalty = 0.0;
        foreach ($limits as $nutrientKey => $limit) {
            // Only penalise for 'max' violations; 'min' is a target, not an exclusion
            if (! isset($limit['max'])) {
                continue;
            }
            // If the recipe doesn't report this nutrient, we cannot penalise it
            if (! array_key_exists($nutrientKey, $recipeMicros)) {
                continue;
            }
            if ((float) $recipeMicros[$nutrientKey] > (float) $limit['max']) {
                $penalty += self::MICRO_PENALTY_PER_EXCESS;
            }
        }

        return $penalty;
    }
}
