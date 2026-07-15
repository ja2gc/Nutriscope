<?php

namespace App\Services\Audit;

use App\Enums\AuditDomain;
use App\Models\Assessment;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\MealPlan;
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
use App\Models\ShoppingListItem;
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

    /** @var array<int, int|null> */
    private array $patientIdsByNcp = [];

    /** @var array<int, ShoppingList|null> */
    private array $shoppingListsById = [];

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
            $subject instanceof ShoppingList => $subject,
            $subject instanceof ShoppingListItem => $this->shoppingListForItem($subject),
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
        return app(AuditEventPolicy::class)->domain($subject, AuditDomain::System);
    }

    /** @return array{root_patient_id: int|null, ncp_record_id: int|null} */
    public function clinicalIdentifiers(Model $subject): array
    {
        $ncpId = match (true) {
            $subject instanceof NcpRecord => $this->positiveInt($subject->getKey()),
            $subject instanceof Assessment,
            $subject instanceof Diagnosis,
            $subject instanceof Intervention,
            $subject instanceof Monitoring => $this->positiveInt($subject->getAttribute('ncp_record_id')),
            $subject instanceof ScreeningDocument => $this->positiveInt($subject->getAttribute('ncp_record_id')),
            $subject instanceof MealPlan => $this->ncpIdForMealPlan($subject),
            $subject instanceof Report => $this->positiveInt($subject->getAttribute('audit_ncp_record_id')),
            default => null,
        };

        $patientId = match (true) {
            $subject instanceof Patient => $this->positiveInt($subject->getKey()),
            $subject instanceof NcpRecord,
            $subject instanceof ScreeningDocument,
            $subject instanceof MealPlan => $this->positiveInt($subject->getAttribute('patient_id')),
            $subject instanceof Report => $this->positiveInt($subject->getAttribute('audit_patient_id')),
            default => null,
        };

        if ($patientId === null && $ncpId !== null) {
            if (! array_key_exists($ncpId, $this->patientIdsByNcp)) {
                $this->patientIdsByNcp[$ncpId] = NcpRecord::query()->whereKey($ncpId)->value('patient_id');
            }

            $patientId = $this->positiveInt($this->patientIdsByNcp[$ncpId]);
        }

        return ['root_patient_id' => $patientId, 'ncp_record_id' => $ncpId];
    }

    public function clinicalOwnerId(Model $subject): ?int
    {
        if ($subject instanceof Report) {
            return $this->positiveInt($subject->getAttribute('audit_owner_id'));
        }

        if ($subject instanceof NcpRecord) {
            return $this->positiveInt($subject->getAttribute('rnd_user_id'));
        }

        $identifiers = $this->clinicalIdentifiers($subject);
        if ($identifiers['ncp_record_id'] === null) {
            return null;
        }

        return $this->positiveInt(NcpRecord::query()
            ->whereKey($identifiers['ncp_record_id'])
            ->value('rnd_user_id'));
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

    private function ncpIdForMealPlan(MealPlan $mealPlan): ?int
    {
        $context = $this->ncpForMealPlan($mealPlan);

        return $context instanceof NcpRecord ? $this->positiveInt($context->getKey()) : null;
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

    private function shoppingListForItem(ShoppingListItem $item): ?Model
    {
        $listId = $item->getAttribute('shopping_list_id');
        if (! $this->isPositiveKey($listId)) {
            return null;
        }

        $listId = (int) $listId;
        if (! array_key_exists($listId, $this->shoppingListsById)) {
            $this->shoppingListsById[$listId] = ShoppingList::query()->whereKey($listId)->first(['id', 'uuid']);
        }

        return $this->shoppingListsById[$listId];
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
            || $model instanceof Report
            || $model instanceof ShoppingList;
    }

    private function isPositiveKey(mixed $id): bool
    {
        return (is_int($id) && $id > 0)
            || (is_string($id) && preg_match('/^[1-9][0-9]*$/D', $id) === 1);
    }

    private function positiveInt(mixed $id): ?int
    {
        return $this->isPositiveKey($id) ? (int) $id : null;
    }
}
