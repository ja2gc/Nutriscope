<?php

namespace App\Services;

use App\Models\FoodItem;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Recipe;
use Illuminate\Support\Collection;

class MealPlanService
{
    /**
     * Flat penalty added to macro-distance score for each micronutrient
     * that exceeds its 'max' limit in the intervention's micronutrient_limits.
     * Using 1.0 ensures any single limit violation pushes a recipe below
     * a perfectly-matched macro recipe (macro-ratio distance is 0–√3 ≈ 1.73).
     */
    private const MICRO_PENALTY_PER_EXCESS = 1.0;

    // Approximate % of daily energy per meal slot
    private const SLOT_DISTRIBUTION = [
        'breakfast' => 0.25,
        'am_snack'  => 0.10,
        'lunch'     => 0.30,
        'pm_snack'  => 0.10,
        'dinner'    => 0.25,
    ];

    // Tolerance threshold for post-generation validation (10%)
    private const TOLERANCE = 0.10;

    // Maximum reconciliation iterations per flagged day
    private const MAX_RECONCILE_ITERATIONS = 3;

    /**
     * Slot → valid meal_types aliases.
     * A recipe is eligible for a slot if its meal_types array contains the slot name,
     * any of its aliases, or "any".
     */
    private const SLOT_TYPE_ALIASES = [
        'breakfast' => ['breakfast'],
        'am_snack'  => ['am_snack', 'snack'],
        'lunch'     => ['lunch'],
        'pm_snack'  => ['pm_snack', 'snack'],
        'dinner'    => ['dinner'],
    ];

    /**
     * Optional RNG seed. Set before calling generate() in tests for reproducibility.
     */
    private ?int $rngSeed = null;

    public function setRngSeed(?int $seed): void
    {
        $this->rngSeed = $seed;
    }

