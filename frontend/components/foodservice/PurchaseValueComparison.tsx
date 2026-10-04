import type { POItem } from "@/services/procurementService";
import { formatFoodServiceNumber } from "@/lib/foodServiceFormat";

const number = (value: string | number | null | undefined) => Number(value ?? 0);

export function PurchaseValueComparison({ item, actualQty, actualPrice }: {
  item: POItem;
  actualQty: string;
  actualPrice: string;
}) {
  const unit = item.purchase_unit ?? item.unit;
  const plannedQty = number(item.purchase_qty ?? item.qty);
  const plannedPrice = number(item.purchase_price ?? item.unit_price);
  const currentQty = number(actualQty);
  const currentPrice = number(actualPrice);

  return (
    <div className="mt-1 text-xs text-warm-500">
      <details className="pt-1">
        <summary className="min-h-11 cursor-pointer py-2 font-bold text-emerald-700">Calculation details</summary>
        <div className="mt-1 space-y-0.5 rounded-lg bg-warm-50 p-2">
          <div>Planned purchase: {formatFoodServiceNumber(plannedQty)} {unit} at ₱{formatFoodServiceNumber(plannedPrice)}</div>
          <div>Actual purchased: {formatFoodServiceNumber(currentQty)} {unit} at ₱{formatFoodServiceNumber(currentPrice)}</div>
        </div>
      </details>
    </div>
  );
}
