"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useEditorChanges } from "@/hooks/useUnsavedChanges";
import { numericStepBase } from "@/components/ui/NumericInput";
import { quantityInputValue } from "@/lib/foodServiceFormat";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/Button";
import { InfoHint } from "@/components/ui/InfoHint";
import { getMenuLineRecipe } from "@/services/menuCycleService";
import { correctionKey, correctionUnits, readCorrectionDraft, writeCorrectionDraft, type InventoryCorrectionDraft } from "@/lib/inventoryCorrectionDraft";

interface Props { kind: "recipe" | "slot"; id: string; token: string; cycleId?: string }

export function InventoryCorrectionEditor({ kind, id, token, cycleId }: Props) {
  const router = useRouter();
  const [draft, setDraft] = useState<InventoryCorrectionDraft | null>(null);
  const [values, setValues] = useState<Record<string, { quantity: string; unit: string }>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const backHref = `/food-service/inventory?inventoryCorrection=${encodeURIComponent(token)}`;

  useEffect(() => {
    const saved = readCorrectionDraft(token);
    if (!saved) { setError("This Inventory correction is no longer available. Return to Inventory and try again."); setLoading(false); return; }
    const affected = saved.affected.filter((row) => kind === "slot" ? row.slot_id === id : row.recipe_id === id);
    if (!affected.length) { setError("This item is not in the affected list."); setLoading(false); return; }
    setDraft(saved);
    setValues(Object.fromEntries(affected.map((row) => [correctionKey(row), saved.corrections[correctionKey(row)] ?? { quantity: "", unit: "" }])));
    const validateCurrent = async () => {
      if (kind === "slot") {
        if (!cycleId) throw new Error("Menu Calendar link is incomplete.");
        const slot = await getMenuLineRecipe(cycleId, id);
        if (!slot.editable || slot.locked) throw new Error("This menu item is now locked. Return to Inventory and review the affected list.");
        if (affected.some((row) => slot.ingredients[row.line_index]?.fs_item_id !== saved.editing.id)) {
          throw new Error("This menu item changed. Return to Inventory and review the affected list.");
        }
      } else {
        const response = await fetch(`/api/fss/food-service-recipes/${encodeURIComponent(id)}`);
        if (!response.ok) throw new Error("This Food could not be loaded.");
        const body = await response.json();
        const matches = (body.data?.ingredients ?? []).filter((row: { fs_item?: { id?: string } | null; fs_item_id?: string }) =>
          (row.fs_item?.id ?? row.fs_item_id) === saved.editing.id);
        if (affected.some((row) => !matches[row.line_index])) {
          throw new Error("This Food changed. Return to Inventory and review the affected list.");
        }
      }
    };
    validateCurrent().catch((cause) => setError(cause instanceof Error ? cause.message : "Failed to load item.")).finally(() => setLoading(false));
  }, [kind, id, token, cycleId]);

  const affected = draft?.affected.filter((row) => kind === "slot" ? row.slot_id === id : row.recipe_id === id) ?? [];
  const units = correctionUnits(draft?.form.base_unit ?? "");
  function stage() {
    if (!draft || error) return false;
    if (affected.some((row) => {
      const value = values[correctionKey(row)];
      const quantity = Number(value?.quantity);
      return !value?.quantity || !Number.isFinite(quantity) || quantity < 0.01 || quantity > 100000 ||
        !/^\d+(?:\.\d{1,2})?$/.test(value.quantity) || !units.includes(value.unit);
    })) { setError("Enter a positive quantity and a compatible unit for every ingredient shown."); return false; }
    writeCorrectionDraft(token, { ...draft, corrections: { ...draft.corrections, ...values } });
    return true;
  }

  const guard = useEditorChanges(JSON.stringify(values), !loading, async () => stage());

  return <div className="mx-auto max-w-3xl space-y-5 font-sans">
    {guard.dialog}
    <Link href={backHref} className="text-sm font-semibold text-emerald-700">← Back to affected items</Link>
    <div className="rounded-2xl border border-warm-200 bg-white p-5 space-y-4">
      <div>
        <h1 className="text-xl font-extrabold text-warm-900">Correct {affected[0]?.recipe_name ?? (kind === "slot" ? "menu item" : "Food")}</h1>
        <p className="text-sm text-warm-600">Enter the amount used in a unit that matches Inventory {draft?.form.base_unit ?? ""}. Servings stay the same. Changes are saved together when you save Inventory.</p>
      </div>
      <div className="flex flex-wrap items-center gap-2 text-xs text-warm-600">
        <span className="font-bold">Convertible units:</span>
        <span>Weight</span><InfoHint label="About weight conversions">g, kg and oz convert to each other.</InfoHint>
        <span>Liquid</span><InfoHint label="About liquid conversions">mL, L, cup, tbsp and tsp convert to each other.</InfoHint>
        <span>Pieces and packs</span><InfoHint label="About piece and pack units">pc, bundle, pack, tray and unit each match only themselves.</InfoHint>
      </div>
      {loading ? <p className="text-sm text-warm-500">Loading…</p> : affected.map((row) => {
        const key = correctionKey(row);
        const value = values[key] ?? { quantity: "", unit: "" };
        return <div key={key} className="rounded-xl border border-warm-200 p-4 space-y-2">
          <p className="text-sm font-semibold text-warm-800">{draft?.editing.name} <span className="font-normal text-warm-500">Current: {row.quantity ?? "Not measured"} {row.unit}</span></p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label className="text-xs font-bold text-warm-600">New quantity<input aria-label={`${row.recipe_name} new quantity`} type="number" min={numericStepBase(quantityInputValue(value.quantity))} max="100000" step="0.1" value={quantityInputValue(value.quantity)} onChange={(event) => { setError(""); setValues((all) => ({ ...all, [key]: { ...value, quantity: event.target.value } })); }} className="mt-1 w-full rounded-lg border border-warm-200 px-3 py-2 text-base" /></label>
            <label className="text-xs font-bold text-warm-600">Unit<select aria-label={`${row.recipe_name} new unit`} value={value.unit} onChange={(event) => { setError(""); setValues((all) => ({ ...all, [key]: { ...value, unit: event.target.value } })); }} className="mt-1 w-full rounded-lg border border-warm-200 bg-white px-3 py-2 text-base"><option value="">Select unit…</option>{units.map((unit) => <option key={unit} value={unit}>{unit}</option>)}</select></label>
          </div>
        </div>;
      })}
      {error && <p role="alert" className="text-sm font-semibold text-red-600">{error}</p>}
      <div className="flex justify-end"><Button variant="primary" disabled={loading || Boolean(error)} onClick={() => { if (stage()) { guard.allowNavigation(); router.push(backHref); } }}>Save correction</Button></div>
    </div>
  </div>;
}
