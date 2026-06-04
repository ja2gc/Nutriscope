"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Database, Plus, Search, Download, Trash2, Pencil,
  CookingPot, X, Loader2, ChevronLeft, ChevronRight,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  fetchFoodItems, fetchRecipes, deleteFoodItem, deleteRecipe,
  searchUsda, importUsdaFood,
  FoodItem, Recipe, UsdaSearchResult,
} from "@/services/foodLibraryService";

const FOOD_CATEGORIES = ["all", "protein", "carbs", "vegetable", "fat", "dairy", "fruit"];
const RECIPE_CATEGORIES = ["all", "breakfast", "lunch", "dinner", "snack"];

type Tab = "foods" | "recipes";

// ─── USDA Import Modal ────────────────────────────────────────────────────────

function UsdaImportModal({
  onClose,
  onImported,
}: {
  onClose: () => void;
  onImported: (food: FoodItem) => void;
}) {
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<UsdaSearchResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [importing, setImporting] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleSearch = async () => {
    if (!query.trim()) return;
    try {
      setSearching(true);
      setError(null);
      setResults(await searchUsda(query.trim()));
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Search failed.");
    } finally {
      setSearching(false);
    }
  };

  const handleImport = async (fdcId: number) => {
    try {
      setImporting(fdcId);
      setError(null);
      onImported(await importUsdaFood(fdcId));
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Import failed.");
    } finally {
      setImporting(null);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col border border-zinc-200">
        <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
          <div>
            <h3 className="text-sm font-extrabold text-zinc-900 uppercase tracking-wider">Import from USDA</h3>
            <p className="text-[10px] text-zinc-400 mt-0.5">Search the USDA FoodData Central database — macros &amp; micros imported automatically</p>
          </div>
          <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-400 hover:text-zinc-700 cursor-pointer transition-colors">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="px-6 py-4 border-b border-zinc-100">
          <div className="flex gap-2">
            <input
              type="text"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && handleSearch()}
              placeholder="e.g. chicken breast, brown rice, banana..."
              className="flex-1 px-4 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-zinc-400"
              autoFocus
            />
            <Button variant="primary" onClick={handleSearch} disabled={searching || !query.trim()} className="px-4 py-2 w-auto shrink-0">
              {searching ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
            </Button>
          </div>
          {error && <p className="text-[11px] text-red-600 font-semibold mt-2">{error}</p>}
        </div>

        <div className="flex-1 overflow-y-auto px-6 py-3 space-y-2">
          {results.length === 0 && !searching && (
            <div className="text-center py-8 text-zinc-400 text-xs select-none">
              {query ? "No results found." : "Enter a food name to search."}
            </div>
          )}
          {results.map((item) => (
            <div key={item.fdc_id} className="flex items-center justify-between gap-4 p-3 rounded-xl border border-zinc-100 hover:border-zinc-200 hover:bg-zinc-50/50 transition-all">
              <div className="min-w-0">
                <div className="text-xs font-bold text-zinc-900 truncate">{item.name}</div>
                <div className="text-[10px] text-zinc-400 mt-0.5 font-mono">
                  {item.calories} kcal · P {item.protein}g · C {item.carbs}g · F {item.fat}g · FDC#{item.fdc_id}
                </div>
              </div>
              <button
                onClick={() => handleImport(item.fdc_id)}
                disabled={importing === item.fdc_id}
                className="shrink-0 flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-[10px] font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer"
              >
                {importing === item.fdc_id ? <Loader2 className="h-3 w-3 animate-spin" /> : <Download className="h-3 w-3" />}
                Import
              </button>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function FoodLibraryPage() {
  const [activeTab, setActiveTab] = useState<Tab>("foods");

  const [foods, setFoods] = useState<FoodItem[]>([]);
  const [foodSearch, setFoodSearch] = useState("");
  const [foodCategory, setFoodCategory] = useState("all");
  const [foodPage, setFoodPage] = useState(1);
  const [foodMeta, setFoodMeta] = useState<any>(null);
  const [foodLoading, setFoodLoading] = useState(true);
  const [foodError, setFoodError] = useState<string | null>(null);

  const [recipes, setRecipes] = useState<Recipe[]>([]);
  const [recipeSearch, setRecipeSearch] = useState("");
  const [recipeCategory, setRecipeCategory] = useState("all");
  const [recipePage, setRecipePage] = useState(1);
  const [recipeMeta, setRecipeMeta] = useState<any>(null);
  const [recipeLoading, setRecipeLoading] = useState(false);
  const [recipeError, setRecipeError] = useState<string | null>(null);

  const [showUsda, setShowUsda] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState<{ type: "food" | "recipe"; id: number; name: string } | null>(null);
  const [deleting, setDeleting] = useState(false);

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
        <span className="text-zinc-650 font-bold">Food Library</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Database className="h-5 w-5 text-emerald-600" />
            Food Library
          </h2>
          <p className="text-xs text-zinc-500 mt-1 select-none">
            Clinical food &amp; recipe reference for nutrition care interventions and meal planning.
          </p>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          {activeTab === "foods" ? (
            <>
              <Button variant="secondary" onClick={() => setShowUsda(true)} className="w-auto px-4 py-2.5 flex items-center gap-2 text-xs">
                <Download className="h-3.5 w-3.5" />
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
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`px-5 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all cursor-pointer -mb-px ${
              activeTab === tab
                ? "border-emerald-600 text-emerald-700"
                : "border-transparent text-zinc-400 hover:text-zinc-700 hover:border-zinc-300"
            }`}
          >
            {tab === "foods" ? (
              <span className="flex items-center gap-1.5"><Database className="h-3.5 w-3.5" /> Foods</span>
            ) : (
              <span className="flex items-center gap-1.5"><CookingPot className="h-3.5 w-3.5" /> Recipes</span>
            )}
          </button>
        ))}
      </div>

      {/* ── Foods Tab ── */}
      {activeTab === "foods" && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row items-center gap-3 bg-white p-4 rounded-xl border border-zinc-200 shadow-sm">
            <div className="relative w-full sm:flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
              <input
                type="text"
                placeholder="Search by food name..."
                value={foodSearch}
                onChange={(e) => { setFoodSearch(e.target.value); setFoodPage(1); }}
                className="w-full pl-9 pr-4 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-zinc-400"
              />
            </div>
            <select
              value={foodCategory}
              onChange={(e) => { setFoodCategory(e.target.value); setFoodPage(1); }}
              className="w-full sm:w-44 px-3 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer font-semibold"
            >
              {FOOD_CATEGORIES.map((c) => (
                <option key={c} value={c}>{c === "all" ? "All Categories" : c.charAt(0).toUpperCase() + c.slice(1)}</option>
              ))}
            </select>
          </div>

          {foodError && <ErrorBanner message={foodError} />}

          <div className="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-sm">
            {foodLoading ? <LoadingSkeleton /> : foods.length === 0 ? (
              <EmptyState icon={<Database className="h-8 w-8 text-emerald-600" />} title="No Foods Found" message="Add foods manually or import from the USDA FoodData Central database." />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="bg-zinc-50 border-b border-zinc-200 select-none">
                      <Th>Name</Th>
                      <Th>Category</Th>
                      <Th>Calories</Th>
                      <Th>Protein</Th>
                      <Th>Carbs</Th>
                      <Th>Fat</Th>
                      <Th>Allergens</Th>
                      <Th right>Actions</Th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-100">
                    {foods.map((food, i) => (
                      <tr key={food.id} className={`${i % 2 === 0 ? "bg-white" : "bg-zinc-50/20"} hover:bg-zinc-50/40 transition-colors`}>
                        <td className="px-5 py-3.5">
                          <div className="text-xs font-bold text-zinc-900">{food.name}</div>
                          {food.usda_fdc_id && (
                            <div className="text-[10px] font-mono text-zinc-400 mt-0.5">USDA FDC#{food.usda_fdc_id}</div>
                          )}
                        </td>
                        <td className="px-5 py-3.5">
                          {food.category
                            ? <span className="inline-flex px-2 py-0.5 bg-zinc-100 text-zinc-600 text-[10px] font-bold uppercase tracking-wide rounded-full">{food.category}</span>
                            : <span className="text-zinc-300 text-[10px]">—</span>}
                        </td>
                        <Td>{food.calories} kcal</Td>
                        <Td>{food.protein ? `${food.protein}g` : "—"}</Td>
                        <Td>{food.carbs ? `${food.carbs}g` : "—"}</Td>
                        <Td>{food.fat ? `${food.fat}g` : "—"}</Td>
                        <td className="px-5 py-3.5">
                          {food.allergens.length > 0 ? (
                            <div className="flex flex-wrap gap-1">
                              {food.allergens.slice(0, 2).map((a) => (
                                <span key={a} className="px-1.5 py-0.5 bg-amber-50 border border-amber-200 text-amber-700 text-[9px] font-bold uppercase rounded">{a}</span>
                              ))}
                              {food.allergens.length > 2 && <span className="text-[9px] text-zinc-400">+{food.allergens.length - 2}</span>}
                            </div>
                          ) : <span className="text-zinc-300 text-[10px]">None</span>}
                        </td>
                        <td className="px-5 py-3.5 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <Link href={`/food-library/foods/${food.id}`} className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 hover:text-zinc-800 transition-colors" title="Edit">
                              <Pencil className="h-3.5 w-3.5" />
                            </Link>
                            <button
                              onClick={() => setDeleteConfirm({ type: "food", id: food.id, name: food.name })}
                              className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-400 hover:text-red-600 transition-colors cursor-pointer"
                              title="Delete"
                            >
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
          <div className="flex flex-col sm:flex-row items-center gap-3 bg-white p-4 rounded-xl border border-zinc-200 shadow-sm">
            <div className="relative w-full sm:flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
              <input
                type="text"
                placeholder="Search by recipe name..."
                value={recipeSearch}
                onChange={(e) => { setRecipeSearch(e.target.value); setRecipePage(1); }}
                className="w-full pl-9 pr-4 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-zinc-400"
              />
            </div>
            <select
              value={recipeCategory}
              onChange={(e) => { setRecipeCategory(e.target.value); setRecipePage(1); }}
              className="w-full sm:w-44 px-3 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 cursor-pointer font-semibold"
            >
              {RECIPE_CATEGORIES.map((c) => (
                <option key={c} value={c}>{c === "all" ? "All Categories" : c.charAt(0).toUpperCase() + c.slice(1)}</option>
              ))}
            </select>
          </div>

          {recipeError && <ErrorBanner message={recipeError} />}

          <div className="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-sm">
            {recipeLoading ? <LoadingSkeleton /> : recipes.length === 0 ? (
              <EmptyState icon={<CookingPot className="h-8 w-8 text-emerald-600" />} title="No Recipes Found" message="Build clinical recipes from the foods in your library." />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="bg-zinc-50 border-b border-zinc-200 select-none">
                      <Th>Name</Th>
                      <Th>Category</Th>
                      <Th>Servings</Th>
                      <Th>Calories</Th>
                      <Th>Protein</Th>
                      <Th>Carbs</Th>
                      <Th>Fat</Th>
                      <Th right>Actions</Th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-100">
                    {recipes.map((recipe, i) => (
                      <tr key={recipe.id} className={`${i % 2 === 0 ? "bg-white" : "bg-zinc-50/20"} hover:bg-zinc-50/40 transition-colors`}>
                        <td className="px-5 py-3.5">
                          <div className="text-xs font-bold text-zinc-900">{recipe.name}</div>
                          {recipe.prep_notes && (
                            <div className="text-[10px] text-zinc-400 mt-0.5 truncate max-w-48">{recipe.prep_notes}</div>
                          )}
                        </td>
                        <td className="px-5 py-3.5">
                          {recipe.category
                            ? <span className="inline-flex px-2 py-0.5 bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase tracking-wide rounded-full border border-emerald-100">{recipe.category}</span>
                            : <span className="text-zinc-300 text-[10px]">—</span>}
                        </td>
                        <Td>{recipe.servings ?? "—"}</Td>
                        <Td>{recipe.total_calories ? `${recipe.total_calories} kcal` : "—"}</Td>
                        <Td>{recipe.total_protein ? `${recipe.total_protein}g` : "—"}</Td>
                        <Td>{recipe.total_carbs ? `${recipe.total_carbs}g` : "—"}</Td>
                        <Td>{recipe.total_fat ? `${recipe.total_fat}g` : "—"}</Td>
                        <td className="px-5 py-3.5 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <Link href={`/food-library/recipes/${recipe.id}`} className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 hover:text-zinc-800 transition-colors" title="Edit">
                              <Pencil className="h-3.5 w-3.5" />
                            </Link>
                            <button
                              onClick={() => setDeleteConfirm({ type: "recipe", id: recipe.id, name: recipe.name })}
                              className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-400 hover:text-red-600 transition-colors cursor-pointer"
                              title="Delete"
                            >
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
            <Pagination meta={recipeMeta} page={recipePage} onPageChange={setRecipePage} />
          </div>
        </div>
      )}

      {showUsda && (
        <UsdaImportModal
          onClose={() => setShowUsda(false)}
          onImported={() => { setShowUsda(false); void loadFoods(); }}
        />
      )}

      {deleteConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm border border-zinc-200 p-6 space-y-4">
            <h3 className="text-sm font-extrabold text-zinc-900">Confirm Delete</h3>
            <p className="text-xs text-zinc-600 leading-relaxed">
              Are you sure you want to delete <span className="font-bold text-zinc-900">&quot;{deleteConfirm.name}&quot;</span>? This cannot be undone.
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

function Th({ children, right }: { children: React.ReactNode; right?: boolean }) {
  return <th className={`px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider ${right ? "text-right" : ""}`}>{children}</th>;
}
function Td({ children }: { children: React.ReactNode }) {
  return <td className="px-5 py-3.5 text-xs font-semibold text-zinc-700">{children}</td>;
}
function ErrorBanner({ message }: { message: string }) {
  return (
    <div className="bg-red-50 border border-red-100 p-4 rounded-xl flex items-start gap-3">
      <span className="inline-flex h-5 w-5 items-center justify-center rounded-full border border-red-200 text-[10px] font-black text-red-600 shrink-0 mt-0.5">!</span>
      <div className="text-xs text-red-700 font-bold">{message}</div>
    </div>
  );
}
function EmptyState({ icon, title, message }: { icon: React.ReactNode; title: string; message: string }) {
  return (
    <div className="p-12 text-center select-none">
      <div className="p-3 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto">{icon}</div>
      <h3 className="text-sm font-bold text-zinc-800 mt-4">{title}</h3>
      <p className="text-xs text-zinc-500 mt-1 max-w-sm mx-auto leading-relaxed">{message}</p>
    </div>
  );
}
function LoadingSkeleton() {
  return (
    <div className="p-8 space-y-4">
      <div className="h-5 w-40 bg-zinc-200 rounded-lg animate-pulse" />
      <div className="space-y-2 pt-4">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="flex gap-4 h-12 items-center">
            <div className="flex-1 bg-zinc-100 rounded-lg h-8 animate-pulse" />
            {[1, 2, 3, 4, 5].map((j) => <div key={j} className="w-20 bg-zinc-100 rounded-lg h-8 animate-pulse" />)}
          </div>
        ))}
      </div>
    </div>
  );
}
function Pagination({ meta, page, onPageChange }: { meta: any; page: number; onPageChange: (p: number) => void }) {
  if (!meta || meta.last_page <= 1) return null;
  return (
    <div className="px-5 py-4 border-t border-zinc-100 bg-zinc-50 flex items-center justify-between select-none">
      <span className="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
        Page {meta.current_page} of {meta.last_page} ({meta.total} total)
      </span>
      <div className="flex gap-1.5">
        <button onClick={() => onPageChange(page - 1)} disabled={page === 1} className="p-1.5 border border-zinc-300 bg-white text-zinc-600 rounded-lg hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors">
          <ChevronLeft className="h-3.5 w-3.5" />
        </button>
        <button onClick={() => onPageChange(page + 1)} disabled={page === meta.last_page} className="p-1.5 border border-zinc-300 bg-white text-zinc-600 rounded-lg hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors">
          <ChevronRight className="h-3.5 w-3.5" />
        </button>
      </div>
    </div>
  );
}
