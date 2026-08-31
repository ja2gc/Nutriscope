"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { AlertTriangle, ArrowLeft, LockKeyhole, RotateCcw, Search, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { searchCatalog, type CatalogItem } from "@/services/fsCatalogService";
import {
  getMenuSlotRecipe,
  MEAL_LABELS,
  restoreMenuSlotRecipe,
  scaledIngredientQuantity,
  updateMenuSlotRecipe,
  type Day,
  type Meal,
  type MenuSlotIngredient,
  type MenuSlotRecipe,
} from "@/services/menuCycleService";

type DraftIngredient = Pick<MenuSlotIngredient, "fs_item_id" | "name" | "quantity" | "unit">;
type Draft = Pick<MenuSlotRecipe, "name" | "reference_servings" | "prep_notes"> & {
  ingredients: DraftIngredient[];
};

function draftFrom(data: MenuSlotRecipe): Draft {
  return {
    name: data.name,
    reference_servings: data.reference_servings,
    prep_notes: data.prep_notes,
    ingredients: data.ingredients.map(({ fs_item_id, name, quantity, unit }) => ({ fs_item_id, name, quantity, unit })),
  };
}

const peso = (value: number) => `₱${value.toFixed(2)}`;

export function MenuSlotRecipePage({ backHref }: { backHref: string }) {
  const { cycleId, day, meal } = useParams<{ cycleId: string; day: Day; meal: Meal }>();
  const [data, setData] = useState<MenuSlotRecipe | null>(null);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [confirmRestore, setConfirmRestore] = useState(false);
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<CatalogItem[]>([]);
  const [searching, setSearching] = useState(false);

  useEffect(() => {
    let active = true;
    setLoading(true);
    getMenuSlotRecipe(cycleId, day, meal)
      .then((slot) => {
        if (!active) return;
        setData(slot);
        setDraft(draftFrom(slot));
      })
      .catch((cause) => { if (active) setError(cause instanceof Error ? cause.message : "Failed to load menu item details."); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [cycleId, day, meal]);

  const canEdit = Boolean(data?.editable) && !data?.locked;
  const ingredientIds = useMemo(() => new Set(draft?.ingredients.map((item) => item.fs_item_id)), [draft?.ingredients]);

  function updateIngredient(index: number, patch: Partial<DraftIngredient>) {
    setDraft((current) => current ? {
      ...current,
      ingredients: current.ingredients.map((item, itemIndex) => itemIndex === index ? { ...item, ...patch } : item),
    } : current);
  }

  async function findIngredients(event: FormEvent) {
    event.preventDefault();
    if (query.trim().length < 2) return;
    setSearching(true);
    setError("");
    try {
      setResults(await searchCatalog(query.trim(), "ingredient"));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Ingredient search failed.");
    } finally {
      setSearching(false);
    }
  }

  function addIngredient(item: CatalogItem) {
    if (ingredientIds.has(item.id)) return;
    setDraft((current) => current ? {
      ...current,
      ingredients: [...current.ingredients, { fs_item_id: item.id, name: item.name, quantity: 1, unit: item.base_unit }],
    } : current);
    setQuery("");
    setResults([]);
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    if (!draft || !canEdit) return;
    setSaving(true);
    setError("");
    setNotice("");
    try {
      const saved = await updateMenuSlotRecipe(cycleId, day, meal, {
        ...draft,
        prep_notes: draft.prep_notes || null,
        ingredients: draft.ingredients.map(({ fs_item_id, quantity, unit }) => ({ fs_item_id, quantity, unit })),
      });
      setData(saved);
      setDraft(draftFrom(saved));
      setNotice("Menu slot changes saved. The original recipe was not changed.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to save menu slot changes.");
    } finally {
      setSaving(false);
    }
  }

  async function restore() {
    setSaving(true);
    setError("");
    try {
      const restored = await restoreMenuSlotRecipe(cycleId, day, meal);
      setData(restored);
      setDraft(draftFrom(restored));
      setConfirmRestore(false);
      setNotice("Original recipe restored for this slot.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to restore the original recipe.");
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <div className="py-20 text-center text-sm text-warm-500">Loading menu item details…</div>;
  if (!data || !draft) return <div className="space-y-4"><Link href={backHref} className="inline-flex min-h-11 items-center gap-2 text-sm font-bold text-emerald-700"><ArrowLeft className="h-4 w-4" /> Back to menu cycle</Link><div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error || "Menu item not found."}</div></div>;

  return (
    <div className="mx-auto max-w-5xl space-y-5 pb-10">
      <Link href={backHref} className="inline-flex min-h-11 items-center gap-2 rounded-lg pr-3 text-sm font-bold text-emerald-700 hover:bg-emerald-50">
        <ArrowLeft className="h-4 w-4" /> Back to menu cycle
      </Link>

      <header className="flex flex-col gap-3 border-b border-warm-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p className="text-xs font-extrabold uppercase tracking-wider text-warm-500">{day} · {MEAL_LABELS[meal]}</p>
          <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-warm-900">Menu Item Details</h1>
          <p className="mt-1 text-sm text-warm-600">{canEdit ? "Changes apply only to this menu slot. The original recipe stays unchanged." : "View baseline recipe values and the current purchase estimate."}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <span className="rounded-full border border-warm-200 bg-warm-50 px-3 py-1 text-xs font-bold text-warm-600">{data.source === "custom" ? "Customized slot" : "Original recipe"}</span>
          {!data.editable && <span className="rounded-full border border-warm-200 px-3 py-1 text-xs font-bold text-warm-500">View only</span>}
          {data.locked && <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800"><LockKeyhole className="h-3 w-3" /> Locked to PO</span>}
        </div>
      </header>

      {error && <div role="alert" className="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700"><AlertTriangle className="h-4 w-4 shrink-0" /> {error}</div>}
      {notice && <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{notice}</div>}

      <form onSubmit={save} className="space-y-5">
        <section className="grid grid-cols-1 gap-4 rounded-2xl border border-warm-200 bg-white p-4 shadow-sm sm:grid-cols-2 sm:p-5">
          <label className="sm:col-span-2">
            <span className="mb-1 block text-xs font-extrabold uppercase tracking-wider text-warm-500">Menu item name</span>
            <input required maxLength={255} value={draft.name} readOnly={!canEdit} onChange={(event) => setDraft({ ...draft, name: event.target.value })} className="min-h-11 w-full rounded-lg border border-warm-200 px-3 text-base font-semibold text-warm-900 read-only:bg-warm-50 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          </label>
          <label>
            <span className="mb-1 block text-xs font-extrabold uppercase tracking-wider text-warm-500">Recipe makes</span>
            <div className="flex items-center gap-2"><input required type="number" min={1} value={draft.reference_servings} readOnly={!canEdit} onChange={(event) => setDraft({ ...draft, reference_servings: Math.max(1, Number(event.target.value)) })} className="min-h-11 w-full rounded-lg border border-warm-200 px-3 text-base read-only:bg-warm-50 focus:outline-none focus:ring-2 focus:ring-emerald-500" /><span className="text-sm text-warm-500">servings</span></div>
            <span className="mt-1 block text-xs text-warm-400">Baseline used to scale every ingredient.</span>
          </label>
          <div><span className="mb-1 block text-xs font-extrabold uppercase tracking-wider text-warm-500">Purchase estimate</span><div className="flex min-h-11 items-center rounded-lg border border-warm-200 bg-warm-50 px-3 text-base font-semibold text-warm-800">{data.purchase_estimate_set ? `${data.planned_servings} servings` : "Not set"}</div><span className="mt-1 block text-xs text-warm-400">Set once when generating the shopping list.</span></div>
        </section>

        <section className="rounded-2xl border border-warm-200 bg-white p-4 shadow-sm sm:p-5">
          <div className="mb-3 flex items-end justify-between gap-3">
            <div><h2 className="font-extrabold text-warm-900">Ingredients</h2><p className="text-xs text-warm-500">Exact recipe quantities stay visible; purchase quantities appear after a shopping estimate is set.</p></div>
          </div>
          <div className="space-y-3">
            {draft.ingredients.map((ingredient, index) => {
              const scaled = data.planned_servings == null ? null : scaledIngredientQuantity(ingredient.quantity, draft.reference_servings, data.planned_servings);
              return (
                <div key={ingredient.fs_item_id} className="grid grid-cols-1 gap-3 rounded-xl border border-warm-100 bg-warm-50/50 p-3 sm:grid-cols-[minmax(0,1fr)_8rem_7rem_7rem_auto] sm:items-end">
                  <div><span className="block text-xs font-bold uppercase tracking-wider text-warm-400">Ingredient</span><span className="mt-2 block min-h-8 text-sm font-bold text-warm-800">{ingredient.name}</span>{data.ingredients[index]?.include_in_generated_lists === false && <span className="mt-1 inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-800">Purchase when needed</span>}</div>
                  <label><span className="block text-xs font-bold uppercase tracking-wider text-warm-400">Baseline qty</span><input required type="number" min="0.001" step="any" value={ingredient.quantity} readOnly={!canEdit} onChange={(event) => updateIngredient(index, { quantity: Number(event.target.value) })} className="mt-1 min-h-11 w-full rounded-lg border border-warm-200 px-2 text-base read-only:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500" /></label>
                  <label><span className="block text-xs font-bold uppercase tracking-wider text-warm-400">Unit</span><input required maxLength={30} value={ingredient.unit} readOnly={!canEdit} onChange={(event) => updateIngredient(index, { unit: event.target.value })} className="mt-1 min-h-11 w-full rounded-lg border border-warm-200 px-2 text-base read-only:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500" /></label>
                  <div><span className="block text-xs font-bold uppercase tracking-wider text-warm-400">Purchase qty</span><span className="mt-1 flex min-h-11 items-center font-mono text-sm font-bold text-emerald-700">{scaled == null ? "Not set" : `${scaled.toFixed(3)} ${ingredient.unit}`}</span></div>
                  {canEdit && <button type="button" aria-label={`Remove ${ingredient.name}`} onClick={() => setDraft({ ...draft, ingredients: draft.ingredients.filter((_, itemIndex) => itemIndex !== index) })} disabled={draft.ingredients.length === 1} className="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-warm-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-30"><Trash2 className="h-4 w-4" /></button>}
                </div>
              );
            })}
          </div>

          {canEdit && <div className="mt-4 rounded-xl border border-dashed border-warm-200 p-3"><p className="mb-2 text-xs font-extrabold uppercase tracking-wider text-warm-500">Add ingredient</p><div className="flex flex-col gap-2 sm:flex-row"><input value={query} onChange={(event) => setQuery(event.target.value)} className="min-h-11 flex-1 rounded-lg border border-warm-200 px-3 text-base focus:outline-none focus:ring-2 focus:ring-emerald-500" /><Button type="button" variant="secondary" loading={searching} disabled={query.trim().length < 2} onClick={findIngredients} className="min-h-11"><Search className="mr-2 h-4 w-4" /> Search</Button></div>{results.length > 0 && <div className="mt-2 divide-y divide-warm-100 rounded-lg border border-warm-100">{results.map((item) => <button key={item.id} type="button" disabled={ingredientIds.has(item.id)} onClick={() => addIngredient(item)} className="flex min-h-11 w-full items-center justify-between px-3 text-left text-sm font-semibold text-warm-700 hover:bg-emerald-50 disabled:opacity-40"><span>{item.name}</span><span className="text-xs text-warm-400">{ingredientIds.has(item.id) ? "Added" : item.base_unit}</span></button>)}</div>}</div>}
        </section>

        <section className="rounded-2xl border border-warm-200 bg-white p-4 shadow-sm sm:p-5">
          <label><span className="mb-1 block text-xs font-extrabold uppercase tracking-wider text-warm-500">Preparation notes</span><textarea rows={5} maxLength={5000} value={draft.prep_notes ?? ""} readOnly={!canEdit} onChange={(event) => setDraft({ ...draft, prep_notes: event.target.value })} className="w-full rounded-lg border border-warm-200 p-3 text-base leading-6 read-only:bg-warm-50 focus:outline-none focus:ring-2 focus:ring-emerald-500" /></label>
        </section>

        <section className="flex flex-col gap-3 rounded-2xl border border-warm-200 bg-warm-50 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div><p className="text-xs font-extrabold uppercase tracking-wider text-warm-500">Purchase estimate</p>{data.total_cost == null ? <p className="text-xl font-extrabold text-warm-500">Not set</p> : <p className="text-xl font-extrabold text-emerald-700">{peso(data.total_cost)} <span className="text-sm font-semibold text-warm-500">· {peso(data.cost_per_head ?? 0)} / serving</span></p>}<p className="text-xs text-warm-400">Baseline recipe cost: {peso(data.baseline_total_cost)}</p></div>
          {canEdit && <div className="flex flex-wrap gap-2">{data.source === "custom" && (confirmRestore ? <><Button type="button" variant="danger" onClick={restore} disabled={saving}>Confirm restore</Button><Button type="button" variant="ghost" onClick={() => setConfirmRestore(false)}>Cancel</Button></> : <Button type="button" variant="secondary" onClick={() => setConfirmRestore(true)}><RotateCcw className="mr-2 h-4 w-4" /> Use original recipe</Button>)}<Button type="submit" loading={saving}>Save slot changes</Button></div>}
        </section>
      </form>
    </div>
  );
}
