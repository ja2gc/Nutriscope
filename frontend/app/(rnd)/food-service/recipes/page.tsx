"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { CookingPot, Plus, Pencil, Trash2, Loader2, Banana } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Pagination, PaginationMeta } from "@/components/ui/Pagination";

interface FSSRecipe {
  id: number;
  name: string;
  category: string | null;
  servings: number;
}

interface RecipePage {
  data: FSSRecipe[];
  meta: PaginationMeta;
}

async function listRecipes(page: number, category: string): Promise<RecipePage> {
  const params = new URLSearchParams({ page: String(page), per_page: "15" });
  if (category !== "All") params.set("category", category);
  const res = await fetch(`/api/fss/food-service-recipes?${params}`);
  if (!res.ok) throw new Error("Failed to load recipes.");
  const json = await res.json();
  return {
    data: json.data ?? [],
    meta: json.meta ?? { current_page: page, per_page: 15, total: 0, last_page: 1 },
  };
}

async function deleteRecipe(id: number): Promise<void> {
  const res = await fetch(`/api/fss/food-service-recipes/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete.");
}

const FSS_CATEGORIES = [
  "All", "beverage", "breakfast", "lunch", "snack", "dinner",
];

export default function FSSRecipeListPage() {
  const router = useRouter();
  const [recipes, setRecipes]     = useState<FSSRecipe[]>([]);
  const [meta, setMeta]           = useState<PaginationMeta | null>(null);
  const [loading, setLoading]     = useState(true);
  const [error, setError]         = useState<string | null>(null);
  const [filterCat, setFilterCat] = useState("All");
  const [page, setPage]           = useState(1);
  const [deleteId, setDeleteId]   = useState<number | null>(null);
  const [deleting, setDeleting]   = useState(false);

  async function loadPage(p: number, cat: string) {
    setPage(p);
    setLoading(true);
    setError(null);
    try {
      const result = await listRecipes(p, cat);
      setRecipes(result.data);
      setMeta(result.meta);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Error.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadPage(1, filterCat);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleDelete = async () => {
    if (!deleteId) return;
    setDeleting(true);
    try {
      await deleteRecipe(deleteId);
      setDeleteId(null);
      const newPage = recipes.length === 1 && page > 1 ? page - 1 : page;
      void loadPage(newPage, filterCat);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Delete failed.");
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="space-y-6 font-sans">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-warm-200">
        <div>
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
            <CookingPot className="h-5 w-5 text-emerald-600" />
            Foods
          </h2>
          <p className="text-sm text-warm-500 mt-1 select-none">
            Manage recipes and single-ingredient food items used by the menu cycle.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="secondary" onClick={() => router.push("/food-service/foods/new?single=1")}
            className="w-auto flex items-center gap-2 px-4">
            <Banana className="h-3.5 w-3.5" /> Single Item
          </Button>
          <Button variant="primary" onClick={() => router.push("/food-service/foods/new")}
            className="w-auto flex items-center gap-2 px-4">
            <Plus className="h-3.5 w-3.5" /> New Recipe
          </Button>
        </div>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-100 p-4 rounded-xl text-sm text-red-700 font-bold">{error}</div>
      )}

      {/* Category filter */}
      <div className="flex flex-wrap gap-2">
        {FSS_CATEGORIES.map((cat) => (
          <button key={cat} onClick={() => { setFilterCat(cat); void loadPage(1, cat); }}
            className={`px-3 py-1.5 rounded-full text-xs font-bold border transition-colors cursor-pointer ${
              filterCat === cat
                ? "bg-emerald-600 text-white border-emerald-600"
                : "bg-white text-warm-600 border-warm-200 hover:border-emerald-400"
            }`}>
            {cat}
          </button>
        ))}
      </div>

      {/* Table */}
      {loading ? (
        <div className="flex items-center justify-center h-40">
          <Loader2 className="h-6 w-6 animate-spin text-emerald-600" />
        </div>
      ) : recipes.length === 0 ? (
        <div className="text-center py-16 border border-dashed border-warm-200 rounded-2xl">
          <CookingPot className="h-8 w-8 text-warm-200 mx-auto mb-3" />
          <p className="text-base font-bold text-warm-400">No recipes found.</p>
          <p className="text-sm text-warm-300 mt-1">Add a recipe to get started.</p>
        </div>
      ) : (
        <div className="bg-white border border-warm-200 rounded-2xl overflow-hidden shadow-sm">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-warm-100 bg-warm-50/60">
                <th className="text-left px-5 py-3 font-extrabold text-warm-500 uppercase tracking-wider">Recipe Name</th>
                <th className="text-left px-4 py-3 font-extrabold text-warm-500 uppercase tracking-wider">Category</th>
                <th className="px-4 py-3 text-right font-extrabold text-warm-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100">
              {recipes.map((recipe) => (
                <tr key={recipe.id} className="hover:bg-warm-50 transition-colors">
                  <td className="px-5 py-3.5">
                    <span className="font-bold text-warm-900">{recipe.name}</span>
                  </td>
                  <td className="px-4 py-3.5">
                    {recipe.category ? (
                      <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        {recipe.category}
                      </span>
                    ) : (
                      <span className="text-warm-300">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3.5 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <Link href={`/food-service/foods/${recipe.id}`}
                        className="p-1.5 rounded-lg text-warm-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                        <Pencil className="h-3.5 w-3.5" />
                      </Link>
                      <button onClick={() => setDeleteId(recipe.id)}
                        className="p-1.5 rounded-lg text-warm-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer">
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {meta && <Pagination meta={meta} page={page} onPageChange={(p) => void loadPage(p, filterCat)} />}
        </div>
      )}

      {/* Delete confirm */}
      {deleteId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
          onClick={() => setDeleteId(null)}>
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm border border-warm-200 overflow-hidden"
            onClick={(e) => e.stopPropagation()}>
            <div className="p-6 space-y-4">
              <h3 className="text-base font-extrabold text-warm-900">Delete Recipe?</h3>
              <p className="text-sm text-warm-500">
                This will permanently delete the recipe and all its ingredients. This cannot be undone.
              </p>
              <div className="flex gap-3 justify-end">
                <Button variant="secondary" onClick={() => setDeleteId(null)} className="w-auto px-4">Cancel</Button>
                <Button variant="danger" onClick={handleDelete} loading={deleting} className="w-auto px-4">Delete</Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
