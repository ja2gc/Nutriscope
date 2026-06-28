"use client";

import React, { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { CookingPot, ArrowLeft, Search, Plus, X, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  fetchRecipeById, updateRecipe, fetchFoodItems,
  Recipe, FoodItem, RecipeIngredientPayload,
} from "@/services/foodDatabaseService";

const RECIPE_CATEGORIES = ["breakfast", "lunch", "dinner", "snack"];

interface IngredientRow {
  key: number;
  food: FoodItem | null;
  foodSearch: string;
  searchResults: FoodItem[];
  showDropdown: boolean;
  quantity: string;
  unit: string;
}

function calcMacros(ingredients: IngredientRow[]) {
  let cal = 0, pro = 0, carb = 0, fat = 0;
  for (const row of ingredients) {
    if (!row.food || !row.quantity) continue;
    const qty = parseFloat(row.quantity);
    const size = parseFloat(row.food.serving_size ?? "100") || 100;
    const factor = qty / size;
    cal  += parseFloat(row.food.calories ?? "0") * factor;
    pro  += parseFloat(row.food.protein  ?? "0") * factor;
    carb += parseFloat(row.food.carbs    ?? "0") * factor;
    fat  += parseFloat(row.food.fat      ?? "0") * factor;
  }
  return { cal: cal.toFixed(1), pro: pro.toFixed(1), carb: carb.toFixed(1), fat: fat.toFixed(1) };
}

let rowKey = 1000;

export default function EditRecipePage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const router = useRouter();

  const [recipe, setRecipe] = useState<Recipe | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [name, setName] = useState("");
  const [category, setCategory] = useState("");
  const [servings, setServings] = useState("1");
  const [prepNotes, setPrepNotes] = useState("");
  const [ingredients, setIngredients] = useState<IngredientRow[]>([]);

  function toRow(ing: Recipe["ingredients"][number]): IngredientRow {
    return {
      key: rowKey++,
      food: ing.food_item as FoodItem | null,
      foodSearch: ing.food_item?.name ?? "",
      searchResults: [],
      showDropdown: false,
      quantity: ing.quantity ?? "",
      unit: ing.unit ?? "g",
    };
  }

  useEffect(() => {
    fetchRecipeById(id)
      .then((r) => {
        setRecipe(r);
        setName(r.name);
        setCategory(r.category ?? "");
        setServings(String(r.servings ?? 1));
        setPrepNotes(r.prep_notes ?? "");
        setIngredients(r.ingredients?.length ? r.ingredients.map(toRow) : [{ key: rowKey++, food: null, foodSearch: "", searchResults: [], showDropdown: false, quantity: "", unit: "g" }]);
      })
      .catch((e: unknown) => setError(e instanceof Error ? e.message : "Failed to load."))
      .finally(() => setLoading(false));
  }, [id]);

  const searchFoods = useCallback(async (query: string, rowIdx: number) => {
    if (query.length < 2) {
      setIngredients((prev) => prev.map((r, i) => i === rowIdx ? { ...r, searchResults: [], showDropdown: false } : r));
      return;
    }
    const res = await fetchFoodItems(query, "all", 1);
    setIngredients((prev) => prev.map((r, i) => i === rowIdx ? { ...r, searchResults: res.data.slice(0, 8), showDropdown: true } : r));
  }, []);

  const updateRow = (idx: number, patch: Partial<IngredientRow>) => {
    setIngredients((prev) => prev.map((r, i) => i === idx ? { ...r, ...patch } : r));
  };

  const selectFood = (idx: number, food: FoodItem) => {
    updateRow(idx, { food, foodSearch: food.name, showDropdown: false, searchResults: [], unit: food.serving_unit ?? "g" });
  };

  const addRow = () => setIngredients((prev) => [...prev, { key: rowKey++, food: null, foodSearch: "", searchResults: [], showDropdown: false, quantity: "", unit: "g" }]);
  const removeRow = (idx: number) => setIngredients((prev) => prev.filter((_, i) => i !== idx));

  const macros = calcMacros(ingredients);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) { setError("Recipe name is required."); return; }
    const validIngredients: RecipeIngredientPayload[] = ingredients
      .filter((r) => r.food && r.quantity && parseFloat(r.quantity) > 0)
      .map((r) => ({ food_item_id: r.food!.id, quantity: parseFloat(r.quantity), unit: r.unit }));
    try {
      setSaving(true);
      setError(null);
      await updateRecipe(id, {
        name: name.trim(),
        category: category || null,
        servings: servings ? parseInt(servings) : null,
        prep_notes: prepNotes || null,
        ingredients: validIngredients,
      });
      router.push("/food-database");
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Failed to save recipe.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <div className="flex items-center justify-center h-64"><Loader2 className="h-6 w-6 animate-spin text-emerald-600" /></div>;
  }

  if (!recipe && error) {
    return <div className="p-6 bg-red-50 border border-red-100 rounded-2xl text-xs text-red-700 font-bold">{error}</div>;
  }

  return (
    <div className="space-y-6 font-sans max-w-3xl mx-auto">
      <div className="flex items-center gap-2 text-xs font-semibold text-warm-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span>/</span>
        <Link href="/food-database" className="hover:text-emerald-700 transition-colors">Food Database</Link>
        <span>/</span>
        <span className="text-zinc-650 font-bold truncate max-w-40">{recipe?.name}</span>
      </div>

      <div className="border-b border-warm-200 pb-5 flex items-center gap-4">
        <Link href="/food-database" className="p-2 rounded-lg border border-warm-200 hover:bg-warm-50 text-warm-500 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </Link>
        <div>
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
            <CookingPot className="h-5 w-5 text-emerald-600" />
            Edit Recipe
          </h2>
          <p className="text-xs text-warm-500 mt-1 select-none">Update ingredients and recalculate macro totals.</p>
        </div>
      </div>

      {error && <div className="bg-red-50 border border-red-100 p-4 rounded-xl text-xs text-red-700 font-bold">{error}</div>}

      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="bg-white border border-warm-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <h3 className="text-xs font-extrabold text-warm-700 uppercase tracking-wider">Recipe Details</h3>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="sm:col-span-2">
              <Label>Recipe Name <Required /></Label>
              <input type="text" value={name} onChange={(e) => setName(e.target.value)} className={inputCls} required />
            </div>
            <div>
              <Label>Category</Label>
              <select value={category} onChange={(e) => setCategory(e.target.value)} className={inputCls}>
                <option value="">Select...</option>
                {RECIPE_CATEGORIES.map((c) => <option key={c} value={c}>{c.charAt(0).toUpperCase() + c.slice(1)}</option>)}
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

        {/* Macro Preview */}
        <div className="grid grid-cols-4 gap-3">
          {[
            { label: "Calories", value: macros.cal, unit: "kcal", color: "emerald" },
            { label: "Protein",  value: macros.pro,  unit: "g", color: "blue" },
            { label: "Carbs",    value: macros.carb, unit: "g", color: "amber" },
            { label: "Fat",      value: macros.fat,  unit: "g", color: "rose" },
          ].map(({ label, value, unit, color }) => (
            <div key={label} className="bg-white border border-warm-200 rounded-xl p-3 text-center shadow-sm">
              <div className="text-[10px] font-extrabold text-warm-500 uppercase tracking-wider">{label}</div>
              <div className={`text-lg font-extrabold mt-1 text-${color}-600`}>{value}</div>
              <div className="text-[9px] text-warm-400 font-semibold">{unit}</div>
            </div>
          ))}
        </div>

        {/* Ingredients */}
        <div className="bg-white border border-warm-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <div className="flex items-center justify-between">
            <h3 className="text-xs font-extrabold text-warm-700 uppercase tracking-wider">Ingredients</h3>
            <button type="button" onClick={addRow} className="flex items-center gap-1.5 px-3 py-1.5 border border-warm-300 rounded-lg text-warm-600 hover:bg-warm-50 cursor-pointer transition-colors text-[10px] font-bold uppercase tracking-wider">
              <Plus className="h-3 w-3" /> Add Row
            </button>
          </div>
          <div className="space-y-3">
            {ingredients.map((row, idx) => (
              <div key={row.key} className="flex gap-2 items-start">
                <div className="relative flex-1">
                  <div className="flex items-center gap-2 w-full px-3 py-2 border border-warm-300 rounded-lg bg-white">
                    <Search className="h-3.5 w-3.5 text-warm-400 shrink-0" />
                    <input
                      type="text"
                      value={row.foodSearch}
                      onChange={async (e) => {
                        const val = e.target.value;
                        updateRow(idx, { foodSearch: val, food: val ? row.food : null });
                        await searchFoods(val, idx);
                      }}
                      placeholder="Search food..."
                      className="flex-1 text-sm text-warm-900 outline-none placeholder:text-warm-400 bg-transparent"
                    />
                    {row.food && <span className="text-[9px] text-emerald-600 font-bold shrink-0">{row.food.calories} kcal</span>}
                  </div>
                  {row.showDropdown && row.searchResults.length > 0 && (
                    <div className="absolute top-full left-0 right-0 z-30 mt-1 bg-white border border-warm-200 rounded-xl shadow-lg overflow-hidden">
                      {row.searchResults.map((food) => (
                        <button
                          key={food.id}
                          type="button"
                          onClick={() => selectFood(idx, food)}
                          className="w-full text-left px-3 py-2 hover:bg-warm-50 transition-colors border-b border-warm-100 last:border-0 cursor-pointer"
                        >
                          <div className="text-xs font-bold text-warm-900">{food.name}</div>
                          <div className="text-[10px] text-warm-400">{food.calories} kcal · P {food.protein ?? 0}g · C {food.carbs ?? 0}g</div>
                        </button>
                      ))}
                    </div>
                  )}
                </div>
                <input
                  type="number"
                  value={row.quantity}
                  onChange={(e) => updateRow(idx, { quantity: e.target.value })}
                  placeholder="Qty"
                  min="0"
                  step="0.1"
                  className="w-20 px-3 py-2 text-sm border border-warm-300 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                />
                <select
                  value={row.unit}
                  onChange={(e) => updateRow(idx, { unit: e.target.value })}
                  className="w-20 px-2 py-2 text-sm border border-warm-300 rounded-lg text-warm-900 focus:outline-none cursor-pointer"
                >
                  {["g", "ml", "piece", "cup", "oz", "tbsp", "tsp"].map((u) => <option key={u} value={u}>{u}</option>)}
                </select>
                <button
                  type="button"
                  onClick={() => removeRow(idx)}
                  disabled={ingredients.length === 1}
                  className="p-2 rounded-lg text-warm-400 hover:text-red-500 hover:bg-red-50 disabled:opacity-30 cursor-pointer transition-colors"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              </div>
            ))}
          </div>
        </div>

        <div className="flex gap-3 justify-end pb-4">
          <Link href="/food-database">
            <Button variant="secondary" className="w-auto px-6 py-2.5">Cancel</Button>
          </Link>
          <Button type="submit" variant="primary" loading={saving} className="w-auto px-6 py-2.5">Save Changes</Button>
        </div>
      </form>
    </div>
  );
}

const inputCls = "w-full px-3 py-2 text-sm bg-white border border-warm-300 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-warm-400";
function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-[10px] font-extrabold text-warm-500 uppercase tracking-wider mb-1.5">{children}</label>;
}
function Required() { return <span className="text-red-500 ml-0.5">*</span>; }
