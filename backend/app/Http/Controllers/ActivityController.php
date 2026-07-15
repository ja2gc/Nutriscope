<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Http\Resources\AuditEventResource;
use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\Concerns\AuditsChanges;
use App\Models\MealPrepLog;
use App\Models\MenuCycleDay;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ProgramProjectActivity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemCorrection;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ActivityController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AuditEventPresenter $presenter,
    ) {}

    public function purchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->history($request, fn (Builder $query): Builder => $query
            ->where(function (Builder $root) use ($purchaseOrder): void {
                $root->where(function (Builder $context) use ($purchaseOrder): void {
                    $context->where('context_type', $purchaseOrder->getMorphClass())
                        ->where('context_id', $purchaseOrder->getKey());
                })->orWhere(function (Builder $direct) use ($purchaseOrder): void {
                    $direct->where('subject_type', $purchaseOrder->getMorphClass())
                        ->where('subject_id', $purchaseOrder->getKey());
                })->orWhere(function (Builder $ledger) use ($purchaseOrder): void {
                    $ledger->where('subject_type', (new BudgetLedger)->getMorphClass())
                        ->whereIn('subject_id', BudgetLedger::query()->select('id')
                            ->where('purchase_order_id', $purchaseOrder->getKey()));
                })->orWhere(function (Builder $attachment) use ($purchaseOrder): void {
                    $attachment->where('subject_type', (new PurchaseOrderAttachment)->getMorphClass())
                        ->whereIn('subject_id', PurchaseOrderAttachment::query()->select('id')
                            ->where('purchase_order_id', $purchaseOrder->getKey()));
                })->orWhere(function (Builder $vendorGroup) use ($purchaseOrder): void {
                    $vendorGroup->where('subject_type', (new PurchaseOrderVendorGroup)->getMorphClass())
                        ->whereIn('subject_id', PurchaseOrderVendorGroup::query()->select('id')
                            ->where('purchase_order_id', $purchaseOrder->getKey()));
                })->orWhere(function (Builder $item) use ($purchaseOrder): void {
                    $item->where('subject_type', (new PurchaseOrderItem)->getMorphClass())
                        ->whereIn('subject_id', PurchaseOrderItem::query()->select('id')
                            ->where('purchase_order_id', $purchaseOrder->getKey()));
                })->orWhere(function (Builder $correction) use ($purchaseOrder): void {
                    $correction->where('subject_type', (new PurchaseOrderItemCorrection)->getMorphClass())
                        ->whereIn('subject_id', PurchaseOrderItemCorrection::query()->select('id')
                            ->whereIn('purchase_order_item_id', PurchaseOrderItem::query()->select('id')
                                ->where('purchase_order_id', $purchaseOrder->getKey())));
                })->orWhere(function (Builder $ppa) use ($purchaseOrder): void {
                    $ppa->where('subject_type', (new ProgramProjectActivity)->getMorphClass())
                        ->whereIn('subject_id', ProgramProjectActivity::query()->select('id')
                            ->where('purchase_order_id', $purchaseOrder->getKey()));
                })->orWhere(function (Builder $mealService) use ($purchaseOrder): void {
                    $mealService->where('subject_type', (new MealPrepLog)->getMorphClass())
                        ->whereIn('subject_id', $this->mealPrepLogsForPurchaseOrder($purchaseOrder));
                });
            }), AuditCategory::Operations, AuditDomain::Procurement, $purchaseOrder);
    }

    public function budget(Request $request, Budget $budget): JsonResponse
    {
        return $this->history($request, fn (Builder $query): Builder => $query
            ->where(function (Builder $root) use ($budget): void {
                $root->where(function (Builder $context) use ($budget): void {
                    $context->where('context_type', $budget->getMorphClass())
                        ->where('context_id', $budget->getKey());
                })->orWhere(function (Builder $direct) use ($budget): void {
                    $direct->where('subject_type', $budget->getMorphClass())
                        ->where('subject_id', $budget->getKey());
                })->orWhere(function (Builder $ledger) use ($budget): void {
                    $ledger->where('subject_type', (new BudgetLedger)->getMorphClass())
                        ->whereIn('subject_id', BudgetLedger::query()->select('id')
                            ->where('fiscal_year', $budget->fiscal_year));
                });
            }), AuditCategory::Operations, AuditDomain::Budget, $budget);
    }

    public function report(Request $request, Report $report): JsonResponse
    {
        Gate::authorize('viewTrail', [AuditActivity::class, $report]);

        return $this->directHistory($request, $report, AuditDomain::Reports);
    }

    public function patient(Request $request, Patient $patient): JsonResponse
    {
        Gate::authorize('viewTrail', [AuditActivity::class, $patient]);

        $response = $this->history($request, fn (Builder $query): Builder => $query
            ->where(function (Builder $root) use ($patient): void {
                $root->where(function (Builder $direct) use ($patient): void {
                    $direct->where('subject_type', $patient->getMorphClass())
                        ->where('subject_id', $patient->getKey());
                })->orWhere('root_patient_id', $patient->getKey());
            })->orWhere(function (Builder $legacy) use ($patient): void {
                $legacy->where('subject_type', NcpRecord::class)
                    ->whereIn('subject_id', NcpRecord::query()->select('id')->where('patient_id', $patient->id));
            }), AuditCategory::Clinical, AuditDomain::Patients, $patient);

        $this->recordTrailAccess($patient, AuditDomain::Patients);

        return $response;
    }

    public function ncpRecord(Request $request, NcpRecord $ncpRecord): JsonResponse
    {
        Gate::authorize('viewTrail', [AuditActivity::class, $ncpRecord]);

        $response = $this->history($request, fn (Builder $query): Builder => $query
            ->where(function (Builder $root) use ($ncpRecord): void {
                $root->where(function (Builder $context) use ($ncpRecord): void {
                    $context->where('context_type', $ncpRecord->getMorphClass())
                        ->where('context_id', $ncpRecord->getKey());
                })->orWhere('ncp_record_id', $ncpRecord->getKey())
                    ->orWhere(function (Builder $direct) use ($ncpRecord): void {
                        $direct->where('subject_type', $ncpRecord->getMorphClass())
                            ->where('subject_id', $ncpRecord->getKey());
                    });
            }), AuditCategory::Clinical, AuditDomain::Ncp, $ncpRecord);

        $this->recordTrailAccess($ncpRecord, AuditDomain::Ncp);

        return $response;
    }

    private function directHistory(Request $request, Model $subject, AuditDomain $domain): JsonResponse
    {
        return $this->history($request, fn (Builder $query): Builder => $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey()), AuditCategory::Operations, $domain, $subject);
    }

    private function mealPrepLogsForPurchaseOrder(PurchaseOrder $purchaseOrder): Builder
    {
        $logTable = (new MealPrepLog)->getTable();
        $dayTable = (new MenuCycleDay)->getTable();

        return MealPrepLog::query()
            ->select($logTable.'.id')
            ->where(function (Builder $root) use ($purchaseOrder, $logTable, $dayTable): void {
                $root->where($logTable.'.purchase_order_id', $purchaseOrder->getKey())
                    ->orWhere(function (Builder $legacy) use ($purchaseOrder, $logTable, $dayTable): void {
                        $legacy->whereNull($logTable.'.purchase_order_id')
                            ->whereExists(MenuCycleDay::query()
                                ->selectRaw('1')
                                ->whereColumn($dayTable.'.menu_cycle_id', $logTable.'.menu_cycle_id')
                                ->where($dayTable.'.snapshot_purchase_order_id', $purchaseOrder->getKey())
                                ->whereRaw("{$dayTable}.day_of_week = DAYNAME({$logTable}.service_date)"));
                    });
            });
    }

    /** @param callable(Builder): Builder $scope */
    private function history(
        Request $request,
        callable $scope,
        AuditCategory $category,
        AuditDomain $domain,
        ?Model $currentRecord = null,
    ): JsonResponse {
        $validated = $request->validate(['before_id' => ['nullable', 'uuid']]);
        $query = AuditActivity::query()
            ->auditOnly()
            ->where(fn (Builder $root): Builder => $scope($root))
            ->where(function (Builder $query): void {
                $query->whereNull('causer_type')->orWhere('causer_type', (new User)->getMorphClass());
            })
            ->with(['causer' => function (MorphTo $relation): void {
                $relation->constrain([
                    User::class => fn (Builder $query): Builder => $query
                        ->withTrashed()
                        ->select('id', 'uuid', 'name', 'first_name', 'last_name', 'role'),
                ]);
            }, 'revision:id,activity_id,public_id,action']);
        if (isset($validated['before_id'])) {
            $boundaryId = (clone $query)->where('public_id', $validated['before_id'])->value('id');
            if (! is_int($boundaryId) && (! is_string($boundaryId) || ctype_digit($boundaryId) === false)) {
                throw ValidationException::withMessages(['before_id' => 'The selected activity cursor is invalid.']);
            }
            $query->where('id', '<', (int) $boundaryId);
        }
        $query
            ->orderByDesc('id')
            ->limit(101);
        $activities = $query->get();
        $hasMore = $activities->count() > 100;
        $pageActivities = $activities->take(100);
        $items = $pageActivities
            ->map(function (AuditActivity $activity) use ($request, $category, $domain, $currentRecord): array {
                $this->applyContextTaxonomy($activity, $category, $domain);
                if ($category === AuditCategory::Clinical) {
                    $this->sanitizeClinicalFields($activity);
                }

                return (new AuditEventResource($this->presenter->present(
                    $activity,
                    $request->user(),
                    $currentRecord,
                )))->resolve($request);
            })
            ->values();

        return response()->json([
            'data' => $items,
            'meta' => ['next_before_id' => $hasMore ? $pageActivities->last()?->public_id : null, 'has_more' => $hasMore],
        ]);
    }

    private function applyContextTaxonomy(AuditActivity $activity, AuditCategory $fallbackCategory, AuditDomain $fallbackDomain): void
    {
        $storedCategory = AuditCategory::tryFrom((string) ($activity->getRawOriginal('category') ?? ''));
        $storedDomain = AuditDomain::tryFrom((string) ($activity->getRawOriginal('domain') ?? ''));
        $storedModule = AuditModule::tryFrom((string) ($activity->getRawOriginal('module') ?? ''));
        $activity->setAttribute(
            'category',
            $fallbackCategory === AuditCategory::Clinical ? AuditCategory::Clinical : ($storedCategory ?? $fallbackCategory),
        );
        $activity->setAttribute('domain', $storedDomain ?? $fallbackDomain);
        $activity->setAttribute('module', $storedModule ?? match (true) {
            $fallbackCategory === AuditCategory::Clinical => AuditModule::NutritionCare,
            $fallbackDomain === AuditDomain::Reports => AuditModule::Reports,
            in_array($fallbackDomain, [AuditDomain::Budget, AuditDomain::Procurement, AuditDomain::FoodService], true) => AuditModule::FoodServiceOperations,
            $fallbackDomain === AuditDomain::NutritionLibrary => AuditModule::NutritionCare,
            default => AuditModule::SecurityAdministration,
        });
    }

    private function sanitizeClinicalFields(AuditActivity $activity): void
    {
        $subjectClass = $activity->subject_type;
        $allowedFields = is_string($subjectClass)
            && class_exists($subjectClass)
            && in_array(AuditsChanges::class, class_uses_recursive($subjectClass), true)
                ? (new $subjectClass)->getActivitylogOptions()->logAttributes
                : [];
        $properties = $activity->properties?->all() ?? [];
        $details = is_array($properties['details'] ?? null) ? $properties['details'] : [];
        $hasStoredFieldList = is_array($details['changed_fields'] ?? null) || is_array($details['fields'] ?? null);
        $fields = ($hasStoredFieldList
            ? collect($details['changed_fields'] ?? [])->merge($details['fields'] ?? [])
            : collect(array_keys(is_array($properties['attributes'] ?? null) ? $properties['attributes'] : []))
                ->merge(array_keys(is_array($properties['old'] ?? null) ? $properties['old'] : [])))
            ->intersect($allowedFields)
            ->filter(fn (mixed $field): bool => is_string($field))
            ->unique()->sort()->values()->all();

        unset($details['fields']);
        $details['changed_fields'] = $fields;
        $properties['details'] = $details;
        $activity->setAttribute('properties', $properties);
    }

    private function recordTrailAccess(Model $subject, AuditDomain $domain): void
    {
        $this->auditLogger->record(
            AuditAction::AuditLogViewed,
            AuditCategory::Clinical,
            $domain,
            subject: $subject,
            details: ['status' => 200],
        );
    }
}
