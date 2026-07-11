<?php

namespace App\Services\Audit;

use App\Enums\AuditDomain;
use App\Models\Assessment;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\Diagnosis;
use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\Intervention;
use App\Models\Inventory;
use App\Models\MealPlan;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ProgramProjectActivity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemCorrection;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\Report;
use App\Models\ScreeningDocument;
use App\Models\ShoppingList;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AuditContextResolver
{
    /** @var array<int, int|null> */
    private array $budgetIdsByYear = [];

    /** @var array<int, int|null> */
    private array $purchaseOrderIdsByItem = [];

    /** @var array<int, int|null> */
    private array $ncpIdsByIntervention = [];

    public function resolve(?Model $subject, ?Model $context = null): ?Model
    {
        if ($context !== null) {
            if (! $this->isPersisted($context)) {
                throw new InvalidArgumentException('The explicit audit context must be persisted.');
            }

            if ($this->isSupportedRoot($context)) {
                return $context;
            }

            $mapped = $this->resolveSubject($context);
            if ($mapped !== null && $this->isSupportedRoot($mapped)) {
                return $mapped;
            }

            throw new InvalidArgumentException('The explicit audit context must map to a supported audit root.');
        }

        if (! $this->isPersisted($subject)) {
            return null;
        }

        return $this->resolveSubject($subject);
    }

    private function resolveSubject(Model $subject): ?Model
    {
        return match (true) {
            $subject instanceof Patient,
            $subject instanceof NcpRecord,
            $subject instanceof PurchaseOrder,
            $subject instanceof Budget,
            $subject instanceof Report => $subject,
            $subject instanceof Assessment,
            $subject instanceof Diagnosis,
            $subject instanceof Intervention,
            $subject instanceof Monitoring => $this->reference(NcpRecord::class, $subject->getAttribute('ncp_record_id')),
            $subject instanceof ScreeningDocument && $subject->getAttribute('ncp_record_id') !== null => $this->reference(NcpRecord::class, $subject->getAttribute('ncp_record_id')),
            $subject instanceof ScreeningDocument => $this->reference(Patient::class, $subject->getAttribute('patient_id')),
            $subject instanceof MealPlan => $this->ncpForMealPlan($subject),
            $subject instanceof PurchaseOrderItem,
            $subject instanceof PurchaseOrderAttachment,
            $subject instanceof PurchaseOrderVendorGroup,
            $subject instanceof ProgramProjectActivity => $this->reference(PurchaseOrder::class, $subject->getAttribute('purchase_order_id')),
            $subject instanceof PurchaseOrderItemCorrection => $this->purchaseOrderForCorrection($subject),
            $subject instanceof BudgetLedger => $this->budgetForLedger($subject),
            default => null,
        };
    }

    public function domain(Model $subject): AuditDomain
    {
        return match (true) {
            $subject instanceof Patient => AuditDomain::Patients,
            $subject instanceof NcpRecord,
            $subject instanceof Assessment,
            $subject instanceof Diagnosis,
            $subject instanceof Intervention,
            $subject instanceof Monitoring,
            $subject instanceof ScreeningDocument,
            $subject instanceof MealPlan => AuditDomain::Ncp,
            $subject instanceof PurchaseOrder,
            $subject instanceof PurchaseOrderItem,
            $subject instanceof PurchaseOrderAttachment,
            $subject instanceof PurchaseOrderVendorGroup,
            $subject instanceof PurchaseOrderItemCorrection,
            $subject instanceof ProgramProjectActivity,
            $subject instanceof ShoppingList => AuditDomain::Procurement,
            $subject instanceof Budget,
            $subject instanceof BudgetLedger => AuditDomain::Budget,
            $subject instanceof Report => AuditDomain::Reports,
            $subject instanceof FoodServiceRecipe,
            $subject instanceof FsItem,
            $subject instanceof Inventory,
            $subject instanceof MealPrepLog,
            $subject instanceof MenuCycle => AuditDomain::FoodService,
            default => AuditDomain::System,
        };
    }

    private function purchaseOrderForCorrection(PurchaseOrderItemCorrection $correction): ?Model
    {
        $itemId = $correction->getAttribute('purchase_order_item_id');
        if (! $this->isPositiveKey($itemId)) {
            return null;
        }

        $itemId = (int) $itemId;
        if (! array_key_exists($itemId, $this->purchaseOrderIdsByItem)) {
            $this->purchaseOrderIdsByItem[$itemId] = PurchaseOrderItem::query()
                ->whereKey($itemId)
                ->value('purchase_order_id');
        }

        return $this->reference(PurchaseOrder::class, $this->purchaseOrderIdsByItem[$itemId]);
    }

    private function ncpForMealPlan(MealPlan $mealPlan): ?Model
    {
        $interventionId = $mealPlan->getAttribute('intervention_id');
        if ($this->isPositiveKey($interventionId)) {
            $interventionId = (int) $interventionId;

            if (! array_key_exists($interventionId, $this->ncpIdsByIntervention)) {
                $this->ncpIdsByIntervention[$interventionId] = Intervention::query()
                    ->whereKey($interventionId)
                    ->value('ncp_record_id');
            }

            $ncp = $this->reference(NcpRecord::class, $this->ncpIdsByIntervention[$interventionId]);
            if ($ncp !== null) {
                return $ncp;
            }
        }

        return $this->reference(Patient::class, $mealPlan->getAttribute('patient_id'));
    }

    private function budgetForLedger(BudgetLedger $ledger): ?Model
    {
        $year = $ledger->getAttribute('fiscal_year');
        if (! is_numeric($year)) {
            return null;
        }

        $year = (int) $year;
        if (! array_key_exists($year, $this->budgetIdsByYear)) {
            $this->budgetIdsByYear[$year] = Budget::query()
                ->where('fiscal_year', $year)
                ->value('id');
        }

        return $this->reference(Budget::class, $this->budgetIdsByYear[$year]);
    }

    /** @param class-string<Model> $modelClass */
    private function reference(string $modelClass, mixed $id): ?Model
    {
        if (! $this->isPositiveKey($id)) {
            return null;
        }

        $model = new $modelClass;
        $model->setAttribute($model->getKeyName(), $id);
        $model->exists = true;

        return $model;
    }

    private function isPersisted(?Model $model): bool
    {
        return $model !== null && $model->exists && $this->isPositiveKey($model->getKey());
    }

    private function isSupportedRoot(Model $model): bool
    {
        return $model instanceof Patient
            || $model instanceof NcpRecord
            || $model instanceof PurchaseOrder
            || $model instanceof Budget
            || $model instanceof Report;
    }

    private function isPositiveKey(mixed $id): bool
    {
        return (is_int($id) && $id > 0)
            || (is_string($id) && preg_match('/^[1-9][0-9]*$/D', $id) === 1);
    }
}
