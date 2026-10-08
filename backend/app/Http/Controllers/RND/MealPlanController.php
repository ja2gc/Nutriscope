<?php

namespace App\Http\Controllers\RND;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\RND\GenerateMealPlanRequest;
use App\Http\Requests\RND\RecommendRequest;
use App\Http\Requests\RND\StoreMealPlanRequest;
use App\Http\Requests\RND\UpdateMealPlanRequest;
use App\Http\Resources\MealPlanResource;
use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\MealPlanTemplate;
use App\Models\MealPlanTemplateDay;
use App\Models\MealPlanTemplateItem;
use App\Models\NcpRecord;
use App\Models\Recipe;
use App\Policies\AuditPolicy;
use App\Services\Audit\AuditLogger;
use App\Services\ClinicalCompletenessService;
use App\Services\MealPlanService;
use App\Services\RecommendService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MealPlanController extends Controller
{
    public function __construct(
        private MealPlanService $mealPlanService,
        private RecommendService $recommendService,
        private ClinicalCompletenessService $completeness,
        private AuditPolicy $auditPolicy,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/meal-plans
     */
    public function index(NcpRecord $ncpRecord): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        $mealPlans = MealPlan::query()
            ->whereHas('intervention', fn ($query) => $query->where('ncp_record_id', $ncpRecord->id))
            ->with(['intervention', 'days'])
            ->latest('created_at')
            ->latest('id')
            ->get();

        return response()->json(['data' => MealPlanResource::collection($mealPlans)]);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans
     */
    public function store(StoreMealPlanRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        $intervention = $this->resolveInterventionPlan($ncpRecord, $request->string('intervention_plan_id')->toString());

        try {
            return $this->audited(function () use ($intervention, $ncpRecord, $request): JsonResponse {
                $lockedIntervention = $ncpRecord->interventions()->whereKey($intervention->id)->lockForUpdate()->firstOrFail();
                if ($lockedIntervention->mealPlan()->exists()) {
                    return $this->mealPlanConflict();
                }

                $mealPlan = MealPlan::create([
                    'intervention_id' => $lockedIntervention->id,
                    'patient_id' => $ncpRecord->patient_id,
                    'week_start_date' => $request->week_start_date,
                    'generation_type' => $request->generation_type ?? 'manual',
                    'status' => $request->status ?? 'draft',
                ]);

                $dayRows = [];
                foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
                    foreach (['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'] as $mealType) {
                        $dayRows[] = [
                            'uuid' => (string) Str::uuid(),
                            'meal_plan_id' => $mealPlan->id, 'day_of_week' => $day, 'meal_type' => $mealType, 'flagged' => false,
                        ];
                    }
                }
                MealPlanDay::insert($dayRows);

                return response()->json(['data' => new MealPlanResource($mealPlan->load(['intervention', 'days']))], 201);
            });
        } catch (UniqueConstraintViolationException) {
            return $this->mealPlanConflict();
        }
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}
     */
    public function show(NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertPlanScope($ncpRecord, $mealPlan);

        return response()->json(['data' => new MealPlanResource($mealPlan->load(['intervention', 'days']))]);
    }

    /** MP-04: the meal plan must belong to this NCP's intervention. */
    private function assertPlanScope(NcpRecord $ncpRecord, MealPlan $mealPlan): void
    {
        $this->authorizeNcp($ncpRecord);
        if (! $mealPlan->intervention()->where('ncp_record_id', $ncpRecord->id)->exists()) {
            abort(404);
        }
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}
     */
    public function update(UpdateMealPlanRequest $request, NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertPlanScope($ncpRecord, $mealPlan);
        $this->audited(fn () => $mealPlan->update($request->validated()));

        return response()->json(['data' => new MealPlanResource($mealPlan->fresh()->load(['intervention', 'days']))]);
    }

    public function scaleToPrescription(NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertPlanScope($ncpRecord, $mealPlan);
        if (! $mealPlan->needs_rescaling) {
            $message = $mealPlan->generation_type === 'auto'
                ? 'This automatically generated plan is already scaled to the current prescription.'
                : 'This plan is already scaled to the current prescription.';

            return response()->json(['message' => $message], 422);
        }

        $intervention = $ncpRecord->intervention()->firstOrFail();
        $scaling = $this->audited(function () use ($mealPlan, $intervention, $ncpRecord): array {
            $result = $this->auditLogger->withoutModelEvents(
                fn (): array => $this->mealPlanService->scaleToPrescription($mealPlan, $intervention)
            );
            $this->auditLogger->record(
                AuditAction::Updated,
                AuditCategory::Clinical,
                AuditDomain::Ncp,
                subject: $mealPlan,
                context: $ncpRecord,
                details: ['fields' => ['meal_plan_item_quantities', 'scaled_at'], 'status' => 200],
            );

            return $result;
        });

        return response()->json([
            'data' => new MealPlanResource($mealPlan->fresh()->load(['intervention', 'days'])),
            'meta' => ['scaling' => $scaling],
        ]);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/generate
     */
    public function generate(GenerateMealPlanRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail($request->user(), $ncpRecord), 403);
        $intervention = $this->resolveInterventionPlan($ncpRecord, $request->string('intervention_plan_id')->toString());
        // MP-01 / IV-02: a meal plan must be built against a real prescription.
        // Generating without energy/macro targets falls back to generic defaults
        // and produces a clinically meaningless plan.
        $missing = $this->completeness->interventionMissingFor($intervention);
        if (! empty($missing)) {
            return response()->json([
                'message' => 'Complete the nutrition prescription before generating a meal plan. Missing: '
                    .implode(', ', $missing).'.',
                'errors' => ['intervention' => $missing],
            ], 422);
        }

        if ($intervention->mealPlan()->exists()) {
            return $this->mealPlanConflict();
        }

        try {
            $result = $this->audited(function () use ($request, $ncpRecord, $intervention) {
                $lockedIntervention = $ncpRecord->interventions()
                    ->whereKey($intervention->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($lockedIntervention->mealPlan()->exists()) {
                    return null;
                }

                $result = $this->auditLogger->withoutModelEvents(fn () => $this->mealPlanService->generate(
                    $ncpRecord,
                    $request->week_start_date,
                    $request->conditions ?? [],
                    $request->allergens ?? [],
                    $request->boolean('exclude_snacks'),
                    $request->boolean('use_rice_as_carb'),
                    $lockedIntervention,
                ));

                if ($result instanceof MealPlan) {
                    $this->auditLogger->record(
                        AuditAction::Generated,
                        AuditCategory::Clinical,
                        AuditDomain::Ncp,
                        subject: $result,
                        context: $ncpRecord,
                        details: ['status' => 201],
                    );
                }

                return $result;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->mealPlanConflict();
        }

        if ($result === null) {
            return $this->mealPlanConflict();
        }

        if (is_array($result)) {
            return response()->json($result, 422);
        }

        return response()->json(['data' => new MealPlanResource($result->load('intervention'))], 201);
    }

    /**
     * DELETE /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}
     */
    public function destroy(NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertPlanScope($ncpRecord, $mealPlan);
        $this->audited(fn () => $mealPlan->delete());

        return response()->json(null, 204);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/save-template
     */
    public function saveTemplate(Request $request, NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail($request->user(), $ncpRecord), 403);
        $this->assertPlanScope($ncpRecord, $mealPlan);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'goal_type' => 'nullable|string|max:255',
            'disease_stage' => 'nullable|string|max:100',
        ]);

        $mealPlan->loadMissing('days.items');
        $template = $this->audited(function () use ($validated, $ncpRecord, $mealPlan, $request) {
            $template = MealPlanTemplate::create([
                'rnd_user_id' => $request->user()->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'goal_type' => $validated['goal_type'] ?? $ncpRecord->intervention?->goal_type,
                'disease_stage' => $validated['disease_stage'] ?? $ncpRecord->intervention?->disease_stage,
                'maternal_status' => $this->templateMaternalStatus(
                    $ncpRecord->assessment?->pregnancy_lactation_status,
                ),
            ]);

            foreach ($mealPlan->days as $day) {
                $items = $day->items->sortBy('id')->values();
                $firstItem = $items->first();
                $templateDay = MealPlanTemplateDay::create([
                    'template_id' => $template->id,
                    'day_of_week' => $day->day_of_week,
                    'meal_type' => $day->meal_type,
                    'food_item_id' => $firstItem?->food_item_id,
                    'recipe_id' => $firstItem?->recipe_id,
                    'quantity' => $firstItem?->quantity ?? 1,
                    'unit' => $firstItem?->unit ?? 'serving',
                ]);

                foreach ($items as $index => $item) {
                    MealPlanTemplateItem::create([
                        'template_day_id' => $templateDay->id,
                        'food_item_id' => $item->food_item_id,
                        'recipe_id' => $item->recipe_id,
                        'fdc_id' => $item->fdc_id,
                        'quantity' => $item->quantity,
                        'unit' => $item->unit,
                        'nutrient_snapshot' => $item->nutrient_snapshot,
                        'ai_suggested' => $item->ai_suggested,
                        'line_order' => $index + 1,
                    ]);
                }
            }
            $this->auditLogger->record(
                AuditAction::Created,
                AuditCategory::Clinical,
                AuditDomain::Ncp,
                subject: $mealPlan,
                context: $ncpRecord,
                details: ['fields' => ['meal_plan_template'], 'status' => 201],
            );

            return $template;
        });

        return response()->json([
            'data' => [
                'id' => $template->uuid,
                'name' => $template->name,
                'goal_type' => $template->goal_type,
                'disease_stage' => $template->disease_stage,
                'maternal_status' => $template->maternal_status,
            ],
        ], 201);
    }

    /**
     * GET /api/rnd/meal-plan-templates
     */
    public function templates(PaginatedRequest $request): JsonResponse
    {
        $templates = MealPlanTemplate::where('rnd_user_id', auth()->id())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage(), ['id', 'uuid', 'name', 'description', 'goal_type', 'disease_stage', 'maternal_status', 'created_at'])
            ->withQueryString();

        $templates->through(fn ($t) => [
            'id' => $t->uuid, 'name' => $t->name, 'description' => $t->description,
            'goal_type' => $t->goal_type, 'disease_stage' => $t->disease_stage, 'created_at' => $t->created_at,
            'maternal_status' => $t->maternal_status,
        ]);

        return response()->json([
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
                'last_page' => $templates->lastPage(),
            ],
        ]);
    }

    public function storeLibraryTemplate(Request $request): JsonResponse
    {
        [$attributes, $lines] = $this->validatedLibraryTemplate($request);
        $template = $this->audited(function () use ($request, $attributes, $lines): MealPlanTemplate {
            $template = MealPlanTemplate::create([
                ...$attributes,
                'rnd_user_id' => $request->user()->id,
            ]);
            $this->writeLibraryTemplateLines($template, $lines);
            $this->auditLogger->record(
                AuditAction::Created,
                AuditCategory::Clinical,
                AuditDomain::NutritionLibrary,
                subject: $template,
                details: ['fields' => ['meal_plan_template'], 'status' => 201],
            );

            return $template;
        });

        return $this->showTemplate($template)->setStatusCode(201);
    }

    public function updateLibraryTemplate(Request $request, MealPlanTemplate $template): JsonResponse
    {
        $this->assertTemplateOwner($template);
        [$attributes, $lines] = $this->validatedLibraryTemplate($request);
        $this->audited(function () use ($template, $attributes, $lines): void {
            $template->update($attributes);
            $template->days()->delete();
            $this->writeLibraryTemplateLines($template, $lines);
            $this->auditLogger->record(
                AuditAction::Updated,
                AuditCategory::Clinical,
                AuditDomain::NutritionLibrary,
                subject: $template,
                details: ['fields' => ['meal_plan_template'], 'status' => 200],
            );
        });

        return $this->showTemplate($template->fresh());
    }

    /**
     * GET /api/rnd/meal-plan-templates/{template}
     */
    public function showTemplate(MealPlanTemplate $template): JsonResponse
    {
        $this->assertTemplateOwner($template);
        $template->load(['days.foodItem', 'days.recipe', 'days.items.foodItem', 'days.items.recipe']);

        $days = $template->days->map(function ($day): array {
            $items = $day->items->map(fn ($item) => [
                'id' => $item->uuid,
                'food_item_id' => $item->foodItem?->uuid,
                'recipe_id' => $item->recipe?->uuid,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'food_name' => $item->nutrient_snapshot['name']
                    ?? $item->foodItem?->name
                    ?? $item->recipe?->name,
                'nutrient_snapshot' => $item->nutrient_snapshot,
                'line_order' => $item->line_order,
            ])->values();
            $firstItem = $items->first();

            return [
                'id' => $day->id,
                'day_of_week' => $day->day_of_week,
                'meal_type' => $day->meal_type,
                'quantity' => $firstItem['quantity'] ?? $day->quantity,
                'unit' => $firstItem['unit'] ?? $day->unit,
                'food_name' => $firstItem['food_name'] ?? $day->foodItem?->name ?? $day->recipe?->name,
                'calories' => $firstItem['nutrient_snapshot']['calories']
                    ?? $day->foodItem?->calories
                    ?? $day->recipe?->total_calories,
                'items' => $items,
            ];
        });

        return response()->json(['data' => [
            'id' => $template->uuid,
            'name' => $template->name,
            'description' => $template->description,
            'goal_type' => $template->goal_type,
            'disease_stage' => $template->disease_stage,
            'maternal_status' => $template->maternal_status,
            'created_at' => $template->created_at,
            'days' => $days,
        ]]);
    }

    /**
     * DELETE /api/rnd/meal-plan-templates/{template}
     */
    public function destroyTemplate(MealPlanTemplate $template): JsonResponse
    {
        $this->assertTemplateOwner($template);
        $template->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/from-template
     */
    public function fromTemplate(Request $request, NcpRecord $ncpRecord): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        $validated = $request->validate([
            'template_id' => 'required|string|exists:meal_plan_templates,uuid',
            'intervention_plan_id' => 'required|uuid',
            'week_start_date' => 'required|date',
        ]);

        // The picker submits the template's public uuid (its Resource 'id').
        $intervention = $this->resolveInterventionPlan($ncpRecord, $validated['intervention_plan_id']);
        $template = MealPlanTemplate::with('days.items')
            ->where('uuid', $validated['template_id'])
            ->where('rnd_user_id', $request->user()->id)
            ->firstOrFail();

        try {
            $plan = $this->audited(function () use ($intervention, $ncpRecord, $validated, $template): ?MealPlan {
                $lockedIntervention = $ncpRecord->interventions()
                    ->whereKey($intervention->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($lockedIntervention->mealPlan()->exists()) {
                    return null;
                }

                $plan = MealPlan::create([
                    'intervention_id' => $lockedIntervention->id,
                    'patient_id' => $ncpRecord->patient_id,
                    'week_start_date' => $validated['week_start_date'],
                    'generation_type' => 'manual',
                    'needs_rescaling' => true,
                    'status' => 'draft',
                ]);

                foreach ($template->days as $tDay) {
                    $day = MealPlanDay::create([
                        'meal_plan_id' => $plan->id,
                        'day_of_week' => $tDay->day_of_week,
                        'meal_type' => $tDay->meal_type,
                    ]);
                    $templateItems = $tDay->items;
                    if ($templateItems->isEmpty() && ($tDay->food_item_id || $tDay->recipe_id)) {
                        $templateItems = collect([$tDay]);
                    }

                    foreach ($templateItems as $templateItem) {
                        $snapshot = $templateItem->nutrient_snapshot
                            ?? $this->snapshotForTemplateItem($templateItem->food_item_id, $templateItem->recipe_id);
                        MealPlanItem::create([
                            'meal_plan_day_id' => $day->id,
                            'food_item_id' => $templateItem->food_item_id,
                            'recipe_id' => $templateItem->recipe_id,
                            'fdc_id' => $templateItem->fdc_id ?? null,
                            'quantity' => $templateItem->quantity,
                            'unit' => $templateItem->unit,
                            'nutrient_snapshot' => $snapshot,
                            'ai_suggested' => $templateItem->ai_suggested ?? false,
                        ]);
                    }
                }

                return $plan;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->mealPlanConflict();
        }

        if ($plan === null) {
            return $this->mealPlanConflict();
        }

        $goalMatches = $template->goal_type === null || $template->goal_type === $intervention->goal_type;
        $stageMatches = $template->disease_stage === null || $template->disease_stage === $intervention->disease_stage;
        $patientMaternalStatus = $ncpRecord->assessment?->pregnancy_lactation_status;
        $maternalMatches = $this->maternalTemplateMatches($template->maternal_status, $patientMaternalStatus);
        $requiresGoalReview = $template->maternal_status !== null
            && $template->goal_type === null
            && $intervention->goal_type !== 'custom'
            && $maternalMatches;

        return response()->json([
            'data' => new MealPlanResource($plan->load(['intervention', 'days.items'])),
            'meta' => [
                'template_compatibility' => [
                    'goal_matches' => $goalMatches,
                    'disease_stage_matches' => $stageMatches,
                    'maternal_status_matches' => $maternalMatches,
                    'warning' => $goalMatches && $stageMatches && $maternalMatches && ! $requiresGoalReview
                        ? null
                        : ($requiresGoalReview
                            ? 'This general maternal template still needs review against the intervention goal before scaling.'
                            : 'This template was created for a different intervention goal, disease stage, or maternal status. Review and scale it before use.'),
                ],
            ],
        ], 201);
    }

    private function mealPlanConflict(): JsonResponse
    {
        return response()->json([
            'message' => 'This Intervention Plan already has a menu plan.',
        ], 409);
    }

    private function resolveInterventionPlan(NcpRecord $ncpRecord, string $uuid): Intervention
    {
        return $ncpRecord->interventions()->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/intervention/recommend
     */
    public function recommend(RecommendRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        $result = $this->recommendService->getRecommendations(
            $request->conditions,
            $request->stages ?? null,
        );

        return response()->json(['data' => $result]);
    }

    /** @return array{0: array<string, mixed>, 1: list<array<string, mixed>>} */
    private function validatedLibraryTemplate(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'goal_type' => ['nullable', 'string', 'max:255'],
            'disease_stage' => ['nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:1', 'max:350'],
            'lines.*.day_of_week' => ['required', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'lines.*.meal_type' => ['required', 'in:breakfast,am_snack,lunch,pm_snack,dinner'],
            'lines.*.food_item_id' => ['nullable', 'uuid', 'exists:food_items,uuid'],
            'lines.*.recipe_id' => ['nullable', 'uuid', 'exists:recipes,uuid'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'lte:99999.99'],
            'lines.*.unit' => ['required', 'string', 'max:40'],
        ]);
        $lines = $validated['lines'];
        $foodIds = FoodItem::query()->whereIn('uuid', collect($lines)->pluck('food_item_id')->filter()->all())->pluck('id', 'uuid');
        $recipeIds = Recipe::query()->where('rnd_user_id', $request->user()->id)
            ->whereIn('uuid', collect($lines)->pluck('recipe_id')->filter()->all())->pluck('id', 'uuid');

        foreach ($lines as $index => &$line) {
            $foodUuid = $line['food_item_id'] ?? null;
            $recipeUuid = $line['recipe_id'] ?? null;
            if (($foodUuid === null) === ($recipeUuid === null)) {
                throw ValidationException::withMessages(["lines.{$index}" => 'Choose exactly one food or recipe.']);
            }
            if ($recipeUuid !== null && ! $recipeIds->has($recipeUuid)) {
                throw ValidationException::withMessages(["lines.{$index}.recipe_id" => 'This recipe is unavailable.']);
            }
            $line['food_item_id'] = $foodUuid === null ? null : (int) $foodIds[$foodUuid];
            $line['recipe_id'] = $recipeUuid === null ? null : (int) $recipeIds[$recipeUuid];
        }
        unset($line);
        unset($validated['lines']);

        return [$validated, $lines];
    }

    /** @param list<array<string, mixed>> $lines */
    private function writeLibraryTemplateLines(MealPlanTemplate $template, array $lines): void
    {
        $groups = [];
        foreach ($lines as $line) {
            $groups[$line['day_of_week'].'|'.$line['meal_type']][] = $line;
        }

        foreach ($groups as $group) {
            $first = $group[0];
            $day = MealPlanTemplateDay::create([
                'template_id' => $template->id,
                'day_of_week' => $first['day_of_week'],
                'meal_type' => $first['meal_type'],
                'food_item_id' => $first['food_item_id'],
                'recipe_id' => $first['recipe_id'],
                'quantity' => $first['quantity'],
                'unit' => $first['unit'],
            ]);
            foreach ($group as $index => $line) {
                MealPlanTemplateItem::create([
                    'template_day_id' => $day->id,
                    'food_item_id' => $line['food_item_id'],
                    'recipe_id' => $line['recipe_id'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'nutrient_snapshot' => $this->snapshotForTemplateItem($line['food_item_id'], $line['recipe_id']),
                    'ai_suggested' => false,
                    'line_order' => $index + 1,
                ]);
            }
        }
    }

    private function assertTemplateOwner(MealPlanTemplate $template): void
    {
        abort_unless($template->rnd_user_id === request()->user()?->id, 404);
    }

    private function templateMaternalStatus(?string $status): ?string
    {
        return match (true) {
            is_string($status) && str_starts_with($status, 'pregnant_') && $status !== 'pregnant_unspecified' => 'pregnant',
            $status === 'lactating' => 'lactating',
            default => null,
        };
    }

    private function maternalTemplateMatches(?string $templateStatus, ?string $patientStatus): bool
    {
        if ($templateStatus === null) {
            return true;
        }

        $patientGroup = $this->templateMaternalStatus($patientStatus);

        return $templateStatus === 'pregnant_or_lactating'
            ? in_array($patientGroup, ['pregnant', 'lactating'], true)
            : $templateStatus === $patientGroup;
    }

    /** @return array<string,mixed>|null */
    private function snapshotForTemplateItem(?int $foodItemId, ?int $recipeId): ?array
    {
        if ($foodItemId !== null && ($food = FoodItem::find($foodItemId))) {
            return [
                'name' => $food->name,
                'calories' => (float) $food->calories,
                'protein' => (float) $food->protein,
                'carbs' => (float) $food->carbs,
                'fat' => (float) $food->fat,
                'water_g' => (float) ($food->water_g ?? 0),
                'micronutrients' => $food->micronutrients ?? [],
                'serving_size' => (float) ($food->serving_size ?? 100),
                'serving_unit' => $food->serving_unit ?? 'g',
                'source' => 'food_item',
            ];
        }

        if ($recipeId !== null && ($recipe = Recipe::find($recipeId))) {
            return [
                'name' => $recipe->name,
                'calories' => (float) $recipe->total_calories,
                'protein' => (float) $recipe->total_protein,
                'carbs' => (float) $recipe->total_carbs,
                'fat' => (float) $recipe->total_fat,
                'water_g' => (float) ($recipe->total_water_g ?? 0),
                'micronutrients' => $recipe->micronutrients ?? [],
                'serving_size' => (float) ($recipe->servings ?? 1),
                'serving_unit' => 'serving',
                'source' => 'recipe',
            ];
        }

        return null;
    }

    private function authorizeNcp(NcpRecord $ncpRecord): void
    {
        abort_unless($this->auditPolicy->viewNcpTrail(request()->user(), $ncpRecord), 403);
    }
}
