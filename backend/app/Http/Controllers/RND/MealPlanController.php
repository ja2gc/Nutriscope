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
use App\Services\MealPlanService;
use App\Services\RecommendService;
use Illuminate\Http\JsonResponse;

class MealPlanController extends Controller
{
    public function __construct(
        private MealPlanService $mealPlanService,
        private RecommendService $recommendService,
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

        return response()->json(['data' => new MealPlanResource($mealPlan->load('days'))], 201);
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}
     */
    public function show(NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        return response()->json(['data' => new MealPlanResource($mealPlan->load('days'))]);
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}
     */
    public function update(UpdateMealPlanRequest $request, NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $mealPlan->update($request->validated());

        return response()->json(['data' => new MealPlanResource($mealPlan->fresh()->load('days'))]);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/generate
     */
    public function generate(GenerateMealPlanRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        if (!$ncpRecord->intervention()->exists()) {
            return response()->json([
                'message' => 'Cannot generate a meal plan without an intervention.',
                'errors'  => ['intervention' => ['Intervention is required before generating a meal plan.']],
            ], 422);
        }

        $mealPlan = $this->mealPlanService->generate(
            $ncpRecord,
            $request->week_start_date,
            $request->conditions ?? [],
            $request->allergens ?? [],
        );

        return response()->json(['data' => new MealPlanResource($mealPlan)], 201);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/save-template
     */
    public function saveTemplate(\Illuminate\Http\Request $request, NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
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
            'data' => ['id' => $template->id, 'name' => $template->name, 'goal_type' => $template->goal_type],
        ], 201);
    }

    /**
     * GET /api/rnd/meal-plan-templates
     */
    public function templates(): JsonResponse
    {
        $templates = \App\Models\MealPlanTemplate::where('rnd_user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'description', 'goal_type', 'created_at']);

        return response()->json(['data' => $templates]);
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
