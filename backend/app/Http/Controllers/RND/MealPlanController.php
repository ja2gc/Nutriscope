<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreMealPlanRequest;
use App\Http\Requests\RND\UpdateMealPlanRequest;
use App\Http\Requests\RND\GenerateMealPlanRequest;
use App\Http\Requests\RND\RecommendRequest;
use App\Http\Resources\MealPlanResource;
use App\Models\MealPlan;
use App\Models\NcpRecord;
use App\Services\ClinicalCompletenessService;
use App\Services\MealPlanService;
use App\Services\RecommendService;
use Illuminate\Http\JsonResponse;

class MealPlanController extends Controller
{
    public function __construct(
        private MealPlanService $mealPlanService,
        private RecommendService $recommendService,
        private ClinicalCompletenessService $completeness,
    ) {}

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/meal-plans
     */
    public function index(NcpRecord $ncpRecord): JsonResponse
    {
        $intervention = $ncpRecord->intervention()->firstOrFail();
        $mealPlans = MealPlan::where('intervention_id', $intervention->id)->with('days')->get();

        return response()->json(['data' => MealPlanResource::collection($mealPlans)]);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans
     */
    public function store(StoreMealPlanRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        $intervention = $ncpRecord->intervention()->firstOrFail();

        $mealPlan = MealPlan::create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $ncpRecord->patient_id,
            'week_start_date' => $request->week_start_date,
            'generation_type' => $request->generation_type ?? 'manual',
            'status'          => $request->status ?? 'draft',
        ]);

