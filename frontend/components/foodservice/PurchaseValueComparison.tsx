import type { POItem } from "@/services/procurementService";

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
    <div className="space-y-1 text-xs text-warm-500">
      <div><span className="font-bold text-warm-700">Planned purchase:</span> {plannedQty.toFixed(3)} {unit} at ₱{plannedPrice.toFixed(2)}</div>
      <div><span className="font-bold text-warm-700">Actual purchased:</span> {currentQty.toFixed(3)} {unit} at ₱{currentPrice.toFixed(2)}</div>
      <span className={`inline-flex rounded-full px-2 py-0.5 font-bold ${item.actual_values_confirmed ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700"}`}>
        {item.actual_values_confirmed ? "Reviewed" : "Not reviewed"}
      </span>
      <details className="pt-1">
        <summary className="cursor-pointer font-bold text-emerald-700">Calculation details</summary>
        <div className="mt-1 space-y-0.5 rounded-lg bg-warm-50 p-2">
          <div>Calculated need: {number(item.qty).toFixed(3)} {item.unit} at ₱{number(item.unit_price).toFixed(2)}</div>
          <div>Planned purchase: {plannedQty.toFixed(3)} {unit} at ₱{plannedPrice.toFixed(2)}</div>
          <div>Actual purchased: {currentQty.toFixed(3)} {unit} at ₱{currentPrice.toFixed(2)}</div>
          <div>Quantity difference: {(currentQty - plannedQty).toFixed(3)} {unit}</div>
          <div>Cost difference: ₱{(currentQty * currentPrice - plannedQty * plannedPrice).toFixed(2)}</div>
        </div>
      </details>
    </div>
  );
}
