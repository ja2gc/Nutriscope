"use client";

import React, { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import {
  Database, Plus, Search, Download, Trash2, Pencil,
  CookingPot, X, Loader2,
  FlaskConical, TriangleAlert,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Pagination } from "@/components/ui/Pagination";
import {
  fetchFoodItems, fetchRecipes, deleteFoodItem, deleteRecipe,
  searchUsda, importUsdaFood,
  FoodItem, Recipe, UsdaSearchResult, type PaginatedResponse,
} from "@/services/foodLibraryService";

const FOOD_CATEGORIES = ["all", "protein", "carbs", "vegetable", "fat", "dairy", "fruit"];
const RECIPE_CATEGORIES = ["all", "breakfast", "lunch", "dinner", "snack"];

const NUTRIENT_GROUPS = {
  Minerals: ["sodium","potassium","phosphate","calcium","iron","magnesium","zinc","copper","manganese","selenium","iodine"],
  Vitamins: ["vitamin_a","vitamin_c","vitamin_d","vitamin_e","vitamin_k","vitamin_b1","vitamin_b2","vitamin_b3","vitamin_b6","vitamin_b12","folate"],
  "Fatty Acids": ["fiber","cholesterol","omega3"],
} as const;

const NUTRIENT_UNITS: Record<string, string> = {
  sodium:"mg", potassium:"mg", phosphate:"mg", calcium:"mg", iron:"mg",
  magnesium:"mg", zinc:"mg", copper:"mg", manganese:"mg", selenium:"mcg", iodine:"mcg",
  vitamin_a:"mcg", vitamin_c:"mg", vitamin_d:"mcg", vitamin_e:"mg", vitamin_k:"mcg",
  vitamin_b1:"mg", vitamin_b2:"mg", vitamin_b3:"mg", vitamin_b6:"mg", vitamin_b12:"mcg", folate:"mcg",
  fiber:"g", cholesterol:"mg", omega3:"g",
};

type Tab = "foods" | "recipes";

// ─── USDA Import Modal ────────────────────────────────────────────────────────

function UsdaImportModal({ onClose, onImported }: {
  onClose: () => void;
  onImported: (food: FoodItem) => void;
}) {
  const [query, setQuery]         = useState("");
  const [results, setResults]     = useState<UsdaSearchResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [importing, setImporting] = useState<number | null>(null);
  const [imported, setImported]   = useState<string | null>(null);
  const [error, setError]         = useState<string | null>(null);
  const debounceRef               = useRef<ReturnType<typeof setTimeout> | null>(null);

  const runSearch = useCallback(async (q: string) => {
    if (q.trim().length < 2) { setResults([]); return; }
    setSearching(true);
    setError(null);
    try {
      setResults(await searchUsda(q.trim()));
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Search failed.");
    } finally {
      setSearching(false);
    }
  }, []);

  const handleQueryChange = (val: string) => {
    setQuery(val);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => runSearch(val), 420);
  };

  const handleImport = async (item: UsdaSearchResult) => {
    setImporting(item.fdc_id);
    setError(null);
    try {
      const food = await importUsdaFood(item.fdc_id);
      setImported(item.name);
      onImported(food);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Import failed.");
    } finally {
      setImporting(null);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col border border-zinc-200 overflow-hidden">

        {/* Header */}
        <div className="flex items-start justify-between px-6 py-5 border-b border-zinc-100 bg-zinc-50/60">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-emerald-100 rounded-xl">
              <FlaskConical className="h-4 w-4 text-emerald-700" />
            </div>
            <div>
              <h3 className="text-sm font-extrabold text-zinc-900">Import from USDA FoodData Central</h3>
              <p className="text-[10px] text-zinc-400 mt-0.5">Foundation · SR Legacy · Survey (FNDDS) — all nutrients auto-extracted</p>
            </div>
          </div>
          <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-zinc-200 text-zinc-400 hover:text-zinc-700 cursor-pointer transition-colors mt-0.5">
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Search */}
        <div className="px-6 py-4 border-b border-zinc-100">
          <div className="relative">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400" />
            {searching && <Loader2 className="absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-emerald-500 animate-spin" />}
            <input
              type="text"
              value={query}
              onChange={(e) => handleQueryChange(e.target.value)}
              placeholder="Type to search — e.g. chicken breast, brown rice, bangus..."
              className="w-full pl-10 pr-10 py-2.5 text-sm bg-white border border-zinc-300 rounded-xl text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all placeholder:text-zinc-400"
              autoFocus
            />
          </div>
          {error && (
            <div className="flex items-center gap-2 mt-2.5 text-[11px] text-red-600 font-semibold">
              <TriangleAlert className="h-3.5 w-3.5 shrink-0" /> {error}
            </div>
          )}
        </div>

        {/* Review note — shown after successful import */}
        {imported && (
          <div className="mx-6 mt-4 flex items-start gap-2.5 px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl">
            <TriangleAlert className="h-3.5 w-3.5 text-amber-600 shrink-0 mt-0.5" />
            <p className="text-[11px] text-amber-800 leading-relaxed">
              <span className="font-bold">&quot;{imported}&quot;</span> imported. Category and allergens were auto-detected from USDA data —{" "}
              <span className="font-bold">please review them in the edit page before use.</span>
            </p>
          </div>
        )}

        {/* Results */}
        <div className="flex-1 overflow-y-auto px-6 py-4 space-y-2">
          {results.length === 0 && !searching && (
            <div className="text-center py-12 select-none">
              <FlaskConical className="h-8 w-8 text-zinc-200 mx-auto mb-3" />
              <p className="text-xs text-zinc-400">
                {query.length >= 2 ? "No results found." : "Start typing to search the USDA database."}
              </p>
            </div>
          )}

          {results.map((item) => {
            return (
              <div key={item.fdc_id}
                className="flex items-center gap-4 p-3.5 rounded-xl border border-zinc-100 hover:border-emerald-200 hover:bg-emerald-50/30 transition-all group">
                <div className="flex-1 min-w-0 space-y-1">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-xs font-bold text-zinc-900 leading-tight">{item.name}</span>
                    {item.data_type && (
                      <span className="text-[9px] text-zinc-400 font-semibold uppercase tracking-wide">{item.data_type}</span>
                    )}
                  </div>
                  <div className="text-[9px] text-zinc-500 font-medium">
                    <span>{Math.round(item.calories * 10) / 10}kcal</span>
                    {item.protein != null && <span className="ml-1.5">· P {Math.round(item.protein * 10) / 10}g</span>}
                    {item.carbs != null && <span className="ml-1.5">· C {Math.round(item.carbs * 10) / 10}g</span>}
                    {item.fat != null && <span className="ml-1.5">· F {Math.round(item.fat * 10) / 10}g</span>}
                    {('water_g' in item) && (item as { water_g?: number | null }).water_g != null && (
                      <span className="ml-1.5">· W {Math.round(Number((item as { water_g: number }).water_g) * 10) / 10}g</span>
                    )}
                    {item.food_category && <span className="ml-1.5 text-zinc-400">· {item.food_category}</span>}
                  </div>
                </div>
                <button
                  onClick={() => handleImport(item)}
                  disabled={importing === item.fdc_id}
                  className="shrink-0 flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 disabled:opacity-50 text-white text-[10px] font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer"
                >
                  {importing === item.fdc_id
                    ? <Loader2 className="h-3 w-3 animate-spin" />
                    : <Download className="h-3 w-3" />}
                  Import
                </button>
              </div>
            );
          })}
        </div>

        <div className="px-6 py-3 border-t border-zinc-100 bg-zinc-50/60">
          <p className="text-[10px] text-zinc-400 text-center">
            FDC ID · Foundation Foods · SR Legacy · Survey (FNDDS) — Branded foods excluded
          </p>
        </div>
      </div>
    </div>
  );
}

