<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
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
use App\Services\Audit\AuditLogger;
use App\Services\Audit\AuditSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AuditSanitizer $sanitizer,
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
            }));
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
            }));
    }

    public function report(Request $request, Report $report): JsonResponse
    {
        Gate::authorize('viewTrail', [AuditActivity::class, $report]);

        return $this->directHistory($request, $report);
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
            }));

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
            }));

        $this->recordTrailAccess($ncpRecord, AuditDomain::Ncp);

        return $response;
    }

    private function directHistory(Request $request, Model $subject): JsonResponse
    {
        return $this->history($request, fn (Builder $query): Builder => $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey()));
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
    private function history(Request $request, callable $scope): JsonResponse
    {
        $validated = $request->validate(['before_id' => ['nullable', 'integer', 'min:1']]);
        $query = AuditActivity::query()
            ->auditOnly()
            ->where(fn (Builder $root): Builder => $scope($root))
            ->when($validated['before_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('id', '<', $id))
            ->orderByDesc('id')
            ->limit(101);
        $activities = $query->get();
        $hasMore = $activities->count() > 100;
        $items = $activities->take(100)->map(fn (AuditActivity $activity): array => $this->present($activity))->values();

        return response()->json([
            'data' => $items,
            'meta' => ['next_before_id' => $hasMore ? $items->last()['id'] : null, 'has_more' => $hasMore],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(AuditActivity $activity): array
    {
        $subjectClass = $activity->subject_type;
        $allowedFields = is_string($subjectClass)
            && class_exists($subjectClass)
            && in_array(AuditsChanges::class, class_uses_recursive($subjectClass), true)
                ? (new $subjectClass)->getActivitylogOptions()->logAttributes
                : [];
        $fields = collect($activity->properties['details']['changed_fields'] ?? [])
            ->merge($activity->properties['details']['fields'] ?? [])
            ->merge(array_keys((array) ($activity->properties['attributes'] ?? [])))
            ->merge(array_keys((array) ($activity->properties['old'] ?? [])))
            ->intersect($allowedFields)
            ->filter(fn (mixed $field): bool => is_string($field))
            ->unique()->sort()->values();
        $hiddenValues = $fields->mapWithKeys(fn (string $field): array => [$field => null])->all();
        $action = AuditAction::tryFrom((string) $activity->event);

        return [
            'id' => $activity->id,
            'event' => $action?->value ?? 'clinical_activity',
            'description' => $this->safeDescription($activity),
            'subject_id' => $activity->subject_id,
            'causer' => $this->safeActor($activity),
            'changes' => ['old' => $hiddenValues, 'new' => $hiddenValues],
            'created_at' => $activity->created_at,
        ];
    }

    private function safeActor(AuditActivity $activity): string
    {
        $actor = $activity->properties['actor'] ?? null;
        if (! is_array($actor)
            || ($actor['kind'] ?? null) !== 'user'
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', (string) ($actor['public_id'] ?? '')) !== 1
            || ! in_array($actor['role'] ?? null, ['RND', 'FSS', 'Admin'], true)
        ) {
            return 'system';
        }

        return $this->sanitizer->text(is_string($actor['name'] ?? null) ? $actor['name'] : null) ?? 'user';
    }

    private function safeDescription(AuditActivity $activity): string
    {
        $description = trim((string) $activity->description);
        if (preg_match('/^(?:Created|Updated|Deleted) (?:patient|NCP record|assessment|diagnosis|intervention|meal plan|monitoring|screening document)$/iD', $description) === 1) {
            return $description;
        }

        return AuditAction::tryFrom((string) $activity->event)?->label() ?? 'Clinical activity';
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
