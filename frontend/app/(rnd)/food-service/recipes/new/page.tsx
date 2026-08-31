"use client";

import React, { useCallback, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { CookingPot, ArrowLeft, Search, Plus, X } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { CATALOG_UNIT_OPTIONS } from "@/lib/units";
import { searchCatalog, type CatalogItem } from "@/services/fsCatalogService";

// ─── Types ────────────────────────────────────────────────────────────────────

interface IngredientRow {
  key: number;
  catalogItem: CatalogItem | null;
  search: string;
  results: CatalogItem[];
  showDropdown: boolean;
  quantity: string;
  unit: string;
}

// ─── API helpers ──────────────────────────────────────────────────────────────

const FSS_CATEGORIES = [
  "beverage", "breakfast", "lunch", "snack", "dinner",
];

const inputCls = "w-full px-3 py-2 text-base border border-warm-200 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all";

function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-1">{children}</label>;
}

function Required() {
  return <span className="text-red-500 ml-0.5">*</span>;
}

// ─── Client-side unit conversion (mirrors backend UnitConverter) ───────────────
const MASS_TO_G: Record<string, number> = {
  mg: 0.001, g: 1, gram: 1, grams: 1, kg: 1000, oz: 28.349523125, lb: 453.59237,
};
const VOLUME_TO_ML: Record<string, number> = {
  ml: 1, milliliter: 1, millilitre: 1, cc: 1, l: 1000, liter: 1000, litre: 1000,
  tsp: 4.92892, teaspoon: 4.92892, tbsp: 14.7868, tablespoon: 14.7868, cup: 236.588,
};
const norm = (u: string) => u.trim().toLowerCase();
const isMass = (u: string) => norm(u) in MASS_TO_G;
const isVolume = (u: string) => norm(u) in VOLUME_TO_ML;
const isKnownUnit = (u: string) => isMass(u) || isVolume(u);

/** Base units contained in one `from` unit, when both share a dimension. null if not convertible. */
function basePerUnit(from: string, to: string): number | null {
  const f = norm(from), t = norm(to);
  if (f === t) return 1;
  if (isMass(f) && isMass(t)) return MASS_TO_G[f] / MASS_TO_G[t];
  if (isVolume(f) && isVolume(t)) return VOLUME_TO_ML[f] / VOLUME_TO_ML[t];
  return null;
}

/** Convertibility of a recipe row against its catalog base unit. */
function rowConversion(row: IngredientRow): { convertible: boolean; warning: string | null; blocked: boolean } {
  if (!row.catalogItem) return { convertible: true, warning: null, blocked: false };
  const base = row.catalogItem.base_unit;
  if (norm(row.unit) === norm(base)) return { convertible: true, warning: null, blocked: false };
  const factor = basePerUnit(row.unit, base);
  if (factor !== null) return { convertible: true, warning: null, blocked: false };
  // Catalog unit is convertible but the chosen unit is not -> hard block.
  if (isKnownUnit(base) && !isKnownUnit(row.unit)) {
    return { convertible: false, blocked: true, warning: `Unit "${row.unit}" can't convert to catalog unit "${base}".` };
  }
  // Outlier catalog unit is allowed with a warning.
  return { convertible: false, blocked: false, warning: `Unit "${row.unit}" can't be converted to catalog unit "${base}"; cost uses entered unit as-is.` };
}

function costPerIngredient(row: IngredientRow): number {
  if (!row.catalogItem || !row.quantity) return 0;
  const qty = parseFloat(row.quantity);
  if (isNaN(qty) || qty <= 0) return 0;
  // Convert qty from the recipe unit into the item's base_unit, then × ₱/base_unit.
  const factor = basePerUnit(row.unit, row.catalogItem.base_unit);
  const qtyInBase = factor !== null ? qty * factor : qty;
  return qtyInBase * row.catalogItem.unit_cost;
}