// ─── Food Micronutrient Popup ─────────────────────────────────────────────────

function FoodMicrosPopup({ food, onClose }: { food: FoodItem; onClose: () => void }) {
  const micros = food.micronutrients ?? {};
  const hasMicros = Object.keys(micros).length > 0;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4" onClick={onClose}>
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-xl border border-zinc-200 overflow-hidden"
        onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-100 bg-zinc-50/60">
          <div>
            <h3 className="text-sm font-extrabold text-zinc-900 truncate max-w-xs">{food.name}</h3>
            <p className="text-[10px] text-zinc-400 mt-0.5">
              Full nutrient profile · per {food.serving_size ?? 100}{food.serving_unit ?? 'g'} serving
              {food.usda_fdc_id && <span className="ml-2 text-emerald-600 font-semibold">· USDA FDC #{food.usda_fdc_id}</span>}
            </p>
          </div>
          <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-zinc-200 text-zinc-400 cursor-pointer transition-colors">
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Macros bar */}
        <div className="flex gap-6 px-6 py-4 bg-gradient-to-r from-zinc-50 to-white border-b border-zinc-100">
          <MacroStat label="Calories" value={food.calories}        unit="kcal" color="text-emerald-700" />
          <MacroStat label="Protein"  value={food.protein ?? "—"}  unit="g"    color="text-rose-700" />
          <MacroStat label="Carbs"    value={food.carbs ?? "—"}    unit="g"    color="text-amber-700" />
          <MacroStat label="Fat"      value={food.fat ?? "—"}      unit="g"    color="text-violet-700" />
          {food.water_g != null && (
            <MacroStat label="Water" value={String(food.water_g)} unit="g" color="text-sky-700" />
          )}
        </div>

        {/* Micronutrients */}
        <div className="px-6 py-4 max-h-72 overflow-y-auto space-y-4">
          {!hasMicros ? (
            <p className="text-xs text-zinc-400 text-center py-6">
              No micronutrient data — import from USDA to auto-populate all micros.
            </p>
          ) : (
            Object.entries(NUTRIENT_GROUPS).map(([group, keys]) => {
              const groupKeys = (keys as readonly string[]).filter((k) => micros[k] != null);
              if (groupKeys.length === 0) return null;
              return (
                <div key={group}>
                  <p className="text-[9px] font-extrabold text-zinc-400 uppercase tracking-widest mb-2">{group}</p>
                  <div className="grid grid-cols-3 sm:grid-cols-4 gap-2">
                    {groupKeys.map((key) => (
                      <div key={key} className="flex flex-col px-2.5 py-2 bg-zinc-50 border border-zinc-100 rounded-lg">
                        <span className="text-[9px] text-zinc-400 uppercase tracking-wide">{key.replace(/_/g, " ")}</span>
                        <span className="text-xs font-bold text-zinc-800 mt-0.5">
                          {micros[key]} <span className="text-[9px] font-normal text-zinc-400">{NUTRIENT_UNITS[key]}</span>
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })
          )}
        </div>
      </div>
    </div>
  );
}

// ─── Recipe Micronutrient Popup ───────────────────────────────────────────────

function RecipeMicrosPopup({ recipe, onClose }: { recipe: Recipe; onClose: () => void }) {
  const micros = recipe.micronutrients ?? {};
  const hasMicros = Object.keys(micros).length > 0;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4" onClick={onClose}>
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-xl border border-zinc-200 overflow-hidden"
        onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-100 bg-zinc-50/60">
          <div>
            <h3 className="text-sm font-extrabold text-zinc-900 truncate max-w-xs">{recipe.name}</h3>
            <p className="text-[10px] text-zinc-400 mt-0.5">Full nutrient profile · per recipe total</p>
          </div>
          <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-zinc-200 text-zinc-400 cursor-pointer transition-colors">
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Macros bar */}
        <div className="flex gap-6 px-6 py-4 bg-gradient-to-r from-zinc-50 to-white border-b border-zinc-100">
          <MacroStat label="Calories" value={recipe.total_calories ?? "—"} unit="kcal" color="text-emerald-700" />
          <MacroStat label="Protein"  value={recipe.total_protein  ?? "—"} unit="g"    color="text-rose-700" />
          <MacroStat label="Carbs"    value={recipe.total_carbs    ?? "—"} unit="g"    color="text-amber-700" />
          <MacroStat label="Fat"      value={recipe.total_fat      ?? "—"} unit="g"    color="text-violet-700" />
          {recipe.servings && <MacroStat label="Servings" value={recipe.servings} unit="" color="text-zinc-700" />}
        </div>

        {/* Micronutrients */}
        <div className="px-6 py-4 max-h-72 overflow-y-auto space-y-4">
          {!hasMicros ? (
            <p className="text-xs text-zinc-400 text-center py-6">No micronutrient data — add USDA-sourced ingredients to populate.</p>
          ) : (
            Object.entries(NUTRIENT_GROUPS).map(([group, keys]) => {
              const groupKeys = (keys as readonly string[]).filter((k) => micros[k] != null);
              if (groupKeys.length === 0) return null;
              return (
                <div key={group}>
                  <p className="text-[9px] font-extrabold text-zinc-400 uppercase tracking-widest mb-2">{group}</p>
                  <div className="grid grid-cols-3 sm:grid-cols-4 gap-2">
                    {groupKeys.map((key) => (
                      <div key={key} className="flex flex-col px-2.5 py-2 bg-zinc-50 border border-zinc-100 rounded-lg">
                        <span className="text-[9px] text-zinc-400 uppercase tracking-wide">{key.replace(/_/g, " ")}</span>
                        <span className="text-xs font-bold text-zinc-800 mt-0.5">{micros[key]} <span className="text-[9px] font-normal text-zinc-400">{NUTRIENT_UNITS[key]}</span></span>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })
          )}
        </div>
      </div>
    </div>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function FoodLibraryPage() {
  const [activeTab, setActiveTab]       = useState<Tab>("foods");
  const [foods, setFoods]               = useState<FoodItem[]>([]);
  const [foodSearch, setFoodSearch]     = useState("");
  const [foodCategory, setFoodCategory] = useState("all");
  const [foodPage, setFoodPage]         = useState(1);
  const [foodMeta, setFoodMeta]         = useState<PaginatedResponse<FoodItem>["meta"] | null>(null);
  const [foodLoading, setFoodLoading]   = useState(true);
  const [foodError, setFoodError]       = useState<string | null>(null);

  const [recipes, setRecipes]               = useState<Recipe[]>([]);
  const [recipeSearch, setRecipeSearch]     = useState("");
  const [recipeCategory, setRecipeCategory] = useState("all");
  const [recipePage, setRecipePage]         = useState(1);
  const [recipeMeta, setRecipeMeta]         = useState<PaginatedResponse<Recipe>["meta"] | null>(null);
  const [recipeLoading, setRecipeLoading]   = useState(false);
  const [recipeError, setRecipeError]       = useState<string | null>(null);

  const [showUsda, setShowUsda]           = useState(false);
  const [microsFood, setMicrosFood]       = useState<FoodItem | null>(null);
  const [microsRecipe, setMicrosRecipe]   = useState<Recipe | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState<{ type: "food" | "recipe"; id: number; name: string } | null>(null);
  const [deleting, setDeleting]           = useState(false);

  const loadFoods = useCallback(async () => {
    try {
      setFoodLoading(true);
      setFoodError(null);
      const res = await fetchFoodItems(foodSearch, foodCategory, foodPage);
      setFoods(res.data);
      setFoodMeta(res.meta);
    } catch (e: unknown) {
      setFoodError(e instanceof Error ? e.message : "Failed to load foods.");
    } finally {
      setFoodLoading(false);
    }
  }, [foodSearch, foodCategory, foodPage]);

  const loadRecipes = useCallback(async () => {
    try {
      setRecipeLoading(true);
      setRecipeError(null);
      const res = await fetchRecipes(recipeSearch, recipeCategory, recipePage);
      setRecipes(res.data);
      setRecipeMeta(res.meta);
    } catch (e: unknown) {
      setRecipeError(e instanceof Error ? e.message : "Failed to load recipes.");
    } finally {
      setRecipeLoading(false);
    }
  }, [recipeSearch, recipeCategory, recipePage]);

  useEffect(() => {
    const t = window.setTimeout(() => void loadFoods(), 250);
    return () => window.clearTimeout(t);
  }, [loadFoods]);

  useEffect(() => {
    if (activeTab === "recipes") {
      const t = window.setTimeout(() => void loadRecipes(), 250);
      return () => window.clearTimeout(t);
    }
  }, [activeTab, loadRecipes]);

  const handleDeleteConfirm = async () => {
    if (!deleteConfirm) return;
    try {
      setDeleting(true);
      if (deleteConfirm.type === "food") {
        await deleteFoodItem(deleteConfirm.id);
        await loadFoods();
      } else {
        await deleteRecipe(deleteConfirm.id);
        await loadRecipes();
      }
      setDeleteConfirm(null);
    } catch (e: unknown) {
      alert(e instanceof Error ? e.message : "Delete failed.");
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span className="text-zinc-300">/</span>
        <span className="font-bold text-zinc-700">Food Library</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <div className="p-1.5 bg-emerald-100 rounded-lg">
              <Database className="h-4 w-4 text-emerald-700" />
            </div>
            Food Library
          </h2>
          <p className="text-xs text-zinc-500 mt-1.5 select-none">
            Clinical food &amp; recipe reference — macros, micros, and allergens for nutrition care.
          </p>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          {activeTab === "foods" ? (
            <>
              <Button variant="secondary" onClick={() => setShowUsda(true)} className="w-auto px-4 py-2.5 flex items-center gap-2 text-xs">
                <FlaskConical className="h-3.5 w-3.5" />
                Import from USDA
              </Button>
              <Link href="/food-library/foods/new">
                <Button variant="primary" className="w-auto px-4 py-2.5 flex items-center gap-2 text-xs">
                  <Plus className="h-3.5 w-3.5" />
                  Add Food
                </Button>
              </Link>
            </>
          ) : (
            <Link href="/food-library/recipes/new">
              <Button variant="primary" className="w-auto px-4 py-2.5 flex items-center gap-2 text-xs">
                <Plus className="h-3.5 w-3.5" />
                Create Recipe
              </Button>
            </Link>
          )}
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-zinc-200">
        {(["foods", "recipes"] as Tab[]).map((tab) => (
          <button key={tab} onClick={() => setActiveTab(tab)}
            className={`px-5 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all cursor-pointer -mb-px ${
              activeTab === tab
                ? "border-emerald-600 text-emerald-700"
                : "border-transparent text-zinc-400 hover:text-zinc-700 hover:border-zinc-300"
            }`}>
            {tab === "foods"
              ? <span className="flex items-center gap-1.5"><Database className="h-3.5 w-3.5" /> Foods</span>
              : <span className="flex items-center gap-1.5"><CookingPot className="h-3.5 w-3.5" /> Recipes</span>}
          </button>
        ))}
      </div>

      {/* ── Foods Tab ── */}
      {activeTab === "foods" && (
        <div className="space-y-4">
          {/* Filters */}
          <div className="flex flex-col sm:flex-row items-center gap-3">
            <div className="relative w-full sm:flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
              <input type="text" placeholder="Search by food name..." value={foodSearch}
                onChange={(e) => { setFoodSearch(e.target.value); setFoodPage(1); }}
                className="w-full pl-9 pr-4 py-2 text-sm bg-white border border-zinc-200 rounded-xl text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all placeholder:text-zinc-400 shadow-sm" />
            </div>
            <div className="flex gap-1.5 flex-wrap">
              {FOOD_CATEGORIES.map((c) => (
                <button key={c} onClick={() => { setFoodCategory(c); setFoodPage(1); }}
                  className={`min-w-[64px] px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide rounded-lg border transition-all cursor-pointer text-center ${
                    foodCategory === c
                      ? "bg-zinc-900 text-white border-zinc-900"
                      : "bg-white text-zinc-500 border-zinc-200 hover:border-zinc-300"
                  }`}>
                  {c === "all" ? "All" : c}
                </button>
              ))}
            </div>
          </div>

          {foodError && <ErrorBanner message={foodError} />}

          <div className="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-sm">
            {foodLoading ? <LoadingSkeleton /> : foods.length === 0 ? (
              <EmptyState icon={<Database className="h-8 w-8 text-emerald-400" />} title="No Foods Found"
                message="Add foods manually or import from the USDA FoodData Central database." />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse min-w-[480px]">
                  <thead>
                    <tr className="border-b border-zinc-100 select-none">
                      <Th>Food</Th>
                      <Th>Category</Th>
                      <Th>Macros</Th>
                      <Th>Micros</Th>
                      <Th>Allergens</Th>
                      <Th right>Actions</Th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-50">
                    {foods.map((food) => (
                      <tr key={food.id} className="hover:bg-zinc-50/60 transition-colors group">
                        <td className="px-5 py-3.5 min-w-[180px]">
                          <div className="text-xs font-bold text-zinc-900 leading-snug">{food.name}</div>
                          {food.usda_fdc_id && (
                            <div className="flex items-center gap-1 mt-1">
                              <FlaskConical className="h-2.5 w-2.5 text-emerald-500" />
                              <span className="text-[9px] font-mono text-zinc-400">USDA #{food.usda_fdc_id}</span>
                            </div>
                          )}
                        </td>
                        <td className="px-5 py-3.5">
                          {food.category
                            ? <span className="text-[10px] text-zinc-600 capitalize font-medium">{food.category}</span>
                            : <span className="text-zinc-300 text-[10px]">—</span>
                          }
                        </td>
                        <td className="px-5 py-3.5">
                          <div className="text-[10px] text-zinc-600 font-medium">
                            <span>{Math.round(parseFloat(food.calories))}kcal</span>
                            {food.protein && <span className="ml-1.5">· P {Math.round(parseFloat(food.protein))}g</span>}
                            {food.carbs && <span className="ml-1.5">· C {Math.round(parseFloat(food.carbs))}g</span>}
                            {food.fat && <span className="ml-1.5">· F {Math.round(parseFloat(food.fat))}g</span>}
                            {food.water_g != null && <span className="ml-1.5">· W {Math.round(Number(food.water_g))}g</span>}
                          </div>
                        </td>
                        <td className="px-5 py-3.5">
                          {(() => {
                            const hasMicros = Object.keys(food.micronutrients ?? {}).length > 0;
                            return (
                              <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setMicrosFood(food)}
                              >
                                <FlaskConical className={`h-3 w-3 ${hasMicros ? "text-blue-600" : "text-zinc-400"}`} />
                                Micros
                              </Button>
                            );
                          })()}
                        </td>
                        <td className="px-5 py-3.5">
                          {food.allergens.length > 0
                            ? <span className="text-[10px] text-zinc-600" title={food.allergens.join(", ")}>{food.allergens.join(", ")}</span>
                            : <span className="text-zinc-300 text-[10px]">None</span>
                          }
                        </td>
                        <td className="px-5 py-3.5 text-right">
                          <div className="flex items-center justify-end gap-1">
                            <Link href={`/food-library/foods/${food.id}`}
                              className="p-1.5 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-zinc-100 text-zinc-400 hover:text-zinc-800 transition-all" title="Edit">
                              <Pencil className="h-3.5 w-3.5" />
                            </Link>
                            <button onClick={() => setDeleteConfirm({ type: "food", id: food.id, name: food.name })}
                              className="p-1.5 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-red-50 text-zinc-400 hover:text-red-600 transition-all cursor-pointer" title="Delete">
                              <Trash2 className="h-3.5 w-3.5" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            <Pagination meta={foodMeta} page={foodPage} onPageChange={setFoodPage} />
          </div>
        </div>
      )}

      {/* ── Recipes Tab ── */}
      {activeTab === "recipes" && (
        <div className="space-y-4">
          {/* Filters */}
          <div className="flex flex-col sm:flex-row items-center gap-3">
            <div className="relative w-full sm:flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
              <input type="text" placeholder="Search by recipe name..." value={recipeSearch}
                onChange={(e) => { setRecipeSearch(e.target.value); setRecipePage(1); }}
                className="w-full pl-9 pr-4 py-2 text-sm bg-white border border-zinc-200 rounded-xl text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all placeholder:text-zinc-400 shadow-sm" />
            </div>
            <div className="flex gap-1.5 flex-wrap">
              {RECIPE_CATEGORIES.map((c) => (
                <button key={c} onClick={() => { setRecipeCategory(c); setRecipePage(1); }}
                  className={`min-w-[64px] px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide rounded-lg border transition-all cursor-pointer text-center ${
                    recipeCategory === c
                      ? "bg-zinc-900 text-white border-zinc-900"
                      : "bg-white text-zinc-500 border-zinc-200 hover:border-zinc-300"
                  }`}>
                  {c === "all" ? "All" : c}
                </button>
              ))}
            </div>
          </div>

          {recipeError && <ErrorBanner message={recipeError} />}

          <div className="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-sm">
            {recipeLoading ? <LoadingSkeleton /> : recipes.length === 0 ? (
              <EmptyState icon={<CookingPot className="h-8 w-8 text-emerald-400" />} title="No Recipes Found"
                message="Build clinical recipes from the foods in your library." />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse min-w-[480px]">
                  <thead>
                    <tr className="border-b border-zinc-100 select-none">
                      <Th>Recipe</Th>
                      <Th>Category</Th>
                      <Th>Macros</Th>
                      <Th>Micros</Th>
                      <Th right>Actions</Th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-50">
                    {recipes.map((recipe) => {
                      const hasMicros = Object.keys(recipe.micronutrients ?? {}).length > 0;
                      return (
                        <tr key={recipe.id} className="hover:bg-zinc-50/60 transition-colors group">
                          <td className="px-5 py-3.5 min-w-[180px]">
                            <div className="text-xs font-bold text-zinc-900 leading-snug">{recipe.name}</div>
                            {recipe.servings && (
                              <div className="text-[10px] text-zinc-400 mt-0.5">{recipe.servings} serving{recipe.servings !== 1 ? "s" : ""}</div>
                            )}
                          </td>
                          <td className="px-5 py-3.5">
                            {recipe.category
                              ? <span className="text-[10px] text-zinc-600 capitalize font-medium">{recipe.category}</span>
                              : <span className="text-zinc-300 text-[10px]">—</span>
                            }
                          </td>
                          <td className="px-5 py-3.5">
                            <div className="text-[10px] text-zinc-600 font-medium">
                              {recipe.total_calories && <span>{Math.round(parseFloat(recipe.total_calories))}kcal</span>}
                              {recipe.total_protein && <span className="ml-1.5">· P {Math.round(parseFloat(recipe.total_protein))}g</span>}
                              {recipe.total_carbs && <span className="ml-1.5">· C {Math.round(parseFloat(recipe.total_carbs))}g</span>}
                              {recipe.total_fat && <span className="ml-1.5">· F {Math.round(parseFloat(recipe.total_fat))}g</span>}
                            </div>
                          </td>
                          <td className="px-5 py-3.5">
                            <Button
                              variant="secondary"
                              size="sm"
                              onClick={() => setMicrosRecipe(recipe)}
                              title={hasMicros ? "View micronutrients" : "No micronutrient data"}
                            >
                              <FlaskConical className={`h-3 w-3 ${hasMicros ? "text-blue-600" : "text-zinc-400"}`} />
                              Micros
                            </Button>
                          </td>
                          <td className="px-5 py-3.5 text-right">
                            <div className="flex items-center justify-end gap-1">
                              <Link href={`/food-library/recipes/${recipe.id}`}
                                className="p-1.5 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-zinc-100 text-zinc-400 hover:text-zinc-800 transition-all" title="Edit">
                                <Pencil className="h-3.5 w-3.5" />
                              </Link>
                              <button onClick={() => setDeleteConfirm({ type: "recipe", id: recipe.id, name: recipe.name })}
                                className="p-1.5 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-red-50 text-zinc-400 hover:text-red-600 transition-all cursor-pointer" title="Delete">
                                <Trash2 className="h-3.5 w-3.5" />
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
            <Pagination meta={recipeMeta} page={recipePage} onPageChange={setRecipePage} />
          </div>
        </div>
      )}

      {/* Modals */}
      {showUsda && (
        <UsdaImportModal
          onClose={() => setShowUsda(false)}
          onImported={() => { void loadFoods(); }}
        />
      )}

      {microsFood && (
        <FoodMicrosPopup food={microsFood} onClose={() => setMicrosFood(null)} />
      )}

      {microsRecipe && (
        <RecipeMicrosPopup recipe={microsRecipe} onClose={() => setMicrosRecipe(null)} />
      )}

      {deleteConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm border border-zinc-200 p-6 space-y-4">
            <h3 className="text-sm font-extrabold text-zinc-900">Confirm Delete</h3>
            <p className="text-xs text-zinc-600 leading-relaxed">
              Are you sure you want to delete{" "}
              <span className="font-bold text-zinc-900">&quot;{deleteConfirm.name}&quot;</span>? This cannot be undone.
            </p>
            <div className="flex gap-2 pt-1">
              <Button variant="secondary" onClick={() => setDeleteConfirm(null)} className="flex-1 py-2">Cancel</Button>
              <Button variant="danger" onClick={handleDeleteConfirm} loading={deleting} className="flex-1 py-2">Delete</Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ─── Shared sub-components ────────────────────────────────────────────────────

function MacroStat({ label, value, unit, color }: { label: string; value: string | number; unit: string; color: string }) {
  return (
    <div>
      <p className={`text-[9px] font-bold uppercase tracking-wider ${color}`}>{label}</p>
      <p className="text-sm font-extrabold text-zinc-900">
        {value}<span className="text-[9px] font-normal text-zinc-500 ml-0.5">{unit}</span>
      </p>
    </div>
  );
}

function Th({ children, right }: { children: React.ReactNode; right?: boolean }) {
  return (
    <th className={`px-5 py-3.5 text-[9px] font-extrabold text-zinc-400 uppercase tracking-widest ${right ? "text-right" : ""}`}>
      {children}
    </th>
  );
}

function ErrorBanner({ message }: { message: string }) {
  return (
    <div className="flex items-center gap-3 bg-red-50 border border-red-100 p-4 rounded-xl">
      <TriangleAlert className="h-4 w-4 text-red-500 shrink-0" />
      <p className="text-xs text-red-700 font-semibold">{message}</p>
    </div>
  );
}

function EmptyState({ icon, title, message }: { icon: React.ReactNode; title: string; message: string }) {
  return (
    <div className="p-14 text-center select-none">
      <div className="p-3.5 bg-zinc-50 border border-zinc-100 rounded-2xl w-fit mx-auto">{icon}</div>
      <h3 className="text-sm font-bold text-zinc-800 mt-4">{title}</h3>
      <p className="text-xs text-zinc-400 mt-1.5 max-w-sm mx-auto leading-relaxed">{message}</p>
    </div>
  );
}

function LoadingSkeleton() {
  return (
    <div className="p-8 space-y-3">
      {[1, 2, 3, 4, 5].map((i) => (
        <div key={i} className="flex gap-4 items-center h-10">
          <div className="flex-1 bg-zinc-100 rounded-lg h-6 animate-pulse" style={{ animationDelay: `${i * 60}ms` }} />
          <div className="w-20 bg-zinc-100 rounded-lg h-6 animate-pulse" style={{ animationDelay: `${i * 80}ms` }} />
          <div className="w-32 bg-zinc-100 rounded-lg h-6 animate-pulse" style={{ animationDelay: `${i * 100}ms` }} />
          <div className="w-24 bg-zinc-100 rounded-lg h-6 animate-pulse" style={{ animationDelay: `${i * 120}ms` }} />
        </div>
      ))}
    </div>
  );
}

