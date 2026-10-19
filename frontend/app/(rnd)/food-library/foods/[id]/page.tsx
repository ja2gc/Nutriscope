"use client";

import { numericStepBase } from "@/components/ui/NumericInput";
import React, { use, useEffect, useState } from "react";
import Link from "next/link";
import { useEditorChanges } from "@/hooks/useUnsavedChanges";
import { useRouter } from "next/navigation";
import { Database, ArrowLeft, Plus, X, Loader2, TriangleAlert } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { InfoHint } from "@/components/ui/InfoHint";
import { canonicalClinicalUnit, clinicalIngredientFactor, clinicalRecipeUnits, CLINICAL_BASELINE_UNITS, CLINICAL_UNIT_HINT } from "@/lib/clinicalUnits";
import { RecipeCorrectionsRequiredError, type AffectedRecipe } from "@/services/fsCatalogService";
import { fetchFoodItemById, updateFoodItem, FoodItem } from "@/services/foodLibraryService";

const CATEGORIES = ["protein", "carbs", "vegetable", "fat", "dairy", "fruit"];
const SERVING_UNITS = CLINICAL_BASELINE_UNITS;
const COMMON_ALLERGENS = ["milk", "eggs", "fish", "shellfish", "tree nuts", "peanuts", "wheat", "soybeans"];

const NUTRIENT_GROUPS = {
  Minerals: ['sodium','potassium','phosphate','calcium','iron','magnesium','zinc','copper','manganese','selenium','iodine'],
  Vitamins: ['vitamin_a','vitamin_c','vitamin_d','vitamin_e','vitamin_k','vitamin_b1','vitamin_b2','vitamin_b3','vitamin_b6','vitamin_b12','folate'],
  'Fatty Acids & Other': ['fiber','cholesterol','omega3'],
} as const;

const NUTRIENT_UNITS: Record<string, string> = {
  sodium:'mg', potassium:'mg', phosphate:'mg', calcium:'mg', iron:'mg',
  magnesium:'mg', zinc:'mg', copper:'mg', manganese:'mg', selenium:'mcg', iodine:'mcg',
  vitamin_a:'mcg', vitamin_c:'mg', vitamin_d:'mcg', vitamin_e:'mg', vitamin_k:'mcg',
  vitamin_b1:'mg', vitamin_b2:'mg', vitamin_b3:'mg', vitamin_b6:'mg', vitamin_b12:'mcg', folate:'mcg',
  fiber:'g', cholesterol:'mg', omega3:'g',
};

