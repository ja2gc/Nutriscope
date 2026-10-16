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
use App\Http\Resources\MealPlanItemResource;
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
use App\Support\UnitConverter;
use Illuminate\Database\Eloquent\Collection as ModelCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        $this->assertInterventionEditable($ncpRecord, $intervention->id);

        try {
            return $this->audited(function () use ($intervention, $ncpRecord, $request): JsonResponse {
                $lockedIntervention = $ncpRecord->interventions()->whereKey($intervention->id)->lockForUpdate()->firstOrFail();
                $this->assertInterventionEditable($ncpRecord, $lockedIntervention->id);
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

    private function assertInterventionEditable(NcpRecord $ncpRecord, int $interventionId): void
    {
        abort_unless((int) $ncpRecord->latestIntervention()->value('id') === $interventionId, 403, 'Historical intervention menus are read-only.');
    }

    private function assertPlanWritable(NcpRecord $ncpRecord, MealPlan $mealPlan): void
    {
        $this->assertPlanScope($ncpRecord, $mealPlan);
        $this->assertInterventionEditable($ncpRecord, $mealPlan->intervention_id);
        abort_if($mealPlan->status === 'completed', 403, 'Completed menus are read-only.');
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}
     */
    public function update(UpdateMealPlanRequest $request, NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertPlanWritable($ncpRecord, $mealPlan);
        $this->audited(fn () => $mealPlan->update($request->validated()));

        return response()->json(['data' => new MealPlanResource($mealPlan->fresh()->load(['intervention', 'days']))]);
    }

    public function scaleToPrescription(NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertPlanWritable($ncpRecord, $mealPlan);
        if (! $mealPlan->needs_rescaling) {
            $message = $mealPlan->generation_type === 'auto'
                ? 'This automatically generated plan is already scaled to the current prescription.'
                : 'This plan is already scaled to the current prescription.';

            return response()->json(['message' => $message], 422);
        }

        $intervention = $mealPlan->intervention()->firstOrFail();
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

        $this->assertInterventionEditable($ncpRecord, $intervention->id);
        if ($intervention->mealPlan()->exists()) {
            return $this->mealPlanConflict();
        }

        try {
            $result = $this->audited(function () use ($request, $ncpRecord, $intervention) {
                $lockedIntervention = $ncpRecord->interventions()
                    ->whereKey($intervention->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->assertInterventionEditable($ncpRecord, $lockedIntervention->id);
                if ($lockedIntervention->mealPlan()->exists()) {
                    return null;
                }

                $result = $this->auditLogger->withoutModelEvents(fn () => $this->mealPlanService->generate(
                    $ncpRecord,
                    $request->week_start_date,
                    $request->conditions ?? [],
                    $request->allergens ?? [],
                    $request->boolean('exclude_snacks'),
                    $request->boolean('exclude_carbs'),
                    $lockedIntervention,
                    $request->input('carb_menu_item_id'),
                ));

                if ($result instanceof MealPlan) {
                    $this->auditLogger->record(
                        AuditAction::Created,
                        AuditCategory::Clinical,
                        AuditDomain::Ncp,
                        subject: $result,
                        context: $ncpRecord,
                        details: ['subject_label' => 'Meal plan generated', 'status' => 201],
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
        $this->assertPlanWritable($ncpRecord, $mealPlan);
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
            'template_id' => ['required', 'uuid'],
            'intervention_plan_id' => ['required', 'uuid'],
            'meal_plan_id' => ['nullable', 'uuid'],
            'week_start_date' => ['required', 'date'],
            'preview' => ['sometimes', 'boolean'],
            'scale' => ['sometimes', 'boolean'],
            'lines' => ['sometimes', 'array'],
            'lines.*.source_template_item_id' => ['nullable', 'uuid'],
            'lines.*.recipe_id' => ['required', 'uuid'],
            'lines.*.food_item_id' => ['prohibited'],
            'lines.*.fdc_id' => ['prohibited'],
            'lines.*.nutrient_snapshot' => ['prohibited'],
            'lines.*.day_of_week' => ['required', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'lines.*.meal_type' => ['required', Rule::in(['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'])],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
            'lines.*.unit' => ['required', Rule::in(['serving'])],
            'lines.*.ingredient_overrides' => ['sometimes', 'array'],
            'lines.*.ingredient_overrides.*.id' => ['required', 'integer'],
            'lines.*.ingredient_overrides.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,6'],
        ]);
        $intervention = $this->resolveInterventionPlan($ncpRecord, $validated['intervention_plan_id']);
        $this->assertInterventionEditable($ncpRecord, $intervention->id);
        $template = MealPlanTemplate::with('days.items.recipe.ingredients.foodItem')
            ->where('uuid', $validated['template_id'])->where('rnd_user_id', $request->user()->id)->firstOrFail();
        $preview = $request->boolean('preview');
        $lines = $this->resolvedTemplateLines($template, $validated['lines'] ?? null);
        $build = function () use ($intervention, $ncpRecord, $validated, $lines, $preview, $request): ?MealPlan {
            $lockedIntervention = $ncpRecord->interventions()->whereKey($intervention->id)->lockForUpdate()->firstOrFail();
            $this->assertInterventionEditable($ncpRecord, $lockedIntervention->id);
            $existing = $lockedIntervention->mealPlan()->lockForUpdate()->first();
            abort_if($existing?->status === 'completed', 403, 'Completed menus are read-only.');
            if (isset($validated['meal_plan_id'])) {
                abort_unless($existing?->uuid === $validated['meal_plan_id'], 404);
                abort_if($existing->status === 'completed', 403, 'Completed menus are read-only.');
            } elseif ($existing && ! $preview) {
                return null;
            }
            $attributes = [
                'intervention_id' => $lockedIntervention->id, 'patient_id' => $ncpRecord->patient_id,
                'week_start_date' => $validated['week_start_date'], 'generation_type' => 'manual',
                'needs_rescaling' => true, 'scaled_at' => null, 'status' => 'draft',
            ];
            $plan = $preview ? new MealPlan($attributes) : ($existing ?? new MealPlan);
            if ($preview) {
                $plan->forceFill(['uuid' => (string) Str::uuid()]);
            } else {
                $plan->fill($attributes)->save();
                $plan->days()->delete();
            }
            $days = new ModelCollection;
            $itemIndex = 0;
            foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $dayName) {
                foreach (['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'] as $slot) {
                    $day = new MealPlanDay(['meal_plan_id' => $plan->id, 'day_of_week' => $dayName, 'meal_type' => $slot, 'flagged' => false]);
                    if ($preview) {
                        $day->forceFill(['id' => $days->count() + 1, 'uuid' => (string) Str::uuid()]);
                    } else {
                        $day->save();
                    }
                    $items = new ModelCollection;
                    foreach ($lines as $line) {
                        if ($line['day_of_week'] !== $dayName || $line['meal_type'] !== $slot) {
                            continue;
                        }
                        $item = new MealPlanItem([
                            'meal_plan_day_id' => $day->id, 'recipe_id' => $line['recipe']->id, 'food_item_id' => null,
                            'quantity' => $line['quantity'], 'unit' => 'serving', 'nutrient_snapshot' => $line['snapshot'], 'ai_suggested' => false,
                        ]);
                        if ($preview) {
                            $item->forceFill(['id' => ++$itemIndex, 'uuid' => (string) Str::uuid()]);
                        } else {
                            $item->save();
                        }
                        $item->setRelation('mealPlanDay', $day)->setRelation('recipe', $line['recipe'])->setRelation('foodItem', null);
                        $item->setRelation('templateOrigin', $line['source']);
                        $item->setRelation('draftOverrides', $line['ingredient_overrides'] ?? []);
                        $items->push($item);
                    }
                    $day->setRelation('items', $items);
                    $days->push($day);
                }
            }
            $plan->setRelation('days', $days)->setRelation('intervention', $lockedIntervention);
            if ($request->boolean('scale')) {
                $missing = $this->completeness->interventionMissingFor($lockedIntervention);
                if ($missing !== []) {
                    throw ValidationException::withMessages(['intervention' => 'Complete the prescription before scaling this draft.']);
                }
                $this->mealPlanService->scaleToPrescription($plan, $lockedIntervention, ! $preview);
            }

            return $plan;
        };
        try {
            $plan = $preview ? $build() : $this->audited($build);
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
            'data' => new MealPlanResource($plan),
            'items' => MealPlanItemResource::collection($plan->days->flatMap->items),
            'meta' => [
                'preview' => $preview,
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
        ], $preview || isset($validated['meal_plan_id']) ? 200 : 201);
    }

    /** Resolve draft source identities and quantities; never trust client nutrient values. */
    private function resolvedTemplateLines(MealPlanTemplate $template, ?array $draftLines): array
    {
        $sources = $template->days->flatMap->items->keyBy('uuid');
        if ($draftLines === null) {
            $draftLines = [];
            foreach ($template->days as $day) {
                if ($day->items->isEmpty() && ($day->food_item_id || $day->recipe_id)) {
                    throw ValidationException::withMessages(['template' => 'Correct legacy template lines in Food Library before loading this template.']);
                }
                foreach ($day->items as $item) {
                    $draftLines[] = [
                        'source_template_item_id' => $item->uuid, 'recipe_id' => $item->recipe?->uuid,
                        'day_of_week' => $day->day_of_week, 'meal_type' => $day->meal_type,
                        'quantity' => $item->quantity, 'unit' => $item->unit,
                    ];
                }
            }
        }
        $lines = [];
        foreach ($draftLines as $line) {
            $source = isset($line['source_template_item_id']) ? $sources->get($line['source_template_item_id']) : null;
            $recipe = Recipe::with('ingredients.foodItem')->where('uuid', $line['recipe_id'])->first();
            if (! $recipe || ! in_array($recipe->category, ['meal', 'carb', 'snack'], true) || ! $recipe->hasValidPortionData()
                || (isset($line['source_template_item_id']) && (! $source || $source->recipe_id !== $recipe->id || $source->food_item_id || $source->fdc_id))) {
                throw ValidationException::withMessages(['template' => 'Choose valid measured Menu Items belonging to this template, or correct the template in Food Library.']);
            }
            $quantity = (float) $line['quantity'];
            if ($line['unit'] !== 'serving') {
                try {
                    $quantity = UnitConverter::scalingFactor($quantity, $line['unit'], (float) $recipe->prepared_portion_amount, (string) $recipe->prepared_portion_unit);
                } catch (\InvalidArgumentException) {
                    throw ValidationException::withMessages(['template' => 'This legacy line has no compatible measured portion. Correct its servings in Food Library before loading.']);
                }
            }
            if ($quantity < 0.01 || $quantity > 999999.99 || abs($quantity - round($quantity, 2)) > 0.000001) {
                throw ValidationException::withMessages(['template' => 'Correct this template to positive servings with at most two decimals before loading.']);
            }
            try {
                $snapshot = ! empty($line['ingredient_overrides'])
                    ? $recipe->planSnapshot($line['ingredient_overrides'])
                    : ($source?->nutrient_snapshot ?? $recipe->planSnapshot());
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['template' => $exception->getMessage()]);
            }
            if (! is_array($snapshot) || (float) ($snapshot['serving_size'] ?? 0) <= 0 || ($snapshot['serving_unit'] ?? '') !== 'serving') {
                throw ValidationException::withMessages(['template' => 'Correct this template snapshot to a measured serving basis in Food Library before loading.']);
            }
            if (! empty($line['ingredient_overrides']) && isset($source?->nutrient_snapshot['name'])) {
                $snapshot['name'] = $source->nutrient_snapshot['name'];
            }
            $lines[] = [
                ...$line, 'quantity' => round($quantity, 2), 'recipe' => $recipe, 'snapshot' => $snapshot, 'source' => $source,
            ];
        }

        return $lines;
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
            'lines.*.food_item_id' => ['prohibited'],
            'lines.*.fdc_id' => ['prohibited'],
            'lines.*.recipe_id' => ['required', 'uuid', 'exists:recipes,uuid'],
            'lines.*.quantity' => ['required', 'numeric', 'between:0.01,99999.99', 'decimal:0,2'],
            'lines.*.unit' => ['required', 'in:serving'],
        ]);
        $lines = $validated['lines'];
        $recipes = Recipe::query()->where('rnd_user_id', $request->user()->id)
            ->whereIn('category', ['meal', 'carb', 'snack'])->with('ingredients.foodItem')
            ->whereIn('uuid', collect($lines)->pluck('recipe_id')->all())->get()->keyBy('uuid');

        foreach ($lines as $index => &$line) {
            $recipe = $recipes->get($line['recipe_id']);
            if ($recipe === null || ! $recipe->hasValidPortionData()) {
                throw ValidationException::withMessages(["lines.{$index}.recipe_id" => 'Choose an available Menu Item with a valid yield and measured Ingredient units.']);
            }
            $line['food_item_id'] = null;
            $line['recipe_id'] = $recipe->id;
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
