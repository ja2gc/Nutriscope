"use client";

import React, { use, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Database, ArrowLeft, Plus, X, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { fetchFoodItemById, updateFoodItem, FoodItem } from "@/services/foodLibraryService";

const CATEGORIES = ["protein", "carbs", "vegetable", "fat", "dairy", "fruit"];
const SERVING_UNITS = ["g", "ml", "piece", "cup", "oz", "tbsp", "tsp"];
const COMMON_ALLERGENS = ["milk", "eggs", "fish", "shellfish", "tree nuts", "peanuts", "wheat", "soybeans"];

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
  const [allergens, setAllergens] = useState<string[]>([]);
  const [allergenInput, setAllergenInput] = useState("");

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
        setServingSize(f.serving_size ?? "100");
        setServingUnit(f.serving_unit ?? "g");
        setAllergens(f.allergens ?? []);
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

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      setSaving(true);
      setError(null);
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
      });
      router.push("/food-library");
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <div className="flex items-center justify-center h-64"><Loader2 className="h-6 w-6 animate-spin text-emerald-600" /></div>;
  if (!food && error) return <div className="p-6 bg-red-50 border border-red-100 rounded-2xl text-xs text-red-700 font-bold">{error}</div>;

  return (
    <div className="space-y-6 font-sans max-w-2xl mx-auto">
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span>/</span>
        <Link href="/food-library" className="hover:text-emerald-700 transition-colors">Food Library</Link>
        <span>/</span>
        <span className="text-zinc-650 font-bold truncate max-w-40">{food?.name}</span>
      </div>

      <div className="border-b border-zinc-200 pb-5 flex items-center gap-4">
        <Link href="/food-library" className="p-2 rounded-lg border border-zinc-200 hover:bg-zinc-50 text-zinc-500 transition-colors">
          <ArrowLeft className="h-4 w-4" />
        </Link>
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Database className="h-5 w-5 text-emerald-600" />
            Edit Food Item
          </h2>
          {food?.usda_fdc_id && (
            <p className="text-[10px] font-mono text-zinc-400 mt-0.5">USDA FDC ID: {food.usda_fdc_id} — micronutrients auto-populated on import</p>
          )}
        </div>
      </div>

      {error && <div className="bg-red-50 border border-red-100 p-4 rounded-xl text-xs text-red-700 font-bold">{error}</div>}

      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="bg-white border border-zinc-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Basic Information</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="sm:col-span-2">
              <Label>Name <Required /></Label>
              <input type="text" value={name} onChange={(e) => setName(e.target.value)} className={inputCls} required />
            </div>
            <div>
              <Label>Category</Label>
              <select value={category} onChange={(e) => setCategory(e.target.value)} className={inputCls}>
                <option value="">Select category</option>
                {CATEGORIES.map((c) => <option key={c} value={c}>{c.charAt(0).toUpperCase() + c.slice(1)}</option>)}
              </select>
            </div>
            <div className="flex gap-2">
              <div className="flex-1">
                <Label>Serving Size</Label>
                <input type="number" value={servingSize} onChange={(e) => setServingSize(e.target.value)} min="0" step="0.1" className={inputCls} />
              </div>
              <div className="w-28">
                <Label>Unit</Label>
                <select value={servingUnit} onChange={(e) => setServingUnit(e.target.value)} className={inputCls}>
                  {SERVING_UNITS.map((u) => <option key={u} value={u}>{u}</option>)}
                </select>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white border border-zinc-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Nutritional Values (per serving)</h3>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div><Label>Calories (kcal) <Required /></Label><input type="number" value={calories} onChange={(e) => setCalories(e.target.value)} min="0" step="0.01" className={inputCls} required /></div>
            <div><Label>Protein (g)</Label><input type="number" value={protein} onChange={(e) => setProtein(e.target.value)} min="0" step="0.01" className={inputCls} /></div>
            <div><Label>Carbs (g)</Label><input type="number" value={carbs} onChange={(e) => setCarbs(e.target.value)} min="0" step="0.01" className={inputCls} /></div>
            <div><Label>Fat (g)</Label><input type="number" value={fat} onChange={(e) => setFat(e.target.value)} min="0" step="0.01" className={inputCls} /></div>
          </div>
          {food?.usda_fdc_id && (
            <div className="mt-2 p-3 bg-emerald-50 border border-emerald-100 rounded-xl">
              <p className="text-[10px] font-bold text-emerald-700 uppercase tracking-wider mb-1">Micronutrients (USDA-sourced)</p>
              <div className="flex flex-wrap gap-x-4 gap-y-1">
                {Object.entries(food.micronutrients ?? {}).map(([key, val]) => (
                  <span key={key} className="text-[10px] text-emerald-600 font-mono">{key}: {val}</span>
                ))}
                {Object.keys(food.micronutrients ?? {}).length === 0 && (
                  <span className="text-[10px] text-zinc-400">No micronutrient data</span>
                )}
              </div>
            </div>
          )}
        </div>

        <div className="bg-white border border-zinc-200 rounded-2xl p-6 space-y-4 shadow-sm">
          <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Allergen Flags</h3>
          <div className="flex flex-wrap gap-2">
            {COMMON_ALLERGENS.map((a) => (
              <button key={a} type="button" onClick={() => toggleAllergen(a)}
                className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide border transition-all cursor-pointer ${allergens.includes(a) ? "bg-amber-100 border-amber-300 text-amber-800" : "bg-zinc-50 border-zinc-200 text-zinc-500 hover:border-amber-200 hover:text-amber-700"}`}>
                {a}
              </button>
            ))}
          </div>
          {allergens.filter((a) => !COMMON_ALLERGENS.includes(a)).map((a) => (
            <span key={a} className="inline-flex items-center gap-1 mr-2 px-2 py-0.5 bg-amber-50 border border-amber-200 text-amber-700 text-[10px] font-bold rounded-full">
              {a}
              <button type="button" onClick={() => setAllergens((prev) => prev.filter((x) => x !== a))} className="cursor-pointer"><X className="h-2.5 w-2.5" /></button>
            </span>
          ))}
          <div className="flex gap-2">
            <input type="text" value={allergenInput} onChange={(e) => setAllergenInput(e.target.value)}
              onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addCustomAllergen(); } }}
              placeholder="Add custom allergen..." className={`${inputCls} flex-1 max-w-64`} />
            <button type="button" onClick={addCustomAllergen} className="px-3 py-2 border border-zinc-300 rounded-lg text-zinc-600 hover:bg-zinc-50 cursor-pointer transition-colors">
              <Plus className="h-3.5 w-3.5" />
            </button>
          </div>
        </div>

        <div className="flex gap-3 justify-end pb-4">
          <Link href="/food-library"><Button variant="secondary" className="w-auto px-6 py-2.5">Cancel</Button></Link>
          <Button type="submit" variant="primary" loading={saving} className="w-auto px-6 py-2.5">Save Changes</Button>
        </div>
      </form>
    </div>
  );
}

const inputCls = "w-full px-3 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-zinc-400";
function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1.5">{children}</label>;
}
function Required() { return <span className="text-red-500 ml-0.5">*</span>; }