    public function generate(NcpRecord $ncpRecord, string $weekStartDate, array $conditions = [], array $allergens = []): array|MealPlan
    {
        $intervention = $ncpRecord->intervention()->firstOrFail();

        // Auto-pull allergens from the assessment if not explicitly passed
        if (empty($allergens)) {
            $assessmentAllergens = $ncpRecord->assessment?->allergies ?? [];
            $allergens = is_array($assessmentAllergens) ? $assessmentAllergens : [];
        }

        // Load all recipes with ingredients (for water aggregation) and filter allergens
        $recipeModels = Recipe::with('ingredients.foodItem')->get()->filter(function ($recipe) use ($allergens) {
            if (empty($allergens)) return true;
            foreach ($recipe->ingredients as $ing) {
                $foodAllergens = $ing->foodItem?->allergens ?? [];
                if (!is_array($foodAllergens)) $foodAllergens = [];
                foreach ($allergens as $patientAllergen) {
                    if (in_array(strtolower($patientAllergen), array_map('strtolower', $foodAllergens))) {
                        return false;
                    }
                }
            }
            return true;
        })->values();

        if ($recipeModels->count() < 5) {
            // Product decision (2026-06-15): no AI fallback for meal generation. When too
            // few recipes match, return an actionable message so the RND adds/loosens
            // recipes rather than silently producing a thin plan.
            return [
                'insufficient_recipes' => true,
                'count'                => $recipeModels->count(),
                'message'              => "Only {$recipeModels->count()} matching recipe(s) found (need at least 5). Add more recipes for this condition/allergen profile, then regenerate.",
            ];
        }

        // Load ready-to-eat food items (fruits/ready veg/overridden) for standalone snack
        // placement. These flow ONLY into snack slots via meal_types = ['snack'].
        $foodModels = FoodItem::readyToEat()->get()->filter(function ($food) use ($allergens) {
            if (empty($allergens)) return true;
            $foodAllergens = is_array($food->allergens) ? $food->allergens : [];
            foreach ($allergens as $patientAllergen) {
                if (in_array(strtolower($patientAllergen), array_map('strtolower', $foodAllergens))) {
                    return false;
                }
            }
            return true;
        })->values();

        // Normalize recipes + food items into a single candidate pool. Candidates expose
        // recipe-shaped fields (total_*) so scoring/validation is source-agnostic.
        $recipes = $recipeModels
            ->map(fn ($r) => $this->recipeToCandidate($r))
            ->concat($foodModels->map(fn ($f) => $this->foodToCandidate($f)))
            ->values();

        // Daily targets
        $dailyKcal    = max((float) ($intervention->energy_kcal ?? 2000), 1);
        $dailyProtein = (float) ($intervention->protein_g ?? 70);
        $dailyCarbs   = (float) ($intervention->carbs_g   ?? 250);
        $dailyFat     = (float) ($intervention->fat_g     ?? 60);
        $dailyFluid   = (float) ($intervention->fluid_ml  ?? 0);

        // Micronutrient limits (e.g. ['sodium_mg' => ['max' => 1500, 'unit' => 'mg']])
        $microLimits = $intervention->micronutrient_limits ?? [];

        // Target macro ratios for scoring
        $targetProteinRatio = ($dailyProtein * 4)  / $dailyKcal;
        $targetCarbsRatio   = ($dailyCarbs   * 4)  / $dailyKcal;
        $targetFatRatio     = ($dailyFat     * 9)  / $dailyKcal;

        $mealPlan = MealPlan::create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $ncpRecord->patient_id,
            'week_start_date' => $weekStartDate,
            'generation_type' => 'auto',
            'status'          => 'draft',
        ]);

        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $mealTypes  = array_keys(self::SLOT_DISTRIBUTION);
        $now        = now();

        // Bulk-insert days
        $dayRows = [];
        foreach ($daysOfWeek as $day) {
            foreach ($mealTypes as $mealType) {
                $dayRows[] = ['meal_plan_id' => $mealPlan->id, 'day_of_week' => $day, 'meal_type' => $mealType, 'flagged' => false];
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
        $dayRecipeMap = []; // dayName => [slotType => chosen recipe id]

        foreach ($daysOfWeek as $dayIndex => $dayName) {
            $dayPool = $seededShuffle($recipes, $dayIndex);
            $daySlots = $days->where('day_of_week', $dayName)->sortBy('id')->values();
            $usedThisDay = [];

            foreach ($daySlots as $slotIndex => $dayRecord) {
                $mealType   = $dayRecord->meal_type;
                $slotPct    = self::SLOT_DISTRIBUTION[$mealType] ?? 0.20;
                $targetKcal = $dailyKcal * $slotPct;

                // Filter by meal_types eligibility (4.2)
                $eligible = $this->filterByMealType($dayPool, $mealType);

                // Snack slots prefer single ready-to-eat food items (the feature intent):
                // when eligible food items exist, snacks are drawn from them rather than
                // composed recipes. Snack-tagged recipes remain a fallback when no food
                // item is available (e.g. all filtered out by allergens).
                if ($this->isSnackSlot($mealType)) {
                    $foodOnly = $eligible->where('source', 'food_item')->values();
                    if ($foodOnly->isNotEmpty()) {
                        $eligible = $foodOnly;
                    }
                }

                if ($eligible->isEmpty()) {
                    // Fallback: use all recipes if no eligible ones for this slot
                    $eligible = $dayPool;
                }

                $best = $this->pickBest(
                    $eligible,
                    $usedThisDay,
                    $targetProteinRatio,
                    $targetCarbsRatio,
                    $targetFatRatio,
                    $microLimits,
                    $slotIndex
                );
                $usedThisDay[] = $best->uid;
                $dayRecipeMap[$dayName][$mealType] = $best->uid;

                // Scale quantity to hit slot calorie target (clamped to ±50% of 1 serving)
                $recipeKcal = max((float) $best->total_calories, 1);
                $quantity   = round(min(max($targetKcal / $recipeKcal, 1.0), 2.0), 2);

                $itemRows[] = $this->buildItemRow($dayRecord->id, $best, $quantity, $now);
            }
        }
        MealPlanItem::insert($itemRows);

        // Reload days with items for validation
        $mealPlan->load('days.items');

        // ── Phase 4.3/4.4: Post-generation ±10% validation + reconciliation ──
        $this->validateAndReconcile(
            $mealPlan,
            $recipes,
            $dailyKcal,
            $dailyProtein,
            $dailyCarbs,
            $dailyFat,
            $dailyFluid,
            $microLimits,
            $targetProteinRatio,
            $targetCarbsRatio,
            $targetFatRatio
        );

        return $mealPlan->load('days');
    }

    // ── Slot eligibility (4.2) ────────────────────────────────────────────────

    /**
     * Return only recipes whose meal_types array contains the slot name, an alias, or "any".
     */
    private function filterByMealType(Collection $recipes, string $slotType): Collection
    {
        $aliases  = self::SLOT_TYPE_ALIASES[$slotType] ?? [$slotType];
        $accepted = array_merge($aliases, ['any']);
        $isSnack  = in_array('snack', $aliases, true);

        return $recipes->filter(function ($recipe) use ($accepted, $isSnack) {
            $types = $recipe->meal_types;
            if (empty($types) || !is_array($types)) {
                // Untyped candidate: eligible for main meals (backward compat for unset
                // recipes), but NOT for snacks. Snack slots require explicit snack tagging
                // or a ready-to-eat food item (tagged ['snack']) — otherwise generic
                // recipes flood snacks and crowd out single ready-to-eat items.
                return !$isSnack;
            }
            foreach ($types as $t) {
                if (in_array($t, $accepted, true)) return true;
            }
            return false;
        })->values();
    }

    /**
     * Whether a slot is a snack slot (am_snack / pm_snack — treated interchangeably).
     */
    private function isSnackSlot(string $slotType): bool
    {
        return in_array('snack', self::SLOT_TYPE_ALIASES[$slotType] ?? [], true);
    }

    // ── Recipe scoring + picking ──────────────────────────────────────────────

    private function pickBest(
        Collection $pool,
        array $usedIds,
        float $targetProteinRatio,
        float $targetCarbsRatio,
        float $targetFatRatio,
        array $microLimits,
        int $fallbackIndex
    ): object {
        $scored = [];
        foreach ($pool as $r) {
            if (in_array($r->uid, $usedIds)) continue;
            $rKcal = max((float) $r->total_calories, 1);
            $rProteinRatio = ((float) $r->total_protein * 4) / $rKcal;
            $rCarbsRatio   = ((float) $r->total_carbs   * 4) / $rKcal;
            $rFatRatio     = ((float) $r->total_fat     * 9) / $rKcal;

            $score = sqrt(
                pow($rProteinRatio - $targetProteinRatio, 2) +
                pow($rCarbsRatio   - $targetCarbsRatio,   2) +
                pow($rFatRatio     - $targetFatRatio,     2)
            );
            if (!empty($microLimits)) {
                $recipeMicros = is_array($r->micronutrients) ? $r->micronutrients : [];
                $score += $this->calcMicroPenalty($recipeMicros, $microLimits);
            }
            $scored[] = ['recipe' => $r, 'score' => $score];
        }

        if (empty($scored)) {
            return $pool[$fallbackIndex % $pool->count()];
        }

        usort($scored, fn($a, $b) => $a['score'] <=> $b['score']);

        // Single ready-to-eat snacks are clinically interchangeable and macro-ratio
        // scoring clusters them tightly, so a wide random window keeps weekly snack
        // variety high (a fruit's poor whole-day macro match shouldn't pin one winner).
        $allFood = collect($scored)->every(fn($s) => $s['recipe']->source === 'food_item');
        $window  = $allFood ? 8 : 3;

        $topN = array_slice($scored, 0, min($window, count($scored)));
        return $topN[array_rand($topN)]['recipe'];
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
        $eligible = $this->filterByMealType($pool, $mealType);
        if ($this->isSnackSlot($mealType)) {
            $foodOnly = $eligible->where('source', 'food_item')->values();
            if ($foodOnly->isNotEmpty()) $eligible = $foodOnly;
        }
        if ($eligible->isEmpty()) $eligible = $pool;

        $scored = [];
        foreach ($eligible as $r) {
            if (in_array($r->uid, $usedIds)) continue;
            $portion = $slotPct; // scale factor = 1 serving at slot %

            // Score = distance between scaled recipe nutrients and residual targets
            $score = 0.0;
            foreach ($residuals as $key => $remaining) {
                $recipeVal = match ($key) {
                    'energy'  => (float) $r->total_calories * $portion,
                    'protein' => (float) $r->total_protein  * $portion,
                    'carbs'   => (float) $r->total_carbs    * $portion,
                    'fat'     => (float) $r->total_fat      * $portion,
                    'water'   => (float) $r->total_water    * $portion,
                    default   => (float) (is_array($r->micronutrients) ? ($r->micronutrients[$key] ?? 0) : 0) * $portion,
                };
                // Penalise distance from remaining target
                $score += abs($recipeVal - $remaining);
            }
            $scored[] = ['recipe' => $r, 'score' => $score];
        }

        if (empty($scored)) return null;

        usort($scored, fn($a, $b) => $a['score'] <=> $b['score']);

        // For interchangeable ready-to-eat snacks, randomise among the closest few so the
        // bounded reconciliation pass doesn't collapse weekly snack variety to one item.
        $allFood = collect($scored)->every(fn($s) => $s['recipe']->source === 'food_item');
        $window  = $allFood ? min(5, count($scored)) : 1;

        return $scored[array_rand(array_slice($scored, 0, $window, true))]['recipe'];
    }

    // ── Post-generation ±10% validation (4.3) ────────────────────────────────

    private function validateAndReconcile(
        MealPlan $mealPlan,
        Collection $allRecipes,
        float $dailyKcal,
        float $dailyProtein,
        float $dailyCarbs,
        float $dailyFat,
        float $dailyFluid,
        array $microLimits,
        float $targetProteinRatio,
        float $targetCarbsRatio,
        float $targetFatRatio
    ): void {
        // Build targets array (only include non-zero targets)
        $targets = ['energy' => $dailyKcal, 'protein' => $dailyProtein, 'carbs' => $dailyCarbs, 'fat' => $dailyFat];
        if ($dailyFluid > 0) {
            $targets['water'] = $dailyFluid;
        }

        // Group days by day_of_week
        $daysByName = $mealPlan->days->groupBy('day_of_week');

        foreach ($daysByName as $dayName => $slots) {
            $this->validateDay(
                $slots,
                $allRecipes,
                $targets,
                $microLimits,
                $targetProteinRatio,
                $targetCarbsRatio,
                $targetFatRatio
            );
        }
    }

    private function validateDay(
        Collection $slots,
        Collection $allRecipes,
        array $targets,
        array $microLimits,
        float $targetProteinRatio,
        float $targetCarbsRatio,
        float $targetFatRatio
    ): void {
        $variance = $this->computeDayVariance($slots, $targets, $microLimits);
        $flagged  = $this->isFlagged($variance);

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
                $variance
            );
            $flagged = $this->isFlagged($variance);
        }

        // Persist flag + variance on each slot row for this day
        foreach ($slots as $slot) {
            MealPlanDay::where('id', $slot->id)->update([
                'flagged'  => $flagged,
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
        // Aggregate per-day totals across all slots
        $totals = ['energy' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0, 'water' => 0.0];
        $microTotals = [];

        foreach ($slots as $slot) {
            foreach ($slot->items as $item) {
                $snap = $item->nutrient_snapshot ?? [];
                $qty  = (float) ($item->quantity ?? 1);

                $totals['energy']  += (float) ($snap['calories'] ?? 0) * $qty;
                $totals['protein'] += (float) ($snap['protein']  ?? 0) * $qty;
                $totals['carbs']   += (float) ($snap['carbs']    ?? 0) * $qty;
                $totals['fat']     += (float) ($snap['fat']      ?? 0) * $qty;
                $totals['water']   += (float) ($snap['water_g']  ?? 0) * $qty;

                $recipeMicros = $snap['micronutrients'] ?? [];
                foreach ($recipeMicros as $k => $v) {
                    $microTotals[$k] = ($microTotals[$k] ?? 0) + ((float) $v * $qty);
                }
            }
        }

        $variance = [];

        // Macro/energy/water
        foreach ($targets as $key => $target) {
            if ($target <= 0) continue;
            $actual = $totals[$key] ?? 0.0;
            $variance[$key] = round(($actual - $target) / $target, 4);
        }

        // Micronutrients from micronutrient_limits
        foreach ($microLimits as $nutrientKey => $limit) {
            $targetVal = $limit['min'] ?? $limit['max'] ?? null;
            if ($targetVal === null) continue;

            if (!array_key_exists($nutrientKey, $microTotals)) {
                // Prescribed nutrient not reported by any recipe → data gap
                $variance[$nutrientKey] = 'cannot_validate';
            } else {
                $variance[$nutrientKey] = round(($microTotals[$nutrientKey] - (float) $targetVal) / max((float) $targetVal, 0.001), 4);
            }
        }

        return $variance;
    }

    /**
     * True if any numeric variance value is outside ±10%.
     * "cannot_validate" does NOT trigger flagging (it is surfaced for data-gap awareness).
     */
    private function isFlagged(array $variance): bool
    {
        foreach ($variance as $v) {
            if (is_float($v) && abs($v) > self::TOLERANCE) return true;
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
        array $currentVariance
    ): array {
        $variance = $currentVariance;

        for ($iter = 0; $iter < self::MAX_RECONCILE_ITERATIONS; $iter++) {
            if (!$this->isFlagged($variance)) break;

            // Find worst slot: the one whose individual nutrients deviate the most
            $worstSlot = null;
            $worstScore = -1.0;

            foreach ($slots as $slot) {
                $score = 0.0;
                foreach ($slot->items as $item) {
                    $snap = $item->nutrient_snapshot ?? [];
                    $qty  = (float) ($item->quantity ?? 1);
                    foreach ($targets as $key => $target) {
                        $val = match ($key) {
                            'energy'  => (float) ($snap['calories'] ?? 0) * $qty,
                            'protein' => (float) ($snap['protein']  ?? 0) * $qty,
                            'carbs'   => (float) ($snap['carbs']    ?? 0) * $qty,
                            'fat'     => (float) ($snap['fat']      ?? 0) * $qty,
                            'water'   => (float) ($snap['water_g']  ?? 0) * $qty,
                            default   => 0.0,
                        };
                        $slotTarget = $target * (self::SLOT_DISTRIBUTION[$slot->meal_type] ?? 0.20);
                        $score += $slotTarget > 0 ? abs($val - $slotTarget) / $slotTarget : 0;
                    }
                }
                if ($score > $worstScore) {
                    $worstScore = $score;
                    $worstSlot  = $slot;
                }
            }

            if (!$worstSlot) break;

            // Compute residual targets for this slot
            $slotPct = self::SLOT_DISTRIBUTION[$worstSlot->meal_type] ?? 0.20;
            $residuals = [];
            foreach ($targets as $key => $target) {
                $residuals[$key] = $target * $slotPct;
            }

            // Exclude every candidate already used ANYWHERE in this day (not just the
            // worst slot) so reconciliation never reintroduces an intra-day duplicate.
            $dayUsedIds = [];
            foreach ($slots as $slot) {
                foreach ($slot->items as $it) {
                    if ($it->recipe_id)    $dayUsedIds[] = 'recipe:' . $it->recipe_id;
                    if ($it->food_item_id) $dayUsedIds[] = 'food_item:' . $it->food_item_id;
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

            if (!$replacement) continue;

            // Replace items in this slot
            $worstSlot->items()->delete();
            $recipeKcal = max((float) $replacement->total_calories, 1);
            $targetKcal = ($targets['energy'] ?? 2000) * $slotPct;
            $quantity   = round(min(max($targetKcal / $recipeKcal, 1.0), 2.0), 2);

            $newItem = $this->buildItemRow($worstSlot->id, $replacement, $quantity, now());
            // buildItemRow JSON-encodes the snapshot for the bulk insert() path (which
            // bypasses casts). create() applies the array cast, so decode first to avoid
            // double-encoding the snapshot.
            $newItem['nutrient_snapshot'] = json_decode($newItem['nutrient_snapshot'], true);
            MealPlanItem::create($newItem);

            // Reload items for re-validation
            $worstSlot->load('items');
            // Update full slots collection with refreshed slot
            $slots = $slots->map(fn($s) => $s->id === $worstSlot->id ? $worstSlot : $s);

            // Re-compute variance
            $variance = $this->computeDayVariance($slots, $targets, $microLimits);
        }

        return $variance;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildItemRow(int $dayId, object $candidate, float $quantity, \Illuminate\Support\Carbon $now): array
    {
        $isFood = $candidate->source === 'food_item';

        return [
            'meal_plan_day_id'  => $dayId,
            'recipe_id'         => $isFood ? null : $candidate->source_id,
            'food_item_id'      => $isFood ? $candidate->source_id : null,
            'quantity'          => $quantity,
            'unit'              => 'serving',
            'nutrient_snapshot' => json_encode([
                'name'           => $candidate->name,
                'calories'       => (float) $candidate->total_calories,
                'protein'        => (float) $candidate->total_protein,
                'carbs'          => (float) $candidate->total_carbs,
                'fat'            => (float) $candidate->total_fat,
                'water_g'        => (float) ($candidate->total_water ?? 0),
                'micronutrients' => $candidate->micronutrients ?? [],
                'serving_size'   => (float) ($candidate->servings ?? 1),
                'serving_unit'   => $candidate->serving_unit ?? 'serving',
                'source'         => $candidate->source,
            ]),
            'ai_suggested' => false,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }

    /**
     * Normalize a Recipe into a source-agnostic candidate object.
     */
    private function recipeToCandidate(Recipe $recipe): object
    {
        return (object) [
            'uid'            => 'recipe:' . $recipe->id,
            'source'         => 'recipe',
            'source_id'      => $recipe->id,
            'name'           => $recipe->name,
            'total_calories' => (float) $recipe->total_calories,
            'total_protein'  => (float) $recipe->total_protein,
            'total_carbs'    => (float) $recipe->total_carbs,
            'total_fat'      => (float) $recipe->total_fat,
            'total_water'    => (float) ($recipe->total_water ?? 0),
            'micronutrients' => is_array($recipe->micronutrients) ? $recipe->micronutrients : [],
            'servings'       => (float) ($recipe->servings ?? 1),
            'serving_unit'   => 'serving',
            'meal_types'     => $recipe->meal_types,
        ];
    }

    /**
     * Normalize a ready-to-eat FoodItem into a candidate. Tagged meal_types = ['snack']
     * so it is only ever eligible for snack slots.
     */
    private function foodToCandidate(FoodItem $food): object
    {
        return (object) [
            'uid'            => 'food_item:' . $food->id,
            'source'         => 'food_item',
            'source_id'      => $food->id,
            'name'           => $food->name,
            'total_calories' => (float) $food->calories,
            'total_protein'  => (float) $food->protein,
            'total_carbs'    => (float) $food->carbs,
            'total_fat'      => (float) $food->fat,
            'total_water'    => $food->water_g !== null ? (float) $food->water_g : 0.0,
            'micronutrients' => is_array($food->micronutrients) ? $food->micronutrients : [],
            'servings'       => (float) ($food->serving_size ?? 100),
            'serving_unit'   => $food->serving_unit ?? 'serving',
            'meal_types'     => ['snack'],
        ];
    }

    /**
     * Calculate a scoring penalty based on micronutrient limit violations.
     *
     * @param  array<string, float>  $recipeMicros  Micronutrient values from the recipe
     * @param  array<string, array{max?: int|float, min?: int|float, unit: string}>  $limits
     * @return float  Additive penalty to append to the macro-distance score
     */
    private function calcMicroPenalty(array $recipeMicros, array $limits): float
    {
        $penalty = 0.0;
        foreach ($limits as $nutrientKey => $limit) {
            // Only penalise for 'max' violations; 'min' is a target, not an exclusion
            if (!isset($limit['max'])) {
                continue;
            }
            // If the recipe doesn't report this nutrient, we cannot penalise it
            if (!array_key_exists($nutrientKey, $recipeMicros)) {
                continue;
            }
            if ((float) $recipeMicros[$nutrientKey] > (float) $limit['max']) {
                $penalty += self::MICRO_PENALTY_PER_EXCESS;
            }
        }
        return $penalty;
    }
}
