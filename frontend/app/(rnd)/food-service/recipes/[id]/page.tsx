"use client";

import React, { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { CookingPot, ArrowLeft, Search, Plus, X, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/Button";

// ─── Types ────────────────────────────────────────────────────────────────────

interface InventoryItem {
  id: number;        // fs_item_id
  name: string;
  unit_cost: number; // ₱ per base_unit
  base_unit: string;
}

interface IngredientRow {
  key: number;
  invItem: InventoryItem | null;
  search: string;
  results: InventoryItem[];
  showDropdown: boolean;
  quantity: string;
  unit: string;
}

interface FSSRecipe {
  id: number;
  name: string;
  category: string | null;
  prep_notes: string | null;
  servings: number;
  cost: number;
  ingredients: Array<{
    id: number;
    fs_item_id: number;
    quantity: number;
    unit: string;
    fs_item: { id: number; name: string; unit_cost: number; base_unit: string } | null;
  }>;
}

// ─── API helpers ──────────────────────────────────────────────────────────────

async function loadRecipe(id: string): Promise<FSSRecipe> {
  const res = await fetch(`/api/fss/food-service-recipes/${id}`);
  if (!res.ok) throw new Error("Recipe not found.");
  return (await res.json()).data;
}

async function searchInventory(q: string): Promise<InventoryItem[]> {
  const res = await fetch(`/api/fss/inventory/rows?search=${encodeURIComponent(q)}&per_page=8`);
  if (!res.ok) return [];
  const json = await res.json();
  const rows = (json.data ?? []) as Array<{
    item_id: number; name: string; unit_cost: string | null; base_unit: string | null; item_type: string;
  }>;
  return rows
    .filter((r) => r.item_type === "ingredient")
    .map((r) => ({ id: r.item_id, name: r.name, unit_cost: parseFloat(r.unit_cost ?? "0"), base_unit: r.base_unit ?? "g" }));
}

const FSS_CATEGORIES = [
  "beverage", "breakfast", "lunch", "snack", "dinner",
];

const inputCls = "w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all";

function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">{children}</label>;
}

function Required() {
  return <span className="text-red-500 ml-0.5">*</span>;
}

// ─── Cost calculation ─────────────────────────────────────────────────────────

function calcCost(rows: IngredientRow[], _servings: number): number {
  return rows.reduce((sum, row) => {
    if (!row.invItem || !row.quantity) return sum;
    const qty = parseFloat(row.quantity);
    if (isNaN(qty) || qty <= 0) return sum;
    return sum + qty * row.invItem.unit_cost;
  }, 0);
}

function costPerIngredient(row: IngredientRow): number {
  if (!row.invItem || !row.quantity) return 0;
  const qty = parseFloat(row.quantity);
  if (isNaN(qty) || qty <= 0) return 0;
  return qty * row.invItem.unit_cost;
}

let rowKey = 3000;

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function EditFSSRecipePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
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
                invItem: ing.fs_item
                  ? { id: ing.fs_item.id, name: ing.fs_item.name, unit_cost: ing.fs_item.unit_cost, base_unit: ing.fs_item.base_unit }
                  : null,
                search: ing.fs_item?.name ?? "",
                results: [],
                showDropdown: false,
                quantity: String(ing.quantity),
                unit: ing.unit,
              }))
            : [{ key: rowKey++, invItem: null, search: "", results: [], showDropdown: false, quantity: "", unit: "g" }]
        );
      })
      .catch((e: unknown) => setError(e instanceof Error ? e.message : "Failed to load."))
      .finally(() => setLoading(false));
  }, [id]);

  const doSearch = useCallback(async (q: string, idx: number) => {
    if (q.length < 2) {
      setIngredients((prev) => prev.map((r, i) => i === idx ? { ...r, results: [], showDropdown: false } : r));
      return;
    }
    const items = await searchInventory(q);
    setIngredients((prev) => prev.map((r, i) => i === idx ? { ...r, results: items, showDropdown: true } : r));
  }, []);

  const updateRow = (idx: number, patch: Partial<IngredientRow>) =>
    setIngredients((prev) => prev.map((r, i) => i === idx ? { ...r, ...patch } : r));

  const selectItem = (idx: number, item: InventoryItem) =>
    updateRow(idx, { invItem: item, search: item.name, showDropdown: false, results: [], unit: item.base_unit });

  const totalCost = calcCost(ingredients, parseInt(servings) || 1);
  const servingsNum = parseInt(servings) || 1;
  const previewNum = parseInt(previewServings) || servingsNum;
  const previewFactor = servingsNum > 0 ? previewNum / servingsNum : 1;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) { setError("Recipe name is required."); return; }

    const valid = ingredients
      .filter((r) => r.invItem && r.quantity && parseFloat(r.quantity) > 0)
      .map((r) => ({ fs_item_id: r.invItem!.id, quantity: parseFloat(r.quantity), unit: r.unit }));

    if (valid.length === 0) { setError("Add at least one ingredient."); return; }

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
      router.push("/food-service/foods");
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <Loader2 className="h-6 w-6 animate-spin text-emerald-600" />
    </div>
  );

  if (!recipe && error) return (
    <div className="p-6 bg-red-50 border border-red-100 rounded-2xl text-xs text-red-700 font-bold">{error}</div>
  );

  return (
    <div className="space-y-6 font-sans max-w-3xl mx-auto">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span>/</span>
        <Link href="/food-service/foods" className="hover:text-emerald-700 transition-colors">Foods</Link>
        <span>/</span>
        <span className="text-zinc-650 font-bold truncate max-w-40">{recipe?.name}</span>
      </div>

      {/* Page header */}
      <div className="border-b border-zinc-200 pb-5 flex items-center gap-4">
        <Link href="/food-service/foods"
          className="p-2 rounded-lg border border-zinc-200 hover:bg-zinc-50 text-zinc-500 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </Link>
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <CookingPot className="h-5 w-5 text-emerald-600" />
            Edit Food
          </h2>
          <p className="text-xs text-zinc-500 mt-1 select-none">
            Ingredients sourced from inventory. Cost calculates live.
          </p>
        </div>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-100 p-4 rounded-xl text-xs text-red-700 font-bold">{error}</div>
      )}

      <form onSubmit={handleSubmit} className="space-y-5">
        {/* Recipe details card */}
        <div className="bg-white border border-zinc-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Recipe Details</h3>
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
        <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm flex items-center gap-5">
          <div className="flex-1">
            <div className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">Total Cost</div>
            <div className="text-3xl font-extrabold text-emerald-600">
              ₱{totalCost.toFixed(2)}
            </div>
            <div className="text-[10px] text-zinc-400 font-semibold mt-1">
              per recipe ({servingsNum} serving{servingsNum !== 1 ? "s" : ""})
              {servingsNum > 1 && (
                <span className="ml-2 text-zinc-500">
                  · ₱{(totalCost / servingsNum).toFixed(2)} per serving
                </span>
              )}
            </div>
          </div>
          <div className="text-xs text-zinc-400 text-right max-w-48">
            <span className="font-bold text-zinc-500">Cost formula:</span><br />
            Σ qty × ₱/unit
          </div>
        </div>

        {/* Serving scaler — read-only preview, does NOT change the saved baseline */}
        <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-3">
          <div className="flex items-center justify-between gap-4 flex-wrap">
            <div className="flex items-center gap-2">
              <span className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Preview at</span>
              <input type="number" min="1" value={previewServings} onChange={(e) => setPreviewServings(e.target.value)}
                placeholder={String(servingsNum)}
                className="w-20 px-2.5 py-1.5 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
              <span className="text-xs text-zinc-500">servings</span>
            </div>
            <div className="text-right">
              <div className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Scaled total</div>
              <div className="text-xl font-extrabold text-emerald-600">₱{(totalCost * previewFactor).toFixed(2)}</div>
              <div className="text-[10px] text-zinc-400 font-semibold">
                ×{previewFactor.toFixed(2)} from {servingsNum}-serving baseline · preview only
              </div>
            </div>
          </div>
          {Math.abs(previewFactor - 1) > 1e-9 && (
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-1.5 pt-2 border-t border-zinc-100">
              {ingredients.filter((r) => r.invItem && r.quantity && parseFloat(r.quantity) > 0).map((r) => (
                <div key={r.key} className="text-[10px] text-zinc-500 truncate">
                  <span className="text-zinc-700 font-semibold">{r.invItem!.name}:</span>{" "}
                  {(parseFloat(r.quantity) * previewFactor).toFixed(1)} {r.unit}
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Ingredients */}
        <div className="bg-white border border-zinc-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <div className="flex items-center justify-between">
            <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Ingredients</h3>
            <button type="button"
              onClick={() => setIngredients((prev) => [...prev, { key: rowKey++, invItem: null, search: "", results: [], showDropdown: false, quantity: "", unit: "g" }])}
              className="flex items-center gap-1.5 px-3 py-1.5 border border-zinc-300 rounded-lg text-zinc-600 hover:bg-zinc-50 cursor-pointer transition-colors text-[10px] font-bold uppercase tracking-wider">
              <Plus className="h-3 w-3" /> Add Row
            </button>
          </div>

          {/* Column headers */}
          <div className="grid grid-cols-[1fr_80px_72px_80px_28px] gap-2 text-[9px] font-extrabold text-zinc-400 uppercase tracking-wider px-1">
            <span>Ingredient (from inventory)</span>
            <span>Qty (g)</span>
            <span>Unit</span>
            <span className="text-right">₱ Cost</span>
            <span />
          </div>

          <div className="space-y-3">
            {ingredients.map((row, idx) => (
              <div key={row.key} className="grid grid-cols-[1fr_80px_72px_80px_28px] gap-2 items-start">
                {/* Ingredient search */}
                <div className="relative">
                  <div className="flex items-center gap-2 w-full px-3 py-2 border border-zinc-300 rounded-lg bg-white">
                    <Search className="h-3.5 w-3.5 text-zinc-400 shrink-0" />
                    <input
                      type="text"
                      value={row.search}
                      onChange={async (e) => {
                        const v = e.target.value;
                        updateRow(idx, { search: v, invItem: v ? row.invItem : null });
                        await doSearch(v, idx);
                      }}
                      placeholder="Search inventory..."
                      className="flex-1 text-sm text-zinc-900 outline-none placeholder:text-zinc-400 bg-transparent"
                    />
                  </div>
                  {row.showDropdown && row.results.length > 0 && (
                    <div className="absolute top-full left-0 right-0 z-30 mt-1 bg-white border border-zinc-200 rounded-xl shadow-lg overflow-hidden">
                      {row.results.map((item) => (
                        <button key={item.id} type="button" onClick={() => selectItem(idx, item)}
                          className="w-full text-left px-3 py-2 hover:bg-zinc-50 transition-colors border-b border-zinc-100 last:border-0 cursor-pointer">
                          <div className="text-xs font-bold text-zinc-900">{item.name}</div>
                          <div className="text-[10px] text-zinc-400">₱{item.unit_cost.toFixed(2)}/{item.base_unit}</div>
                        </button>
                      ))}
                    </div>
                  )}
                </div>

                {/* Quantity */}
                <input type="number" value={row.quantity}
                  onChange={(e) => updateRow(idx, { quantity: e.target.value })}
                  placeholder="0" min="0" step="0.1"
                  className="w-full px-3 py-2 text-sm border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />

                {/* Unit */}
                <select value={row.unit} onChange={(e) => updateRow(idx, { unit: e.target.value })}
                  className="w-full px-2 py-2 text-sm border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none cursor-pointer">
                  {["g", "ml", "piece", "cup", "oz", "tbsp", "tsp"].map((u) => <option key={u} value={u}>{u}</option>)}
                </select>

                {/* Cost contribution */}
                <div className="py-2 text-right text-xs font-bold text-emerald-700">
                  {row.invItem && row.quantity
                    ? `₱${costPerIngredient(row).toFixed(2)}`
                    : <span className="text-zinc-300">—</span>}
                </div>

                {/* Remove */}
                <button type="button" onClick={() => setIngredients((prev) => prev.filter((_, i) => i !== idx))}
                  disabled={ingredients.length === 1}
                  className="mt-1.5 p-1.5 rounded-lg text-zinc-400 hover:text-red-500 hover:bg-red-50 disabled:opacity-30 cursor-pointer transition-colors">
                  <X className="h-3.5 w-3.5" />
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* Actions */}
        <div className="flex gap-3 justify-end pb-4">
          <Link href="/food-service/foods">
            <Button variant="secondary" className="w-auto px-6 py-2.5">Cancel</Button>
          </Link>
          <Button type="submit" variant="primary" loading={saving} className="w-auto px-6 py-2.5">
            Save Changes
          </Button>
        </div>
      </form>
    </div>
  );
}
