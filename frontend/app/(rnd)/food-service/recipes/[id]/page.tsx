"use client";
import { SectionLoading } from "@/components/ui/SectionLoading";

import React, { use, useEffect, useState } from "react";
import Link from "next/link";
import { useEditorChanges } from "@/hooks/useUnsavedChanges";
import { useRouter } from "next/navigation";
import { CookingPot, ArrowLeft, Search, Plus, X } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { numericStepBase } from "@/components/ui/NumericInput";
import { quantityInputValue } from "@/lib/foodServiceFormat";
import { InfoHint } from "@/components/ui/InfoHint";
import ButtonFilterGroup from "@/components/ui/ButtonFilterGroup";
import type { PaginationMeta } from "@/components/ui/Pagination";
import { MenuItemPicker } from "@/components/ncp/MenuItemPicker";
import { InventoryCorrectionEditor } from "@/components/foodservice/InventoryCorrectionEditor";
import { compatibleRecipeUnits, foodServiceIngredientCost } from "@/lib/foodServiceUnits";
import { listCatalog, type CatalogItem } from "@/services/fsCatalogService";

// ─── Types ────────────────────────────────────────────────────────────────────

type RecipeCatalogItem = Pick<CatalogItem, "id" | "name" | "unit_cost" | "base_unit" | "include_in_generated_lists">;

interface IngredientRow {
  key: number;
  catalogItem: RecipeCatalogItem | null;
  quantity: string;
  unit: string;
  adjust_to_taste: boolean;
}

interface FSSRecipe {
  id: string;
  name: string;
  category: string | null;
  prep_notes: string | null;
  servings: number;
  cost: number;
  ingredients: Array<{
    id: number;
    fs_item_id: string;
    quantity: number | null;
    unit: string;
    adjust_to_taste: boolean;
    fs_item: RecipeCatalogItem | null;
  }>;
}

// ─── API helpers ──────────────────────────────────────────────────────────────

async function loadRecipe(id: string): Promise<FSSRecipe> {
  const res = await fetch(`/api/fss/food-service-recipes/${id}`);
  if (!res.ok) throw new Error("Recipe not found.");
  return (await res.json()).data;
}

const FSS_CATEGORIES = [
  "meal", "snack", "beverage",
];

const inputCls = "w-full px-3 py-2 text-base border border-warm-200 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all";

function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-1">{children}</label>;
}

function Required() {
  return <span className="text-red-500 ml-0.5">*</span>;
}

// ─── Cost calculation ─────────────────────────────────────────────────────────

function calcCost(rows: IngredientRow[]): number {
  return rows.reduce((sum, row) => sum + costPerIngredient(row), 0);
}

function costPerIngredient(row: IngredientRow): number {
  if (!row.catalogItem || !row.quantity) return 0;
  return foodServiceIngredientCost(row.catalogItem, parseFloat(row.quantity), row.unit);
}

let rowKey = 3000;

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function EditFSSRecipePage({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: Promise<{ inventoryCorrection?: string }> }) {
  const { id } = use(params);
  const { inventoryCorrection } = use(searchParams);
  if (inventoryCorrection) return <InventoryCorrectionEditor kind="recipe" id={id} token={inventoryCorrection} />;
  return <StandardEditFSSRecipePage id={id} />;
}