        // Pre-create all 35 empty day slots so the frontend can render the grid immediately
        $dayRows = [];
        foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day) {
            foreach (['breakfast','am_snack','lunch','pm_snack','dinner'] as $mealType) {
                $dayRows[] = [
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'meal_plan_id' => $mealPlan->id, 'day_of_week' => $day, 'meal_type' => $mealType, 'flagged' => false,
                ];
            }
        }
        \App\Models\MealPlanDay::insert($dayRows);

        return response()->json(['data' => new MealPlanResource($mealPlan->load('days'))], 201);
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}
     */
    public function show(NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertPlanScope($ncpRecord, $mealPlan);
        return response()->json(['data' => new MealPlanResource($mealPlan->load('days'))]);
    }

    /** MP-04: the meal plan must belong to this NCP's intervention. */
    private function assertPlanScope(NcpRecord $ncpRecord, MealPlan $mealPlan): void
    {
        if ($mealPlan->intervention_id !== $ncpRecord->intervention?->id) {
            abort(404);
        }
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}
     */
    public function update(UpdateMealPlanRequest $request, NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertPlanScope($ncpRecord, $mealPlan);
        $mealPlan->update($request->validated());

        return response()->json(['data' => new MealPlanResource($mealPlan->fresh()->load('days'))]);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/generate
     */
    public function generate(GenerateMealPlanRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        // MP-01 / IV-02: a meal plan must be built against a real prescription.
        // Generating without energy/macro targets falls back to generic defaults
        // and produces a clinically meaningless plan.
        $missing = $this->completeness->interventionMissing($ncpRecord);
        if (! empty($missing)) {
            return response()->json([
                'message' => 'Complete the nutrition prescription before generating a meal plan. Missing: '
                    . implode(', ', $missing) . '.',
                'errors'  => ['intervention' => $missing],
            ], 422);
        }

        $result = $this->mealPlanService->generate(
            $ncpRecord,
            $request->week_start_date,
            $request->conditions ?? [],
            $request->allergens ?? [],
        );

        if (is_array($result)) {
            return response()->json($result, 422);
        }

        return response()->json(['data' => new MealPlanResource($result)], 201);
    }

    /**
     * DELETE /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}
     */
    public function destroy(NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertPlanScope($ncpRecord, $mealPlan);
        $mealPlan->delete();
        return response()->json(null, 204);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/save-template
     */
    public function saveTemplate(\Illuminate\Http\Request $request, NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertPlanScope($ncpRecord, $mealPlan);
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'goal_type'   => 'nullable|string|max:255',
        ]);

        $template = \App\Models\MealPlanTemplate::create([
            'rnd_user_id' => auth()->id(),
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'goal_type'   => $validated['goal_type'] ?? $ncpRecord->intervention?->goal_type,
        ]);

        foreach ($mealPlan->days as $day) {
            $firstItem = $day->items()->first();
            \App\Models\MealPlanTemplateDay::create([
                'template_id'  => $template->id,
                'day_of_week'  => $day->day_of_week,
                'meal_type'    => $day->meal_type,
                'food_item_id' => $firstItem?->food_item_id,
                'recipe_id'    => $firstItem?->recipe_id,
                'quantity'     => $firstItem?->quantity ?? 1,
                'unit'         => $firstItem?->unit ?? 'serving',
            ]);
        }

        return response()->json([
            'data' => ['id' => $template->uuid, 'name' => $template->name, 'goal_type' => $template->goal_type],
        ], 201);
    }

    /**
     * GET /api/rnd/meal-plan-templates
     */
    public function templates(): JsonResponse
    {
        $templates = \App\Models\MealPlanTemplate::where('rnd_user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get(['id', 'uuid', 'name', 'description', 'goal_type', 'created_at']);

        return response()->json(['data' => $templates->map(fn ($t) => [
            'id' => $t->uuid, 'name' => $t->name, 'description' => $t->description,
            'goal_type' => $t->goal_type, 'created_at' => $t->created_at,
        ])]);
    }

    /**
     * GET /api/rnd/meal-plan-templates/{template}
     */
    public function showTemplate(\App\Models\MealPlanTemplate $template): JsonResponse
    {
        $template->load(['days.foodItem', 'days.recipe']);

        $days = $template->days->map(fn($d) => [
            'id'          => $d->id,
            'day_of_week' => $d->day_of_week,
            'meal_type'   => $d->meal_type,
            'quantity'    => $d->quantity,
            'unit'        => $d->unit,
            'food_name'   => $d->foodItem?->name ?? $d->recipe?->name ?? null,
            'calories'    => $d->foodItem?->calories ?? $d->recipe?->total_calories ?? null,
        ]);

        return response()->json(['data' => [
            'id'         => $template->uuid,
            'name'       => $template->name,
            'description'=> $template->description,
            'goal_type'  => $template->goal_type,
            'created_at' => $template->created_at,
            'days'       => $days,
        ]]);
    }

    /**
     * DELETE /api/rnd/meal-plan-templates/{template}
     */
    public function destroyTemplate(\App\Models\MealPlanTemplate $template): JsonResponse
    {
        $template->delete();
        return response()->json(null, 204);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/from-template
     */
    public function fromTemplate(\Illuminate\Http\Request $request, NcpRecord $ncpRecord): JsonResponse
    {
        $validated = $request->validate([
            'template_id'     => 'required|integer|exists:meal_plan_templates,id',
            'week_start_date' => 'required|date',
        ]);

        $intervention = $ncpRecord->intervention()->firstOrFail();
        $template     = \App\Models\MealPlanTemplate::with('days')->findOrFail($validated['template_id']);

        $plan = MealPlan::create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $ncpRecord->patient_id,
            'week_start_date' => $validated['week_start_date'],
            'generation_type' => 'manual',
            'status'          => 'draft',
        ]);

        foreach ($template->days as $tDay) {
            $day = \App\Models\MealPlanDay::create([
                'meal_plan_id' => $plan->id,
                'day_of_week'  => $tDay->day_of_week,
                'meal_type'    => $tDay->meal_type,
            ]);
            if ($tDay->food_item_id || $tDay->recipe_id) {
                $snapshot = null;
                if ($tDay->food_item_id) {
                    $food = \App\Models\FoodItem::find($tDay->food_item_id);
                    if ($food) {
                        $snapshot = [
                            'name'           => $food->name,
                            'calories'       => (float) $food->calories,
                            'protein'        => (float) $food->protein,
                            'carbs'          => (float) $food->carbs,
                            'fat'            => (float) $food->fat,
                            'serving_size'   => (float) ($food->serving_size ?? 100),
                            'serving_unit'   => $food->serving_unit ?? 'g',
                            'micronutrients' => [],
                        ];
                    }
                }
                \App\Models\MealPlanItem::create([
                    'meal_plan_day_id'  => $day->id,
                    'food_item_id'      => $tDay->food_item_id,
                    'recipe_id'         => $tDay->recipe_id,
                    'quantity'          => $tDay->quantity,
                    'unit'              => $tDay->unit,
                    'nutrient_snapshot' => $snapshot,
                    'source'            => $tDay->food_item_id ? 'library' : 'recipe',
                ]);
            }
        }

        return response()->json(['data' => new MealPlanResource($plan->load('days.items'))], 201);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/intervention/recommend
     */
    public function recommend(RecommendRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        $result = $this->recommendService->getRecommendations(
            $request->conditions,
            $request->stages ?? null,
        );

        return response()->json(['data' => $result]);
    }
}
