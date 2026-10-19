"use client";

import React, { useCallback, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { CookingPot, ArrowLeft, Search, Plus, X } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { InfoHint } from "@/components/ui/InfoHint";
import { canonicalClinicalUnit, clinicalIngredientFactor, clinicalRecipeUnits, CLINICAL_UNIT_HINT } from "@/lib/clinicalUnits";
import { CLINICAL_BASELINE_UNITS } from "@/lib/clinicalUnits";
import { ALL_MICROS } from "@/lib/nutritionCalculations";
import { createRecipe, fetchFoodItems, FoodItem, RecipeIngredientPayload } from "@/services/foodLibraryService";

const RECIPE_CATEGORIES = ["meal", "carb", "snack"];
const COMMON_ALLERGENS = ["milk", "eggs", "fish", "shellfish", "tree nuts", "peanuts", "wheat", "soybeans"];
const LABEL_NUTRIENTS = [
  { key: "calories", label: "Calories", unit: "kcal", max: 10000 },
  { key: "protein", label: "Protein", unit: "g", max: 1000 },
  { key: "carbs", label: "Carbohydrate", unit: "g", max: 1000 },
  { key: "fat", label: "Fat", unit: "g", max: 1000 },
  { key: "water_g", label: "Water", unit: "g", max: undefined },
];

interface IngredientRow {
  key: number;
  food: FoodItem | null;
  foodSearch: string;
  searchResults: FoodItem[];
  showDropdown: boolean;
  quantity: string;
  unit: string;
}

function calcMacros(rows: IngredientRow[]) {
  let cal = 0, pro = 0, carb = 0, fat = 0;
  for (const row of rows) {
    if (!row.food || !row.quantity) continue;
    const qty = parseFloat(row.quantity);
    const factor = clinicalIngredientFactor(qty, row.unit, Number(row.food.serving_size), row.food.serving_unit ?? "");
    if (factor === null) continue;
    cal  += parseFloat(row.food.calories ?? "0") * factor;
    pro  += parseFloat(row.food.protein  ?? "0") * factor;
    carb += parseFloat(row.food.carbs    ?? "0") * factor;
    fat  += parseFloat(row.food.fat      ?? "0") * factor;
  }
  return { cal: cal.toFixed(1), pro: pro.toFixed(1), carb: carb.toFixed(1), fat: fat.toFixed(1) };
}

let rowKey = 0;

export default function NewRecipePage() {
  const router = useRouter();
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [name, setName] = useState("");
  const [category, setCategory] = useState("");
  const [servings, setServings] = useState("");
  const [prepNotes, setPrepNotes] = useState("");
  const [ingredients, setIngredients] = useState<IngredientRow[]>([newRow()]);
  const [nutritionSource, setNutritionSource] = useState("ingredients");
  const [labelAmount, setLabelAmount] = useState("100");
  const [labelUnit, setLabelUnit] = useState("g");
  const [labelValues, setLabelValues] = useState<Record<string, string>>({});
  const [allergens, setAllergens] = useState<string[]>([]);
  const [customAllergen, setCustomAllergen] = useState("");

  function newRow(): IngredientRow {
    return { key: rowKey++, food: null, foodSearch: "", searchResults: [], showDropdown: false, quantity: "", unit: "g" };
  }

  const searchFoods = useCallback(async (query: string, idx: number) => {
    if (query.length < 2) {
      setIngredients((prev) => prev.map((r, i) => i === idx ? { ...r, searchResults: [], showDropdown: false } : r));
      return;
    }
    const res = await fetchFoodItems(query, "all", 1);
    setIngredients((prev) => prev.map((r, i) => i === idx ? { ...r, searchResults: res.data.slice(0, 5), showDropdown: true } : r));
  }, [setIngredients]);

  const updateRow = (idx: number, patch: Partial<IngredientRow>) =>
    setIngredients((prev) => prev.map((r, i) => i === idx ? { ...r, ...patch } : r));

  const selectFood = (idx: number, food: FoodItem) =>
    updateRow(idx, { food, foodSearch: food.name, showDropdown: false, searchResults: [], unit: canonicalClinicalUnit(food.serving_unit ?? "") });

  const macros = calcMacros(ingredients);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) { setError("Menu Item name is required."); return; }
    if (!category || (nutritionSource === "ingredients" && ingredients.some((row) => !row.food || clinicalIngredientFactor(Number(row.quantity), row.unit, Number(row.food.serving_size), row.food.serving_unit ?? "") === null))) {
      setError("Choose a category and measured Ingredients with positive quantities in compatible units."); return;
    }
    const valid: RecipeIngredientPayload[] = ingredients
      .filter((r) => r.food && r.quantity && parseFloat(r.quantity) > 0)
      .map((r) => ({ food_item_id: r.food!.id, quantity: parseFloat(r.quantity), unit: r.unit }));
    try {
      setSaving(true);
      setError(null);
      const nutrition = nutritionSource === "label" ? {
        nutrition_label: {
          serving_size: Number(labelAmount), serving_unit: labelUnit,
          calories: Number(labelValues.calories),
          protein: labelValues.protein ? Number(labelValues.protein) : null,
          carbs: labelValues.carbs ? Number(labelValues.carbs) : null,
          fat: labelValues.fat ? Number(labelValues.fat) : null,
          water_g: labelValues.water_g ? Number(labelValues.water_g) : null,
          micronutrients: Object.fromEntries(ALL_MICROS.filter(({ key }) => labelValues[key] !== undefined && labelValues[key] !== "").map(({ key }) => [key, Number(labelValues[key])])),
          allergens,
        },
      } : { ingredients: valid };
      await createRecipe({ name: name.trim(), category: category || null, servings: servings ? parseInt(servings) : null, prep_notes: prepNotes || null, ...nutrition });
      router.push("/food-library");
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Failed to save Menu Item.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6 font-sans max-w-3xl mx-auto">
      <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span>/</span>
        <Link href="/food-library" className="hover:text-emerald-700 transition-colors">Food Library</Link>
        <span>/</span>
        <span className="text-zinc-650 font-bold">Create Menu Item</span>
      </div>

      <div className="border-b border-warm-200 pb-5 flex items-center gap-4">
        <Link href="/food-library" className="p-2 rounded-lg border border-warm-200 hover:bg-warm-50 text-warm-500 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </Link>
        <div>
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
            <CookingPot className="h-5 w-5 text-emerald-600" />
            Create Menu Item
          </h2>
        </div>
      </div>

      {error && <div className="bg-red-50 border border-red-100 p-4 rounded-xl text-sm text-red-700 font-bold">{error}</div>}

      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="bg-white border border-warm-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Menu Item Details</h3>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="sm:col-span-2">
              <Label>Menu Item Name <Required /></Label>
              <input aria-label="Menu Item Name" maxLength={255} type="text" value={name} onChange={(e) => setName(e.target.value)} className={inputCls} required />
            </div>
            <div>
              <Label>Category</Label>
              <select aria-label="Category" value={category} onChange={(e) => setCategory(e.target.value)} className={inputCls} required>
                <option value="">Select...</option>
                {RECIPE_CATEGORIES.map((c) => <option key={c} value={c}>{c.charAt(0).toUpperCase() + c.slice(1)}</option>)}
              </select>
            </div>
            <div>
              <Label>Recipe yield (servings) <Required /></Label>
              <input aria-label="Recipe yield (servings)" type="number" value={servings} onChange={(e) => setServings(e.target.value)} min="1" max="10000" step="1" required className={inputCls} />
              <p className="mt-1 text-xs text-warm-500">{nutritionSource === "label" ? "Number of label servings in the full item or package." : "Ingredient quantities below are for the full batch."}</p>
            </div>
            <div className="sm:col-span-2">
              <Label>Prep Notes</Label>
              <textarea value={prepNotes} maxLength={2000} onChange={(e) => setPrepNotes(e.target.value)} rows={2} className={`${inputCls} resize-none`} />
            </div>
          </div>
        </div>

        <div>
          <Label>Nutrition source</Label>
          <select aria-label="Nutrition source" value={nutritionSource} onChange={(event) => setNutritionSource(event.target.value)} className={inputCls}>
            <option value="ingredients">Ingredients</option>
            <option value="label">Nutrition facts label</option>
          </select>
        </div>
        {nutritionSource === "label" ? <div className="space-y-4 rounded-2xl border border-warm-200 bg-white p-6 shadow-sm">
          <h3 className="text-sm font-extrabold uppercase tracking-wider text-warm-700">Nutrition facts per label serving</h3>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><Label>Label serving amount <Required /></Label><input aria-label="Label serving amount" type="number" value={labelAmount} onChange={(event) => setLabelAmount(event.target.value)} min="0" max="10000" step="0.1" required className={inputCls} /></div>
            <div><div className="flex items-center gap-1"><Label>Label serving unit</Label><InfoHint label="Label serving units">{CLINICAL_UNIT_HINT}</InfoHint></div><select aria-label="Label serving unit" value={labelUnit} onChange={(event) => { setLabelUnit(event.target.value); if (event.target.value === "piece") setLabelAmount("1"); }} className={inputCls}>{CLINICAL_BASELINE_UNITS.map((unit) => <option key={unit} value={unit}>{unit}</option>)}</select></div>
          </div>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {[...LABEL_NUTRIENTS, ...ALL_MICROS].map(({ key, label, unit }) => <div key={key}>
              <Label>{label} ({unit}){key === "calories" && <Required />}</Label>
              <input aria-label={`${label} (${unit})`} type="number" min="0" max={LABEL_NUTRIENTS.find((nutrient) => nutrient.key === key)?.max} step="0.01" value={labelValues[key] ?? ""} onChange={(event) => setLabelValues((previous) => ({ ...previous, [key]: event.target.value }))} required={key === "calories"} className={inputCls} />
            </div>)}
          </div>
          <fieldset className="space-y-3"><legend className="text-xs font-bold text-warm-600">Allergens</legend>
            <div className="flex flex-wrap gap-2">{[...new Set([...COMMON_ALLERGENS, ...allergens])].map((allergen) => <label key={allergen} className="flex items-center gap-2 rounded-lg border border-warm-200 px-3 py-2 text-sm"><input aria-label={`Contains ${allergen}`} type="checkbox" checked={allergens.includes(allergen)} onChange={() => setAllergens((previous) => previous.includes(allergen) ? previous.filter((entry) => entry !== allergen) : [...previous, allergen])} />{allergen}</label>)}</div>
            <div className="flex flex-wrap gap-2"><input aria-label="Other allergen" maxLength={100} value={customAllergen} onChange={(event) => setCustomAllergen(event.target.value)} className={`${inputCls} min-w-0 flex-1`} /><Button type="button" variant="secondary" onClick={() => { const value = customAllergen.trim().toLowerCase(); if (value && !allergens.includes(value)) setAllergens((previous) => [...previous, value]); setCustomAllergen(""); }}>Add allergen</Button></div>
          </fieldset>
          <p className="text-xs text-warm-500">Label values are also saved as a reusable Ingredient.</p>
        </div> : <>
        {/* Live macro preview */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[{ label: "Calories", value: macros.cal, unit: "kcal", color: "emerald" }, { label: "Protein", value: macros.pro, unit: "g", color: "blue" }, { label: "Carbs", value: macros.carb, unit: "g", color: "amber" }, { label: "Fat", value: macros.fat, unit: "g", color: "rose" }].map(({ label, value, unit, color }) => (
            <div key={label} className="bg-white border border-warm-200 rounded-xl p-3 text-center shadow-sm">
              <div className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">{label}</div>
              <div className={`text-lg font-extrabold mt-1 text-${color}-600`}>{value}</div>
              <div className="text-xs text-warm-400 font-semibold">{unit}</div>
            </div>
          ))}
        </div>

        {/* Ingredients */}
        <div className="bg-white border border-warm-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2"><h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Ingredients</h3><InfoHint label="Ingredient unit families">{CLINICAL_UNIT_HINT}</InfoHint></div>
            <button type="button" onClick={() => setIngredients((prev) => [...prev, newRow()])}
              className="flex items-center gap-1.5 px-3 py-1.5 border border-warm-300 rounded-lg text-warm-600 hover:bg-warm-50 cursor-pointer transition-colors text-xs font-bold uppercase tracking-wider">
              <Plus className="h-3 w-3" /> Add Item
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
                      onChange={async (e) => { const v = e.target.value; updateRow(idx, { foodSearch: v, food: v ? row.food : null }); await searchFoods(v, idx); }}
                      className="flex-1 text-base text-warm-900 outline-none placeholder:text-warm-400 bg-transparent"
                    />
                    {row.food && <span className="text-xs text-emerald-600 font-bold shrink-0">{row.food.calories} kcal</span>}
                  </div>
                  {row.showDropdown && row.searchResults.length > 0 && (
                    <div className="absolute top-full left-0 right-0 z-30 mt-1 bg-white border border-warm-200 rounded-xl shadow-lg overflow-hidden">
                      {row.searchResults.map((food) => (
                        <button key={food.id} type="button" onClick={() => selectFood(idx, food)}
                          className="w-full text-left px-3 py-2 hover:bg-warm-50 transition-colors border-b border-warm-100 last:border-0 cursor-pointer">
                          <div className="text-sm font-bold text-warm-900">{food.name}</div>
                          <div className="text-xs text-warm-400">{food.calories} kcal · P {food.protein ?? 0}g · C {food.carbs ?? 0}g · F {food.fat ?? 0}g</div>
                        </button>
                      ))}
                    </div>
                  )}
                </div>
                <input aria-label={`Ingredient ${idx + 1} quantity`} type="number" value={row.quantity} onChange={(e) => updateRow(idx, { quantity: e.target.value })} min="0.000001" max="100000" step="0.000001" required
                  className="w-20 px-3 py-2 text-base border border-warm-300 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
                <select aria-label={`Ingredient ${idx + 1} unit`} value={row.unit} onChange={(e) => updateRow(idx, { unit: e.target.value })} disabled={!row.food}
                  className="w-20 px-2 py-2 text-base border border-warm-300 rounded-lg text-warm-900 focus:outline-none cursor-pointer">
                  {clinicalRecipeUnits(row.food?.serving_unit ?? "").map((u) => <option key={u} value={u}>{u}</option>)}
                </select>
                <button type="button" onClick={() => setIngredients((prev) => prev.filter((_, i) => i !== idx))}
                  disabled={ingredients.length === 1}
                  className="p-2 rounded-lg text-warm-400 hover:text-red-500 hover:bg-red-50 disabled:opacity-30 cursor-pointer transition-colors">
                  <X className="h-3.5 w-3.5" />
                </button>
              </div>
            ))}
          </div>
        </div>

        </>}

        <div className="flex gap-3 justify-end pb-4">
          <Link href="/food-library"><Button variant="secondary" className="w-auto px-6 py-2.5">Cancel</Button></Link>
          <Button type="submit" variant="primary" loading={saving} className="w-auto px-6 py-2.5">Save Menu Item</Button>
        </div>
      </form>
    </div>
  );
}

const inputCls = "w-full px-3 py-2 text-base bg-white border border-warm-300 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-warm-400";
function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-1.5">{children}</label>;
}
function Required() { return <span className="text-red-500 ml-0.5">*</span>; }