function StandardEditFSSRecipePage({ id }: { id: string }) {
  const router = useRouter();

  const [recipe, setRecipe]       = useState<FSSRecipe | null>(null);
  const [loading, setLoading]     = useState(true);
  const [saving, setSaving]       = useState(false);
  const [error, setError]         = useState<string | null>(null);

  const [name, setName]           = useState("");
  const [category, setCategory]   = useState("");
  const [servings, setServings]   = useState("1");
  const [prepNotes, setPrepNotes] = useState("");
  const [previewServings, setPreviewServings] = useState(""); // blank = baseline; read-only scale preview
  const [ingredients, setIngredients] = useState<IngredientRow[]>([]);
  const [pickerRowKey, setPickerRowKey] = useState<number | null>(null);
  const [pickerQuery, setPickerQuery] = useState("");
  const [pickerResults, setPickerResults] = useState<CatalogItem[]>([]);
  const [pickerMeta, setPickerMeta] = useState<PaginationMeta | null>(null);
  const [pickerPage, setPickerPage] = useState(1);
  const [groceryFilter, setGroceryFilter] = useState<"" | "non_auto" | "auto">("");
  const [pickerLoading, setPickerLoading] = useState(false);
  const [pickerError, setPickerError] = useState<string | null>(null);

  useEffect(() => {
    loadRecipe(id)
      .then((r) => {
        setRecipe(r);
        setName(r.name);
        setCategory(r.category ?? "");
        setServings(String(r.servings ?? 1));
        setPrepNotes(r.prep_notes ?? "");
        setIngredients(
          r.ingredients?.length
            ? r.ingredients.map((ing) => ({
                key: rowKey++,
                catalogItem: ing.fs_item
                  ? { id: ing.fs_item.id, name: ing.fs_item.name, unit_cost: ing.fs_item.unit_cost, base_unit: ing.fs_item.base_unit, include_in_generated_lists: ing.fs_item.include_in_generated_lists }
                  : null,
                quantity: ing.quantity === null ? "" : String(ing.quantity),
                unit: ing.unit,
                adjust_to_taste: ing.adjust_to_taste ?? false,
              }))
            : [{ key: rowKey++, catalogItem: null, quantity: "", unit: "g", adjust_to_taste: false }]
        );
      })
      .catch((e: unknown) => setError(e instanceof Error ? e.message : "Failed to load."))
      .finally(() => setLoading(false));
  }, [id]);

  useEffect(() => {
    if (pickerRowKey === null) return;
    let cancelled = false;
    setPickerLoading(true);
    setPickerError(null);
    const timer = window.setTimeout(async () => {
      try {
        const result = await listCatalog("ingredient", pickerPage, pickerQuery.trim(), groceryFilter);
        if (!cancelled) { setPickerResults(result.data); setPickerMeta(result.meta); }
      } catch (cause) {
        if (!cancelled) setPickerError(cause instanceof Error ? cause.message : "Ingredient search failed. Try again.");
      } finally {
        if (!cancelled) setPickerLoading(false);
      }
    }, 180);
    return () => { cancelled = true; window.clearTimeout(timer); };
  }, [pickerPage, pickerQuery, pickerRowKey, groceryFilter]);

  function openPicker(key: number) {
    setPickerRowKey(key); setPickerQuery(""); setPickerResults([]); setPickerMeta(null); setPickerPage(1); setGroceryFilter(""); setPickerError(null);
  }

  const updateRow = (idx: number, patch: Partial<IngredientRow>) =>
    setIngredients((prev) => prev.map((r, i) => i === idx ? { ...r, ...patch } : r));

  const selectItem = (key: number, item: CatalogItem) => {
    setIngredients((prev) => prev.map((row) => row.key === key ? { ...row, catalogItem: item, unit: item.base_unit } : row));
    setPickerRowKey(null);
  };

  const totalCost = calcCost(ingredients);
  const servingsNum = parseInt(servings) || 1;
  const previewNum = parseInt(previewServings) || servingsNum;
  const previewFactor = servingsNum > 0 ? previewNum / servingsNum : 1;

  const guard = useEditorChanges(JSON.stringify({ name, category, servings, prepNotes, ingredients: ingredients.map(({ catalogItem, quantity, unit, adjust_to_taste }) => ({ id: catalogItem?.id, quantity, unit, adjust_to_taste })) }), !loading, () => handleSubmit(undefined, false));

  async function handleSubmit(e?: React.FormEvent, navigate = true) {
    e?.preventDefault();
    if (!e && !document.querySelector<HTMLFormElement>("form")?.reportValidity()) return false;
    if (!name.trim()) { setError("Recipe name is required."); return false; }
    if (ingredients.some((r) => r.catalogItem && (!r.unit.trim() || r.unit.trim().toLowerCase() === "to taste"))) { setError("Enter a measured unit for every ingredient."); return false; }
    if (ingredients.some((r) => r.catalogItem && (!r.quantity || Number(r.quantity) <= 0))) { setError("Enter a baseline quantity for every ingredient."); return false; }

    const valid = ingredients
      .filter((r) => r.catalogItem && r.quantity && parseFloat(r.quantity) > 0)
      .map((r) => ({ fs_item_id: r.catalogItem!.id, quantity: parseFloat(r.quantity), unit: r.unit, adjust_to_taste: r.adjust_to_taste }));

    if (valid.length === 0) { setError("Add at least one ingredient."); return false; }

    try {
      setSaving(true);
      setError(null);
      await fetch(`/api/fss/food-service-recipes/${id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: name.trim(),
          category: category || null,
          servings: servingsNum,
          prep_notes: prepNotes || null,
          ingredients: valid,
        }),
      }).then(async (res) => {
        if (!res.ok) {
          const d = await res.json().catch(() => ({}));
          throw new Error(d.message ?? "Failed to save.");
        }
      });
      if (navigate) { guard.allowNavigation(); router.push("/food-service/inventory?tab=menu-items"); }
      return true;
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Failed to save.");
      return false;
    } finally {
      setSaving(false);
    }
  };

  if (loading) return (
    <SectionLoading label="Loading menu item…" skeleton />
  );

  if (!recipe && error) return (
    <div className="p-6 bg-red-50 border border-red-100 rounded-2xl text-sm text-red-700 font-bold">{error}</div>
  );

  return (
    <div className="space-y-6 font-sans max-w-3xl mx-auto">
      {guard.dialog}
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span>/</span>
        <Link href="/food-service/inventory?tab=menu-items" className="hover:text-emerald-700 transition-colors">Menu Items</Link>
        <span>/</span>
        <span className="text-zinc-650 font-bold truncate max-w-40">{recipe?.name}</span>
      </div>

      {/* Page header */}
      <div className="border-b border-warm-200 pb-5 flex items-center gap-4">
        <Link href="/food-service/inventory?tab=menu-items"
          className="p-2 rounded-lg border border-warm-200 hover:bg-warm-50 text-warm-500 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </Link>
        <div>
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
            <CookingPot className="h-5 w-5 text-emerald-600" />
            Edit Food
          </h2>
        </div>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-100 p-4 rounded-xl text-sm text-red-700 font-bold">{error}</div>
      )}

      <form onSubmit={handleSubmit} className="space-y-5">
        {/* Recipe details card */}
        <div className="bg-white border border-warm-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Recipe Details</h3>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="sm:col-span-2">
              <Label>Recipe Name <Required /></Label>
              <input type="text" value={name} onChange={(e) => setName(e.target.value)} className={inputCls} required />
            </div>
            <div>
              <Label>Category <Required /></Label>
              <select value={category} onChange={(e) => setCategory(e.target.value)} className={inputCls} required>
                <option value="">Select...</option>
                {FSS_CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
              </select>
            </div>
            <div>
              <Label>Servings</Label>
              <input type="number" value={servings} onChange={(e) => setServings(e.target.value)} min="1" className={inputCls} />
            </div>
            <div className="sm:col-span-2">
              <Label>Prep Notes</Label>
              <textarea value={prepNotes} onChange={(e) => setPrepNotes(e.target.value)} rows={2} className={`${inputCls} resize-none`} />
            </div>
          </div>
        </div>

        {/* Total cost card */}
        <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm flex items-center gap-5">
          <div className="flex-1">
            <div className="text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-1">Total Cost</div>
            <div className="text-3xl font-extrabold text-emerald-600">
              ₱{totalCost.toFixed(2)}
            </div>
            <div className="text-xs text-warm-400 font-semibold mt-1">
              per recipe ({servingsNum} serving{servingsNum !== 1 ? "s" : ""})
              {servingsNum > 1 && (
                <span className="ml-2 text-warm-500">
                  · ₱{(totalCost / servingsNum).toFixed(2)} per serving
                </span>
              )}
            </div>
          </div>
        </div>

        {/* Serving scaler — read-only preview, does NOT change the saved baseline */}
        <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm space-y-3">
          <div className="flex items-center justify-between gap-4 flex-wrap">
            <div className="flex items-center gap-2">
              <span className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">Preview at</span>
              <input type="number" min="1" value={previewServings} onChange={(e) => setPreviewServings(e.target.value)}
                className="w-20 px-2.5 py-1.5 text-base border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
              <span className="text-sm text-warm-500">servings</span>
            </div>
            <div className="text-right">
              <div className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">Scaled total</div>
              <div className="text-xl font-extrabold text-emerald-600">₱{(totalCost * previewFactor).toFixed(2)}</div>
              <div className="text-xs text-warm-400 font-semibold">
                ×{previewFactor.toFixed(2)} from {servingsNum}-serving baseline · preview only
              </div>
            </div>
          </div>
          {Math.abs(previewFactor - 1) > 1e-9 && (
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-1.5 pt-2 border-t border-warm-100">
              {ingredients.filter((r) => r.catalogItem && r.quantity && parseFloat(r.quantity) > 0).map((r) => (
                <div key={r.key} className="text-xs text-warm-500 truncate">
                  <span className="text-warm-700 font-semibold">{r.catalogItem!.name}:</span>{" "}
                  {(parseFloat(r.quantity) * previewFactor).toFixed(1)} {r.unit}
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Ingredients */}
        <div className="bg-white border border-warm-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <div className="flex items-center justify-between">
            <h3 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wider text-warm-700">
              Ingredients
              <InfoHint label="About recipe ingredients">Every ingredient has a baseline amount and scales with servings. Non-Auto stays out of automatic shopping lists. Mark seasonings to adjust to taste.</InfoHint>
            </h3>
            <button type="button"
              onClick={() => setIngredients((prev) => [...prev, { key: rowKey++, catalogItem: null, quantity: "", unit: "g", adjust_to_taste: false }])}
              className="flex items-center gap-1.5 px-3 py-1.5 border border-warm-300 rounded-lg text-warm-600 hover:bg-warm-50 cursor-pointer transition-colors text-xs font-bold uppercase tracking-wider">
              <Plus className="h-3 w-3" /> Add Row
            </button>
          </div>

          {/* Column headers */}
          <div className="grid grid-cols-[1fr_80px_72px_80px_28px] items-center gap-2 text-xs font-extrabold text-warm-400 uppercase tracking-wider px-1">
            <span>Catalog ingredient</span>
            <span>Qty</span>
            <span className="flex items-center">Unit<InfoHint label="About recipe units">Weight: g, kg, oz. Liquid: mL, L, cup, tbsp, tsp. pc, bundle, pack, tray, unit each match only themselves. Nonconvertible units keep their saved unit.</InfoHint></span>
            <span className="text-right">₱ Cost</span>
            <span />
          </div>

          <div className="space-y-3">
            {ingredients.map((row, idx) => (
              <div key={row.key} className="grid grid-cols-[1fr_80px_72px_80px_28px] gap-2 items-start">
                {/* Ingredient search */}
                <div>
                  <button type="button" aria-label={`Choose ingredient ${idx + 1}`} onClick={() => openPicker(row.key)} className="flex min-h-11 w-full items-center gap-2 rounded-lg border border-warm-300 bg-white px-3 py-2 text-left text-base text-warm-900 hover:bg-warm-50">
                    <Search className="h-3.5 w-3.5 shrink-0 text-warm-400" />
                    <span className="min-w-0 flex-1 truncate">{row.catalogItem?.name ?? "Select ingredient"}</span>
                    {row.catalogItem && <span className="shrink-0 text-xs text-warm-400">₱{row.catalogItem.unit_cost.toFixed(2)}/{row.catalogItem.base_unit}</span>}
                  </button>
                  {row.catalogItem && <label className="mt-2 flex items-center gap-2 text-xs font-semibold text-warm-600"><input type="checkbox" checked={row.adjust_to_taste} onChange={(e) => updateRow(idx, { adjust_to_taste: e.target.checked })} /> Adjust to taste</label>}
                </div>

                {/* Quantity */}
                <input type="number" value={quantityInputValue(row.quantity)} required={Boolean(row.catalogItem)}
                  onChange={(e) => updateRow(idx, { quantity: e.target.value })}
                  min={numericStepBase(quantityInputValue(row.quantity))} step="0.1"
                  className="w-full px-3 py-2 text-base border border-warm-300 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />

                <select aria-label="Recipe unit" value={row.unit} onChange={(e) => updateRow(idx, { unit: e.target.value })}
                  className="w-full px-2 py-2 text-base border border-warm-300 rounded-lg text-warm-900 focus:outline-none cursor-pointer">
                  {compatibleRecipeUnits(row.catalogItem?.base_unit ?? row.unit, row.unit).map((u) => <option key={u} value={u}>{u}</option>)}
                </select>

                {/* Cost contribution */}
                <div className="py-2 text-right text-sm font-bold text-emerald-700">
                  {row.catalogItem && row.quantity
                    ? `₱${costPerIngredient(row).toFixed(2)}`
                    : <span className="text-warm-300">—</span>}
                </div>

                {/* Remove */}
                <button type="button" onClick={() => setIngredients((prev) => prev.filter((_, i) => i !== idx))}
                  disabled={ingredients.length === 1}
                  className="mt-1.5 p-1.5 rounded-lg text-warm-400 hover:text-red-500 hover:bg-red-50 disabled:opacity-30 cursor-pointer transition-colors">
                  <X className="h-3.5 w-3.5" />
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* Actions */}
        <div className="flex gap-3 justify-end pb-4">
          <Link href="/food-service/inventory?tab=menu-items">
            <Button variant="secondary" className="w-auto px-6 py-2.5">Cancel</Button>
          </Link>
          <Button type="submit" variant="primary" loading={saving} className="w-auto px-6 py-2.5">
            Save Changes
          </Button>
        </div>
      </form>
      {pickerRowKey !== null && <MenuItemPicker title="Add Ingredient" itemLabel="Ingredient" query={pickerQuery} onQueryChange={(value) => { setPickerQuery(value); setPickerPage(1); setPickerResults([]); }} filters={<div className="[&_button]:min-h-11"><ButtonFilterGroup label="Grocery" size="sm" value={groceryFilter} onChange={(value) => { setGroceryFilter(value as typeof groceryFilter); setPickerPage(1); setPickerResults([]); setPickerLoading(true); }} options={[{ value: "", label: "All" }, { value: "non_auto", label: "Non-auto grocery" }, { value: "auto", label: "Auto grocery" }]} /></div>} loading={pickerLoading} error={pickerError} items={pickerResults.map((item) => ({ id: item.id, name: item.name, detail: `₱${item.unit_cost.toFixed(2)}/${item.base_unit}` }))} onSelect={(itemId) => { const item = pickerResults.find((result) => result.id === itemId); if (item) selectItem(pickerRowKey, item); }} onClose={() => setPickerRowKey(null)} meta={pickerMeta} page={pickerPage} onPageChange={setPickerPage} />}
    </div>
  );
}
