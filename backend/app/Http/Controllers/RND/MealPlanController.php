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
        $mealPlans = MealPlan::where('intervention_id', $intervention->id)->get();

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