export default function EditFoodPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();

  const [food, setFood] = useState<FoodItem | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [name, setName] = useState("");
  const [category, setCategory] = useState("");
  const [calories, setCalories] = useState("");
  const [protein, setProtein] = useState("");
  const [carbs, setCarbs] = useState("");
  const [fat, setFat] = useState("");
  const [servingSize, setServingSize] = useState("");
  const [servingUnit, setServingUnit] = useState("g");
  const [affectedRecipes, setAffectedRecipes] = useState<AffectedRecipe[]>([]);
  const [corrections, setCorrections] = useState<Record<string, { quantity: string; unit: string }>>({});
  const [allergens, setAllergens] = useState<string[]>([]);
  const [allergenInput, setAllergenInput] = useState("");
  const [micros, setMicros] = useState<Record<string, string>>({});

  useEffect(() => {
    fetchFoodItemById(id)
      .then((f) => {
        setFood(f);
        setName(f.name);
        setCategory(f.category ?? "");
        setCalories(f.calories ?? "");
        setProtein(f.protein ?? "");
        setCarbs(f.carbs ?? "");
        setFat(f.fat ?? "");
        setServingSize(String(Number(f.serving_size ?? "100")));
        setServingUnit(canonicalClinicalUnit(f.serving_unit ?? ""));
        setAllergens(f.allergens ?? []);
        const microMap: Record<string, string> = {};
        Object.values(NUTRIENT_GROUPS).flat().forEach((key) => {
          microMap[key] = f.micronutrients?.[key] != null ? String(f.micronutrients[key]) : '';
        });
        setMicros(microMap);
      })
      .catch((e: unknown) => setError(e instanceof Error ? e.message : "Failed to load."))
      .finally(() => setLoading(false));
  }, [id]);

  const toggleAllergen = (a: string) =>
    setAllergens((prev) => prev.includes(a) ? prev.filter((x) => x !== a) : [...prev, a]);

  const addCustomAllergen = () => {
    const val = allergenInput.trim().toLowerCase();
    if (val && !allergens.includes(val)) setAllergens((prev) => [...prev, val]);
    setAllergenInput("");
  };

  const changeBaselineUnit = (unit: string) => {
    const converted = clinicalIngredientFactor(Number(servingSize), servingUnit, 1, unit);
    if (converted !== null) {
      const stored = Number(converted.toFixed(6));
      const restored = clinicalIngredientFactor(stored, unit, 1, servingUnit);
      if (stored <= 0 || stored > 10000 || restored === null || Number(restored.toFixed(6)) !== Number(servingSize)) {
        setError("This unit change cannot preserve the measured baseline at six decimals. Choose another compatible unit."); return;
      }
      setServingSize(String(stored));
    }
    setServingUnit(unit);
    setAffectedRecipes([]);
    setCorrections({});
    setError(null);
  };

  const guard = useEditorChanges(JSON.stringify({ name, category, calories, protein, carbs, fat, servingSize, servingUnit, allergens, micros, corrections }), !loading, () => handleSubmit(undefined, false));

  async function handleSubmit(e?: React.FormEvent, navigate = true) {
    e?.preventDefault();
    if (!e && !document.querySelector<HTMLFormElement>("form")?.reportValidity()) return false;
    try {
      setSaving(true);
      setError(null);
      const recipeCorrections = affectedRecipes.map((recipe) => {
        const correction = corrections[`${recipe.recipe_id}:${recipe.line_index}`];
        return { recipe_id: recipe.recipe_id, line_index: recipe.line_index, quantity: Number(correction?.quantity), unit: correction?.unit ?? "" };
      });
      if (recipeCorrections.some((correction) => clinicalIngredientFactor(correction.quantity, correction.unit, Number(servingSize), servingUnit) === null)) {
        setError("Enter a measured quantity and compatible unit for every affected Menu Item."); return false;
      }
      const micronutrients: Record<string, number> = {};
      if (!food?.usda_fdc_id) {
        Object.entries(micros).forEach(([k, v]) => {
          const n = parseFloat(v);
          if (!isNaN(n) && n >= 0) micronutrients[k] = n;
        });
      }
      await updateFoodItem(id, {
        name: name.trim(),
        calories: parseFloat(calories),
        category: category || null,
        protein: protein ? parseFloat(protein) : null,
        carbs: carbs ? parseFloat(carbs) : null,
        fat: fat ? parseFloat(fat) : null,
        serving_size: servingSize ? parseFloat(servingSize) : null,
        serving_unit: servingUnit || null,
        allergens,
        ...(recipeCorrections.length > 0 && { recipe_corrections: recipeCorrections }),
        ...(!food?.usda_fdc_id && Object.keys(micronutrients).length > 0 && { micronutrients }),
      });
      if (navigate) { guard.allowNavigation(); router.push("/food-library"); }
      return true;
    } catch (e: unknown) {
      if (e instanceof RecipeCorrectionsRequiredError) {
        setAffectedRecipes(e.affectedRecipes);
        setCorrections(Object.fromEntries(e.affectedRecipes.map((recipe) => {
          const key = `${recipe.recipe_id}:${recipe.line_index}`;
          return [key, corrections[key] ?? { quantity: "", unit: servingUnit }];
        })));
      }
      setError(e instanceof Error ? e.message : "Failed to save.");
      return false;
    } finally {
      setSaving(false);
    }
  };


  if (loading) return <div className="flex items-center justify-center h-64"><Loader2 className="h-6 w-6 animate-spin text-emerald-600" /></div>;
  if (!food && error) return <div className="p-6 bg-red-50 border border-red-100 rounded-2xl text-sm text-red-700 font-bold">{error}</div>;

  return (
    <div className="space-y-6 font-sans max-w-2xl mx-auto">
      {guard.dialog}
      <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span>/</span>
        <Link href="/food-library" className="hover:text-emerald-700 transition-colors">Food Library</Link>
        <span>/</span>
        <span className="text-zinc-650 font-bold truncate max-w-40">{food?.name}</span>
      </div>

      <div className="border-b border-warm-200 pb-5 flex items-center gap-4">
        <Link href="/food-library" aria-label="Back to Ingredients" className="p-2 rounded-lg border border-warm-200 hover:bg-warm-50 text-warm-500 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </Link>
        <div>
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
            <Database className="h-5 w-5 text-emerald-600" />
            Edit Food Item
          </h2>
          {food?.usda_fdc_id && (
            <p className="text-xs font-mono text-warm-400 mt-0.5">USDA FDC ID: {food.usda_fdc_id} — micronutrients auto-populated on import</p>
          )}
        </div>
      </div>

      {food?.usda_fdc_id && (
        <div className="flex items-start gap-2.5 px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl">
          <TriangleAlert className="h-3.5 w-3.5 text-amber-600 shrink-0 mt-0.5" />
          <p className="text-xs text-amber-800 leading-relaxed">
            Category and allergens below were <span className="font-bold">auto-detected from USDA data</span> — please review and correct them before saving.
          </p>
        </div>
      )}

      {error && <div className="bg-red-50 border border-red-100 p-4 rounded-xl text-sm text-red-700 font-bold">{error}</div>}

      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="bg-white border border-warm-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Basic Information</h3>
          <div className="grid grid-cols-1 items-end sm:grid-cols-2 gap-4">
            <div className="sm:col-span-2">
              <Label>Name <Required /></Label>
              <input aria-label="Name" type="text" value={name} onChange={(e) => setName(e.target.value)} className={inputCls} required />
            </div>
            <div>
              <Label>Category</Label>
              <select aria-label="Category" value={category} onChange={(e) => setCategory(e.target.value)} className={inputCls}>
                <option value="">Select category</option>
                {CATEGORIES.map((c) => <option key={c} value={c}>{c.charAt(0).toUpperCase() + c.slice(1)}</option>)}
              </select>
            </div>
            <div className="flex items-end gap-2">
              <div className="flex-1">
                <Label>Nutrient baseline amount</Label>
                <input aria-label="Nutrient baseline amount" type="number" value={servingSize} onChange={(e) => setServingSize(e.target.value)} min={numericStepBase(servingSize)} max="10000" step="0.1" required className={inputCls} />
              </div>
              <div className="w-28">
                <div className="flex items-center"><Label>Unit</Label><InfoHint label="Ingredient unit families">{CLINICAL_UNIT_HINT}</InfoHint></div>
                <select aria-label="Nutrient baseline unit" value={servingUnit} onChange={(e) => changeBaselineUnit(e.target.value)} className={inputCls}>
                  {[...new Set([...SERVING_UNITS, canonicalClinicalUnit(food?.serving_unit ?? "")].filter(Boolean))].map((u) => <option key={u} value={u}>{u}</option>)}
                </select>
              </div>
            </div>
          </div>
        </div>

        {affectedRecipes.length > 0 && <section className="space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-4" aria-label="Affected Menu Item corrections">
          <h3 className="font-bold">Correct affected Menu Items</h3>
          {affectedRecipes.map((recipe) => {
            const key = `${recipe.recipe_id}:${recipe.line_index}`;
            const correction = corrections[key] ?? { quantity: "", unit: servingUnit };
            return <div key={key} className="space-y-2">
              <p><Link href={`/food-library/recipes/${recipe.recipe_id}`} target="_blank" className="font-semibold underline">{recipe.recipe_name}</Link>: {recipe.quantity} {recipe.unit}</p>
              <div className="flex items-end gap-2">
                <input aria-label={`${recipe.recipe_name} correction quantity ${recipe.line_index + 1}`} type="number" min={numericStepBase(correction.quantity, 0.000001)} max="100000" step="0.1" value={correction.quantity} onChange={(event) => setCorrections((current) => ({ ...current, [key]: { ...correction, quantity: event.target.value } }))} className={inputCls} required />
                <select aria-label={`${recipe.recipe_name} correction unit ${recipe.line_index + 1}`} value={correction.unit} onChange={(event) => setCorrections((current) => ({ ...current, [key]: { ...correction, unit: event.target.value } }))} className={inputCls}>
                  {clinicalRecipeUnits(servingUnit).map((unit) => <option key={unit} value={unit}>{unit}</option>)}
                </select>
              </div>
            </div>;
          })}
        </section>}

        <div className="bg-white border border-warm-200 rounded-2xl p-6 space-y-5 shadow-sm">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Nutritional Values (per serving)</h3>
            {food?.usda_fdc_id && (
              <span className="text-xs font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full uppercase tracking-wider">USDA-sourced</span>
            )}
          </div>

          {/* Macros — always editable */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div><Label>Calories (kcal) <Required /></Label><input aria-label="Calories (kcal)" type="number" value={calories} onChange={(e) => setCalories(e.target.value)} min={numericStepBase(calories)} step="0.1" className={inputCls} required /></div>
            <div><Label>Protein (g)</Label><input aria-label="Protein (g)" type="number" value={protein} onChange={(e) => setProtein(e.target.value)} min={numericStepBase(protein)} step="0.1" className={inputCls} /></div>
            <div><Label>Carbs (g)</Label><input aria-label="Carbs (g)" type="number" value={carbs} onChange={(e) => setCarbs(e.target.value)} min={numericStepBase(carbs)} step="0.1" className={inputCls} /></div>
            <div><Label>Fat (g)</Label><input aria-label="Fat (g)" type="number" value={fat} onChange={(e) => setFat(e.target.value)} min={numericStepBase(fat)} step="0.1" className={inputCls} /></div>
          </div>

          {/* Micronutrients — grouped */}
          {Object.entries(NUTRIENT_GROUPS).map(([group, keys]) => (
            <div key={group}>
              <p className="text-xs font-extrabold text-warm-400 uppercase tracking-widest mb-2">{group}</p>
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                {(keys as readonly string[]).map((key) => (
                  <div key={key}>
                    <label className="block text-xs font-bold text-warm-400 uppercase tracking-wider mb-1">
                      {key.replace(/_/g, ' ')} <span className="normal-case font-normal">({NUTRIENT_UNITS[key]})</span>
                    </label>
                    {food?.usda_fdc_id ? (
                      <p className="text-base font-semibold text-warm-700 px-3 py-2 bg-warm-50 border border-warm-200 rounded-lg">
                        {micros[key] !== '' ? `${micros[key]} ${NUTRIENT_UNITS[key]}` : <span className="text-warm-300">—</span>}
                      </p>
                    ) : (
                      <input
                        aria-label={`${key.replace(/_/g, " ")} (${NUTRIENT_UNITS[key]})`} type="number" min={numericStepBase(micros[key] ?? "")} step="0.1"
                        value={micros[key] ?? ''}
                        onChange={(e) => setMicros((prev) => ({ ...prev, [key]: e.target.value }))}
                        className={inputCls}
                      />
                    )}
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>

        <div className="bg-white border border-warm-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Allergen Flags</h3>
          <div className="flex flex-wrap gap-2">
            {COMMON_ALLERGENS.map((a) => (
              <button key={a} type="button" onClick={() => toggleAllergen(a)}
                className={`px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide border transition-all cursor-pointer ${allergens.includes(a) ? "bg-amber-100 border-amber-300 text-amber-800" : "bg-warm-50 border-warm-200 text-warm-500 hover:border-amber-200 hover:text-amber-700"}`}>
                {a}
              </button>
            ))}
          </div>
          {allergens.filter((a) => !COMMON_ALLERGENS.includes(a)).map((a) => (
            <span key={a} className="inline-flex items-center gap-1 mr-2 px-2 py-0.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-bold rounded-full">
              {a}
              <button type="button" aria-label={`Remove allergen ${a}`} onClick={() => setAllergens((prev) => prev.filter((x) => x !== a))} className="cursor-pointer"><X className="h-2.5 w-2.5" /></button>
            </span>
          ))}
          <div className="flex items-end gap-2">
            <input aria-label="Custom allergen" type="text" value={allergenInput} onChange={(e) => setAllergenInput(e.target.value)}
              onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addCustomAllergen(); } }}
              className={`${inputCls} flex-1 max-w-64`} />
            <button type="button" aria-label="Add custom allergen" onClick={addCustomAllergen} className="px-3 py-2 border border-warm-300 rounded-lg text-warm-600 hover:bg-warm-50 cursor-pointer transition-colors">
              <Plus className="h-3.5 w-3.5" />
            </button>
          </div>
        </div>

        <div className="flex gap-3 justify-end pb-4">
          <Button type="button" variant="secondary" onClick={() => guard.requestLeave(() => router.push("/food-library"))} className="w-auto px-6 py-2.5">Cancel</Button>
          <Button type="submit" variant="primary" loading={saving} className="w-auto px-6 py-2.5">Save Changes</Button>
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
