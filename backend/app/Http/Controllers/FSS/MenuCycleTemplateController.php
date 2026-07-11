<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleTemplate;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MenuCycleTemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): JsonResponse
    {
        $templates = MenuCycleTemplate::withCount('days')->orderBy('name')->get();

        return response()->json(['data' => $templates->map(fn ($t) => [
            'id' => $t->uuid, 'name' => $t->name, 'description' => $t->description,
            'cycle_days' => $t->cycle_days, 'days_count' => $t->days_count, 'updated_at' => $t->updated_at,
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $template = $this->audited(function () use ($data): MenuCycleTemplate {
            $template = DB::transaction(function () use ($data) {
                $template = MenuCycleTemplate::create([
                    'rnd_user_id' => Auth::id(),
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'cycle_days' => $data['cycle_days'] ?? 7,
                ]);
                $this->syncDays($template, $data['days'] ?? []);

                return $template;
            });
            $fields = array_map(
                fn (string $field): string => $field === 'description' ? 'content' : $field,
                array_keys($template->getAttributes()),
            );
            if ($template->days()->exists()) {
                $fields[] = 'days';
            }
            $this->auditLogger->recordMutation(AuditAction::Created, AuditDomain::FoodService, $template, $fields);

            return $template;
        });

        return response()->json(['data' => $this->format($template)], 201);
    }

    public function show(MenuCycleTemplate $menuCycleTemplate): JsonResponse
    {
        return response()->json(['data' => $this->format($menuCycleTemplate)]);
    }

    public function update(Request $request, MenuCycleTemplate $menuCycleTemplate): JsonResponse
    {
        $data = $this->validatePayload($request, false);
        $beforeDays = $this->daySignature($menuCycleTemplate);

        $this->audited(function () use ($menuCycleTemplate, $data, $beforeDays): void {
            DB::transaction(function () use ($menuCycleTemplate, $data) {
                $menuCycleTemplate->update(array_filter([
                    'name' => $data['name'] ?? null,
                    'description' => $data['description'] ?? null,
                    'cycle_days' => $data['cycle_days'] ?? null,
                ], fn ($v) => $v !== null));
                if (isset($data['days'])) {
                    $this->syncDays($menuCycleTemplate, $data['days']);
                }
            });
            $fields = array_map(
                fn (string $field): string => $field === 'description' ? 'content' : $field,
                array_keys($menuCycleTemplate->getChanges()),
            );
            if (isset($data['days']) && $beforeDays !== $this->daySignature($menuCycleTemplate)) {
                $fields[] = 'days';
            }
            $this->auditLogger->recordMutation(AuditAction::Updated, AuditDomain::FoodService, $menuCycleTemplate, $fields);
        });

        return response()->json(['data' => $this->format($menuCycleTemplate->fresh())]);
    }

    public function destroy(MenuCycleTemplate $menuCycleTemplate): JsonResponse
    {
        $this->audited(function () use ($menuCycleTemplate): void {
            $menuCycleTemplate->delete();
            $this->auditLogger->recordMutation(AuditAction::Deleted, AuditDomain::FoodService, $menuCycleTemplate, []);
        });

        return response()->json(null, 204);
    }

    /** Save an existing menu cycle as a reusable template (copies its day grid). */
    public function fromCycle(Request $request, MenuCycle $menuCycle): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $template = $this->audited(function () use ($data, $menuCycle): MenuCycleTemplate {
            $template = DB::transaction(function () use ($data, $menuCycle) {
                $template = MenuCycleTemplate::create([
                    'rnd_user_id' => Auth::id(),
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'cycle_days' => $menuCycle->cycle_days ?? 7,
                ]);
                foreach ($menuCycle->days as $d) {
                    $template->days()->create([
                        'day_of_week' => $d->day_of_week,
                        'meal_type' => $d->meal_type,
                        'recipe_id' => $d->recipe_id,
                        'fs_item_id' => $d->fs_item_id,
                        'quantity' => $d->quantity,
                    ]);
                }

                return $template;
            });
            $fields = array_map(
                fn (string $field): string => $field === 'description' ? 'content' : $field,
                array_keys($template->getAttributes()),
            );
            if ($template->days()->exists()) {
                $fields[] = 'days';
            }
            $this->auditLogger->recordMutation(AuditAction::Created, AuditDomain::FoodService, $template, $fields, ['source' => 'menu_cycle']);

            return $template;
        });

        return response()->json(['data' => $this->format($template)], 201);
    }

    /** Create a new draft menu cycle from this template (copies the day grid). */
    public function instantiate(Request $request, MenuCycleTemplate $menuCycleTemplate): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'week_start_date' => ['nullable', 'date'],
        ]);

        $cycle = $this->audited(function () use ($data, $menuCycleTemplate): MenuCycle {
            $cycle = $this->auditLogger->withoutModelEvents(fn () => DB::transaction(function () use ($data, $menuCycleTemplate) {
                $cycle = MenuCycle::create([
                    'rnd_user_id' => Auth::id(),
                    'name' => $data['name'] ?? ($menuCycleTemplate->name.' — '.now()->format('M j')),
                    'cycle_days' => $menuCycleTemplate->cycle_days,
                    'week_start_date' => $data['week_start_date'] ?? now()->toDateString(),
                    'status' => 'draft',
                ]);
                foreach ($menuCycleTemplate->days as $d) {
                    $cycle->days()->create([
                        'day_of_week' => $d->day_of_week,
                        'meal_type' => $d->meal_type,
                        'recipe_id' => $d->recipe_id,
                        'fs_item_id' => $d->fs_item_id,
                        'quantity' => $d->quantity,
                    ]);
                }

                return $cycle;
            }));
            $fields = array_keys($cycle->getAttributes());
            if ($cycle->days()->exists()) {
                $fields[] = 'days';
            }
            $this->auditLogger->recordMutation(AuditAction::Created, AuditDomain::FoodService, $cycle, $fields, ['source' => 'menu_cycle_template']);

            return $cycle;
        });

        return response()->json(['data' => ['id' => $cycle->uuid, 'name' => $cycle->name]], 201);
    }

    private function validatePayload(Request $request, bool $nameRequired = true): array
    {
        $data = $request->validate([
            'name' => [$nameRequired ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cycle_days' => ['nullable', 'integer', 'min:1', 'max:28'],
            'days' => ['nullable', 'array'],
            'days.*.day_of_week' => ['required_with:days', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'days.*.meal_type' => ['required_with:days', 'in:breakfast,am_snack,lunch,pm_snack,dinner'],
            'days.*.recipe_id' => ['nullable', 'string', 'exists:food_service_recipes,uuid'],
            'days.*.fs_item_id' => ['nullable', 'string', 'exists:fs_items,uuid'],
            'days.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! empty($data['days'])) {
            $data['days'] = collect($data['days'])->map(function ($d) {
                if (! empty($d['recipe_id'])) {
                    $d['recipe_id'] = FoodServiceRecipe::idFromUuid($d['recipe_id']);
                }
                if (! empty($d['fs_item_id'])) {
                    $d['fs_item_id'] = FsItem::idFromUuid($d['fs_item_id']);
                }

                return $d;
            })->all();
        }

        return $data;
    }

    private function syncDays(MenuCycleTemplate $template, array $days): void
    {
        $template->days()->delete();
        foreach ($days as $d) {
            if (empty($d['recipe_id']) && empty($d['fs_item_id'])) {
                continue;
            }
            $template->days()->create([
                'day_of_week' => $d['day_of_week'],
                'meal_type' => $d['meal_type'],
                'recipe_id' => $d['recipe_id'] ?? null,
                'fs_item_id' => $d['fs_item_id'] ?? null,
                'quantity' => $d['quantity'] ?? 1,
            ]);
        }
    }

    private function format(MenuCycleTemplate $template): array
    {
        $template->loadMissing('days.recipe', 'days.fsItem');

        return [
            'id' => $template->uuid,
            'name' => $template->name,
            'description' => $template->description,
            'cycle_days' => $template->cycle_days,
            'days' => $template->days->map(fn ($d) => [
                'id' => $d->id,
                'day_of_week' => $d->day_of_week,
                'meal_type' => $d->meal_type,
                'recipe_id' => $d->recipe_id,
                'fs_item_id' => $d->fs_item_id,
                'quantity' => $d->quantity,
                'recipe' => $d->recipe ? ['id' => $d->recipe->uuid, 'name' => $d->recipe->name] : null,
                'fs_item' => $d->fsItem ? ['id' => $d->fsItem->uuid, 'name' => $d->fsItem->name] : null,
            ])->values(),
            'updated_at' => $template->updated_at,
        ];
    }

    private function daySignature(MenuCycleTemplate $template): array
    {
        return $template->days()->orderBy('day_of_week')->orderBy('meal_type')
            ->get(['day_of_week', 'meal_type', 'recipe_id', 'fs_item_id', 'quantity'])
            ->map->toArray()->values()->all();
    }
}