let rowKey = 5000;

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function NewFSSRecipePage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const singleItemMode = searchParams.get("single") === "1";

  const [name, setName]           = useState("");
  const [category, setCategory]   = useState("");
  const [servings, setServings]   = useState("1");
  const [prepNotes, setPrepNotes] = useState("");
  const [previewServings, setPreviewServings] = useState(""); // blank = baseline; read-only scale preview
  const [ingredients, setIngredients] = useState<IngredientRow[]>([
    { key: rowKey++, catalogItem: null, search: "", results: [], showDropdown: false, quantity: "", unit: "g" },
  ]);
  const [saving, setSaving]   = useState(false);
  const [error, setError]     = useState<string | null>(null);

  const doSearch = useCallback(async (q: string, idx: number) => {
    if (q.length < 2) {
      setIngredients((prev) => prev.map((r, i) => i === idx ? { ...r, results: [], showDropdown: false } : r));
      return;
    }
    const items = await searchCatalog(q, "ingredient");
    setIngredients((prev) => prev.map((r, i) => i === idx ? { ...r, results: items, showDropdown: true } : r));
  }, []);

  const updateRow = (idx: number, patch: Partial<IngredientRow>) =>
    setIngredients((prev) => prev.map((r, i) => i === idx ? { ...r, ...patch } : r));

  const selectItem = (idx: number, item: CatalogItem) =>
    updateRow(idx, { catalogItem: item, search: item.name, showDropdown: false, results: [], unit: item.base_unit });

  const totalCost = ingredients.reduce((sum, row) => sum + costPerIngredient(row), 0);
  const servingsNum = parseInt(servings) || 1;
  const previewNum = parseInt(previewServings) || servingsNum;
  const previewFactor = servingsNum > 0 ? previewNum / servingsNum : 1;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) { setError("Recipe name is required."); return; }

    const valid = ingredients
      .filter((r) => r.catalogItem && r.quantity && parseFloat(r.quantity) > 0)
      .map((r) => ({ fs_item_id: r.catalogItem!.id, quantity: parseFloat(r.quantity), unit: r.unit }));

    if (valid.length === 0) { setError("Add at least one ingredient."); return; }
    if (singleItemMode && valid.length !== 1) { setError("Single item foods must have exactly one ingredient."); return; }

    // Block bundle/non-convertible units when the catalog unit is convertible.
    const blockedRow = ingredients.find((r) => r.catalogItem && r.quantity && rowConversion(r).blocked);
    if (blockedRow) { setError(rowConversion(blockedRow).warning ?? "Incompatible ingredient unit."); return; }

    try {
      setSaving(true);
      setError(null);
      const res = await fetch("/api/fss/food-service-recipes", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: name.trim(),
          category: category || null,
          servings: servingsNum,
          prep_notes: prepNotes || null,
          ingredients: valid,
        }),
      });
      if (!res.ok) {
        const d = await res.json().catch(() => ({}));
        throw new Error(d.message ?? "Failed to save.");
      }
      router.push("/food-service/foods");
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6 font-sans max-w-3xl mx-auto">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span>/</span>
        <Link href="/food-service/foods" className="hover:text-emerald-700 transition-colors">Foods</Link>
        <span>/</span>
        <span className="text-zinc-650 font-bold">{singleItemMode ? "Single Item" : "New Recipe"}</span>
      </div>

      {/* Page header */}
      <div className="border-b border-warm-200 pb-5 flex items-center gap-4">
        <Link href="/food-service/foods"
          className="p-2 rounded-lg border border-warm-200 hover:bg-warm-50 text-warm-500 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </Link>
        <div>
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
            <CookingPot className="h-5 w-5 text-emerald-600" />
            {singleItemMode ? "New Single Item" : "New Food"}
          </h2>
          <p className="text-sm text-warm-500 mt-1 select-none">
            {singleItemMode ? "Use one catalog ingredient with a one-serving baseline." : "Ingredients sourced from the catalog. Cost calculates live."}
          </p>
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
              <Label>Category</Label>
              <select value={category} onChange={(e) => setCategory(e.target.value)} className={inputCls}>
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
          <div className="text-sm text-warm-400 text-right max-w-48">
            <span className="font-bold text-warm-500">Cost formula:</span><br />
            Σ qty × ₱/unit
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
            <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Ingredients</h3>
            {!singleItemMode && (
              <button type="button"
                onClick={() => setIngredients((prev) => [...prev, { key: rowKey++, catalogItem: null, search: "", results: [], showDropdown: false, quantity: "", unit: "g" }])}
                className="flex items-center gap-1.5 px-3 py-1.5 border border-warm-300 rounded-lg text-warm-600 hover:bg-warm-50 cursor-pointer transition-colors text-xs font-bold uppercase tracking-wider">
                <Plus className="h-3 w-3" /> Add Row
              </button>
            )}
          </div>

          {/* Column headers */}
          <div className="grid grid-cols-[1fr_80px_72px_80px_28px] gap-2 text-xs font-extrabold text-warm-400 uppercase tracking-wider px-1">
            <span>Catalog ingredient</span>
            <span>Qty (g)</span>
            <span>Unit</span>
            <span className="text-right">₱ Cost</span>
            <span />
          </div>

          <div className="space-y-3">
            {ingredients.map((row, idx) => {
              const conv = rowConversion(row);
              return (
              <React.Fragment key={row.key}>
              <div className="grid grid-cols-[1fr_80px_72px_80px_28px] gap-2 items-start">
                {/* Ingredient search */}
                <div className="relative">
                  <div className="flex items-center gap-2 w-full px-3 py-2 border border-warm-300 rounded-lg bg-white">
                    <Search className="h-3.5 w-3.5 text-warm-400 shrink-0" />
                    <input
                      type="text"
                      value={row.search}
                      onChange={async (e) => {
                        const v = e.target.value;
                        updateRow(idx, { search: v, catalogItem: v ? row.catalogItem : null });
                        await doSearch(v, idx);
                      }}
                      className="flex-1 text-base text-warm-900 outline-none placeholder:text-warm-400 bg-transparent"
                    />
                  </div>
                  {row.showDropdown && row.results.length > 0 && (
                    <div className="absolute top-full left-0 right-0 z-30 mt-1 bg-white border border-warm-200 rounded-xl shadow-lg overflow-hidden">
                      {row.results.map((item) => (
                        <button key={item.id} type="button" onClick={() => selectItem(idx, item)}
                          className="w-full text-left px-3 py-2 hover:bg-warm-50 transition-colors border-b border-warm-100 last:border-0 cursor-pointer">
                          <div className="text-sm font-bold text-warm-900">{item.name}</div>
                          <div className="text-xs text-warm-400">₱{item.unit_cost.toFixed(2)}/{item.base_unit}</div>
                        </button>
                      ))}
                    </div>
                  )}
                </div>

                {/* Quantity */}
                <input type="number" value={row.quantity}
                  onChange={(e) => updateRow(idx, { quantity: e.target.value })}
                  min="0" step="0.1"
                  className="w-full px-3 py-2 text-base border border-warm-300 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />

                {/* Catalog units first, then common recipe units. */}
                <select value={row.unit} onChange={(e) => updateRow(idx, { unit: e.target.value })}
                  className="w-full px-2 py-2 text-base border border-warm-300 rounded-lg text-warm-900 focus:outline-none cursor-pointer">
                  {[...CATALOG_UNIT_OPTIONS, "cup", "oz", "tbsp", "tsp"].map((u) => <option key={u} value={u}>{u}</option>)}
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
              {row.catalogItem && conv.warning && (
                <div className={`text-xs font-semibold px-1 ${conv.blocked ? "text-red-600" : "text-amber-600"}`}>
                  {conv.blocked ? "⛔ " : "⚠ "}{conv.warning}
                </div>
              )}
              </React.Fragment>
              );
            })}
          </div>
        </div>

        {/* Actions */}
        <div className="flex gap-3 justify-end pb-4">
          <Link href="/food-service/foods">
            <Button variant="secondary" className="w-auto px-6 py-2.5">Cancel</Button>
          </Link>
          <Button type="submit" variant="primary" loading={saving} className="w-auto px-6 py-2.5">
            {singleItemMode ? "Create Single Item" : "Create Recipe"}
          </Button>
        </div>
      </form>
    </div>
  );
}
