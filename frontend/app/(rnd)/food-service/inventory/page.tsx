"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Boxes, Plus, Pencil, Trash2, X } from "lucide-react";
import { useUnsavedChanges } from "@/hooks/useUnsavedChanges";
import { Button } from "@/components/ui/Button";
import { Tabs } from "@/components/ui/Tabs";
import { FoodServiceMenuItems } from "./_components/FoodServiceMenuItems";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import SearchInput from "@/components/ui/SearchInput";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import { useAuth } from "@/contexts/AuthContext";
import {
  CatalogItem, FsItemKind, listCatalog, createFsItem, updateFsItem, deleteFsItem, CreateFsItemPayload,
  RecipeCorrectionsRequiredError, type AffectedRecipe, type RecipeCorrection,
} from "@/services/fsCatalogService";
import { listAllSuppliers, Supplier } from "@/services/supplierService";
import { CATALOG_UNIT_OPTIONS } from "@/lib/units";
import { numericStepBase } from "@/components/ui/NumericInput";
import { InfoHint } from "@/components/ui/InfoHint";
import { compatibleRecipeUnits } from "@/lib/foodServiceUnits";
import { clearCorrectionDraft, correctionHref, correctionKey, readCorrectionDraft, writeCorrectionDraft, type InventoryCorrectionDraft } from "@/lib/inventoryCorrectionDraft";

const peso = (n: number | string | null) => `₱${(n ? parseFloat(String(n)) : 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 6 })}`;
const inputCls = "w-full px-3 py-2 text-base border border-warm-200 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500";

const TABS: { key: FsItemKind; label: string }[] = [
  { key: "ingredient", label: "Ingredients" },
  { key: "supply", label: "Supplies" },
];

function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-1">{children}</label>;
}

interface FormState {
  name: string; default_supplier_id: string;
  base_unit: string; purchase_price: string; include_in_generated_lists: boolean;
}
const emptyForm: FormState = { name: "", default_supplier_id: "", base_unit: "", purchase_price: "", include_in_generated_lists: true };

function ItemFormModal({ kind, editing, suppliers, initialDraft, initialToken, onClose, onSaved }: {
  kind: FsItemKind; editing: CatalogItem | null; suppliers: Supplier[];
  initialDraft?: InventoryCorrectionDraft | null; initialToken?: string;
  onClose: () => void; onSaved: () => void;
}) {
  const router = useRouter();
  const isSupply = kind === "supply";
  const savedForm: FormState = editing ? {
    name: editing.name, default_supplier_id: editing.default_supplier_id ? String(editing.default_supplier_id) : '',
    base_unit: editing.base_unit ?? '', purchase_price: editing.purchase_price ?? '', include_in_generated_lists: editing.include_in_generated_lists,
  } : emptyForm;
  const [form, setForm] = useState<FormState>(initialDraft?.form ?? savedForm);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [affectedRecipes, setAffectedRecipes] = useState<AffectedRecipe[]>(initialDraft?.affected ?? []);
  const [corrections, setCorrections] = useState<Record<string, { quantity: string; unit: string }>>(initialDraft?.corrections ?? {});
  const [token, setToken] = useState(initialToken ?? "");

  const resetCorrections = () => {
    setAffectedRecipes([]); setCorrections({});
    clearCorrectionDraft(token); setToken("");
    if (typeof window !== "undefined" && window.location.search.includes("inventoryCorrection")) window.history.replaceState(window.history.state, "", "/food-service/inventory");
  };


  const set = (k: keyof FormState, v: string | boolean) => {
    if (k === "base_unit" || k === "include_in_generated_lists") resetCorrections();
    setForm((f) => ({ ...f, [k]: v }));
  };

  const guard = useUnsavedChanges({ dirty: JSON.stringify(form) !== JSON.stringify(savedForm) || Object.keys(corrections).length > 0, onSave: save });

  async function save() {
    setError(null);
    if (!form.name.trim()) { setError("Name is required."); return false; }
    // Ingredients carry a single unit; supplies have no unit input (cost-per-unit only).
    if (!isSupply && !form.base_unit.trim()) { setError("Unit is required."); return false; }
    const price = parseFloat(form.purchase_price);
    if (!Number.isFinite(price) || price < 0) { setError("Enter a valid cost."); return false; }

    setSaving(true);
    try {
      const payload: CreateFsItemPayload = {
        name: form.name.trim(),
        kind,
        base_unit: isSupply ? "unit" : form.base_unit.trim(),
        purchase_price: price,
        default_supplier_id: form.default_supplier_id ? form.default_supplier_id : null,
        include_in_generated_lists: isSupply ? true : form.include_in_generated_lists,
      };
      if (editing) {
        const patch: Partial<CreateFsItemPayload> = { ...payload };
        if (form.base_unit !== editing.base_unit && price === Number(editing.purchase_price)) {
          delete patch.purchase_price;
        }
        if (affectedRecipes.length) {
          const entries: RecipeCorrection[] = affectedRecipes.map((recipe) => {
            const correction = corrections[correctionKey(recipe)];
            return {
              ...(recipe.slot_id ? { slot_id: recipe.slot_id } : { recipe_id: recipe.recipe_id }),
              line_index: recipe.line_index,
              quantity: Number(correction?.quantity),
              unit: correction?.unit ?? "",
            };
          });
          if (entries.some((entry) => !Number.isFinite(entry.quantity) || entry.quantity <= 0 ||
            !compatibleRecipeUnits(form.base_unit).includes(entry.unit))) {
            setError("Open and correct every listed Food and menu item before saving Inventory.");
            return false;
          }
          patch.recipe_corrections = entries;
        }
        await updateFsItem(editing.id, patch);
      } else {
        await createFsItem(payload);
      }
      onSaved();
      return true;
    } catch (e) {
      if (e instanceof RecipeCorrectionsRequiredError) {
        setAffectedRecipes(e.affectedRecipes);
        const nextCorrections = Object.fromEntries(e.affectedRecipes.flatMap((recipe) => {
          const key = correctionKey(recipe);
          return corrections[key] ? [[key, corrections[key]]] : [];
        }));
        setCorrections(nextCorrections);
        const nextToken = token || crypto.randomUUID();
        setToken(nextToken);
        if (editing) writeCorrectionDraft(nextToken, { editing, form, affected: e.affectedRecipes, corrections: nextCorrections });
        window.history.replaceState(window.history.state, "", `/food-service/inventory?inventoryCorrection=${encodeURIComponent(nextToken)}`);
      }
      setError(e instanceof Error ? e.message : "Failed to save.");
      return false;
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" onClick={() => guard.requestLeave(onClose)}>
      {guard.dialog}
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 space-y-4" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between">
          <h3 className="text-base font-extrabold text-warm-900 uppercase tracking-wider">
            {editing ? "Edit" : "New"} {TABS.find((t) => t.key === kind)?.label.replace(/s$/, "")}
          </h3>
          <button onClick={() => guard.requestLeave(onClose)} className="text-warm-400 hover:text-warm-700"><X className="h-4 w-4" /></button>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="sm:col-span-2">
            <Label>Name</Label>
            <input value={form.name} onChange={(e) => set("name", e.target.value)} className={inputCls} />
          </div>
          <div>
            <Label>Vendor</Label>
            <select value={form.default_supplier_id} onChange={(e) => set("default_supplier_id", e.target.value)} className={`${inputCls} bg-white`}>
              <option value="">Unassigned</option>
              {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </div>

          {isSupply ? (
            <div>
              <Label>Cost/unit (₱)</Label>
              <input type="number" min={numericStepBase(form.purchase_price)} step="0.1" value={form.purchase_price} onChange={(e) => set("purchase_price", e.target.value)} className={inputCls} />
            </div>
          ) : (
            <>
              <div>
                <div className="flex items-center gap-1"><Label>Unit</Label><InfoHint label="About Inventory units">Matching unit changes keep the same price. If a Food or editable menu item cannot use the new unit, update it before saving.</InfoHint></div>
                <select value={form.base_unit} onChange={(e) => set("base_unit", e.target.value)} className={`${inputCls} bg-white`}>
                  <option value="">Select unit…</option>
                  {form.base_unit && (form.base_unit === "sack" || !CATALOG_UNIT_OPTIONS.includes(form.base_unit as typeof CATALOG_UNIT_OPTIONS[number])) && <option value={form.base_unit}>{form.base_unit}</option>}
                  {CATALOG_UNIT_OPTIONS.filter((u) => u !== "sack").map((u) => <option key={u} value={u}>{u}</option>)}
                </select>
              </div>
              <div>
                <Label>Cost/unit (₱)</Label>
                <input type="number" min={numericStepBase(form.purchase_price)} step="0.1" value={form.purchase_price} onChange={(e) => set("purchase_price", e.target.value)} className={inputCls} />
              </div>
              <div className="sm:col-span-2 flex flex-wrap items-center gap-x-3 gap-y-0 text-xs text-warm-500">
                <span>Weight <InfoHint label="About weight conversions">g, kg and oz convert to each other.</InfoHint></span>
                <span>Liquid <InfoHint label="About liquid conversions">mL, L, cup, tbsp and tsp convert to each other.</InfoHint></span>
                <span>Pieces/packs <InfoHint label="About piece and pack units">pc, bundle, pack, tray and unit each match only themselves.</InfoHint></span>
              </div>
            </>
          )}
          {!isSupply && <label className="sm:col-span-2 flex items-start gap-3 rounded-xl border border-warm-200 bg-warm-50 p-3"><input type="checkbox" checked={form.include_in_generated_lists} onChange={(e) => set("include_in_generated_lists", e.target.checked)} className="mt-1 h-4 w-4 accent-emerald-600" /><span><span className="block text-sm font-bold text-warm-800">Include in generated shopping lists</span><span className="block text-xs text-warm-500">Turn off for items purchased only when needed. Recipes still keep the exact measurement.</span></span></label>}
        </div>

        {affectedRecipes.length > 0 && (
          <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 space-y-3">
            <p className="text-sm font-bold text-amber-900">Update these recipes and menu items before saving</p>
            <p className="text-xs text-amber-800">Enter how much each uses in a unit that matches {form.base_unit}. Servings stay the same.</p>
            {affectedRecipes.map((recipe) => {
              const key = correctionKey(recipe);
              const correction = corrections[key];
              const href = correctionHref(recipe, token);
              return <div key={key} className="flex items-center justify-between gap-3 rounded-lg bg-white p-2">
                <div className="text-sm font-semibold text-warm-800">{recipe.recipe_name}<span className="block text-xs font-normal text-warm-500">Current: {recipe.quantity ?? "Not measured"} {recipe.unit}</span></div>
                {href ? <button type="button" className="shrink-0 text-sm font-bold text-emerald-700" onClick={() => {
                  if (editing) writeCorrectionDraft(token, { editing, form, affected: affectedRecipes, corrections });
                  guard.allowNavigation();
                  router.push(href);
                }}>{correction ? "Edit correction" : "Correct"}</button> : <span className="text-xs text-red-600">Reopen Inventory to refresh this item.</span>}
              </div>;
            })}
          </div>
        )}

        {error && <p className="text-sm text-red-600 font-semibold">{error}</p>}
        <div className="flex justify-end gap-3 pt-1">
          <Button variant="secondary" onClick={() => guard.requestLeave(onClose)} className="px-4 py-2">Cancel</Button>
          <Button variant="primary" onClick={save} loading={saving} className="px-4 py-2">{editing ? "Save" : "Create"}</Button>
        </div>
      </div>
    </div>
  );
}

export default function InventoryCatalogPage() {
  const { user } = useAuth();
  const isRnd = user?.role === "RND";
  const [tab, setTab] = useState<FsItemKind>("ingredient");
  const [menuItemsOpen, setMenuItemsOpen] = useState(false);
  useEffect(() => {
    setMenuItemsOpen(new URLSearchParams(window.location.search).get('tab') === 'menu-items');
  }, []);
  const [items, setItems] = useState<CatalogItem[]>([]);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [grocery, setGrocery] = useState<"auto" | "non_auto" | "">("");
  const debouncedSearch = useDebouncedValue(search);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<CatalogItem | null>(null);
  const [restoredDraft, setRestoredDraft] = useState<InventoryCorrectionDraft | null>(null);
  const [restoredToken, setRestoredToken] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);

  // Load the whole catalog once; Ingredients covers food items, Supplies covers supplies.
  const [loadError, setLoadError] = useState<string | null>(null);
  const load = useCallback(async (quiet = false) => {
    if (!quiet) setLoading(true);
    setLoadError(null);
    try {
      const [c, s] = await Promise.all([listCatalog(tab, page, debouncedSearch, tab === "ingredient" ? grocery : ""), listAllSuppliers()]);
      setItems(c.data); setMeta(c.meta); setSuppliers(s);
    } catch (cause) {
      setLoadError(cause instanceof Error ? cause.message : "Failed to load inventory.");
    } finally { setLoading(false); }
  }, [page, debouncedSearch, tab, grocery]);
  useEffect(() => { load(); }, [load]);

  useEffect(() => { setPage(1); }, [search, tab, grocery]);

  useEffect(() => {
    const token = new URLSearchParams(window.location.search).get("inventoryCorrection") ?? "";
    const draft = readCorrectionDraft(token);
    if (!draft) return;
    setRestoredDraft(draft); setRestoredToken(token);
    setTab(draft.editing.kind); setEditing(draft.editing); setModalOpen(true);
  }, []);

  function closeModal() {
    const token = restoredToken || new URLSearchParams(window.location.search).get("inventoryCorrection") || "";
    clearCorrectionDraft(token);
    if (token) window.history.replaceState(window.history.state, "", "/food-service/inventory");
    setRestoredDraft(null); setRestoredToken(""); setModalOpen(false);
  }

  async function remove(item: CatalogItem) {
    if (!confirm(`Delete "${item.name}" from the catalog?`)) return;
    try { await deleteFsItem(item.id); await load(true); }
    catch (e) { alert(e instanceof Error ? e.message : "Failed to delete."); }
  }

  const isSupply = tab === "supply";

  return (
    <div className="space-y-6 font-sans">
      <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700">Home</Link><span>/</span>
        <span>Food Service</span><span>/</span>
        <span className="font-bold text-warm-600">Inventory</span>
      </div>

      <div className="border-b border-warm-200 pb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
            <Boxes className="h-5 w-5 text-emerald-600" /> Inventory — Reference Catalog
          </h2>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          {isRnd && !menuItemsOpen && (
            <Button variant="primary" onClick={() => { setEditing(null); setModalOpen(true); }} className="px-4 py-2.5 flex items-center gap-2">
              <Plus className="h-4 w-4" /> New {TABS.find((t) => t.key === tab)?.label.replace(/s$/, "")}
            </Button>
          )}
        </div>
      </div>

      <Tabs<FsItemKind | 'menu_items'> ariaLabel="Inventory sections" items={[TABS[0], { key: 'menu_items', label: 'Menu Items' }, TABS[1]]} value={menuItemsOpen ? 'menu_items' : tab} onChange={(value) => {
        setMenuItemsOpen(value === 'menu_items');
        if (value !== 'menu_items') setTab(value);
        const url = new URL(window.location.href);
        if (value === 'menu_items') url.searchParams.set('tab', 'menu-items');
        else url.searchParams.delete('tab');
        window.history.replaceState(window.history.state, '', url.pathname + url.search);
      }} fill />
      {menuItemsOpen ? <FoodServiceMenuItems canManage={isRnd} /> : <>
      {loadError && <p role="alert" className="text-sm text-red-700">{loadError}</p>}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <SearchInput className="w-full sm:flex-1" label="Search inventory" value={search} onChange={setSearch} loading={loading && search !== debouncedSearch} />
        {!isSupply && <div className="flex flex-wrap items-center gap-2">
          <div role="group" aria-label="Shopping list behavior filter" className="flex flex-wrap gap-1.5">
            {([{ value: "", label: "All" }, { value: "non_auto", label: "Non-Auto Grocery" }, { value: "auto", label: "Auto Grocery" }] as const).map((filter) => <button key={filter.value} type="button" aria-pressed={grocery === filter.value} onClick={() => setGrocery(filter.value)} className={`min-h-11 rounded-lg border px-3 py-1.5 text-xs font-bold uppercase tracking-wide ${grocery === filter.value ? "border-forest-line bg-forest-900 text-white" : "border-warm-200 bg-white text-warm-500 hover:border-warm-300"}`}>{filter.label}</button>)}
          </div>
          <InfoHint label="About Auto Grocery">Auto Grocery items need a measured recipe amount in a matching unit. Other items stay in recipes and can be added to a shopping list by hand.</InfoHint>
          <InfoHint label="About package units">For fixed packages, add the size to the name (e.g., “Soy sauce (900 g)”). Use a matching unit like pc or pack in inventory and recipes so Auto Grocery shows how many to buy, not just weight.</InfoHint>
        </div>}
      </div>

      <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-x-auto">
        {loading ? (
          <div className="py-16 text-center text-sm text-warm-400">Loading…</div>
        ) : items.length === 0 ? (
          <div className="py-16 text-center text-sm text-warm-400">No items yet.</div>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-warm-50 border-b border-warm-100">
              <tr>
                {(isSupply
                  ? ["Name", "Vendor", "Cost/unit", "Actions"]
                  : ["Name", "Vendor", "Unit", "Cost/unit", "Shopping list behavior", "Actions"]
                ).map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100">
              {items.map((it) => (
                <tr key={it.id} className="hover:bg-warm-50/60">
                  <td className="px-4 py-3 font-semibold text-warm-800">
                    {it.name}
                  </td>
                  <td className="px-4 py-3 text-warm-500">{it.vendor ?? "—"}</td>
                  {!isSupply && <td className="px-4 py-3 text-warm-500">{it.base_unit}</td>}
                  <td className="px-4 py-3 text-warm-700 font-mono">
                    {peso(it.purchase_price)}
                  </td>
                  {!isSupply && <td className="px-4 py-3 text-warm-500">{it.include_in_generated_lists ? "Auto grocery" : "Purchase when needed"}</td>}
                  <td className="px-4 py-3">
                    {isRnd ? (
                      <div className="flex items-center gap-1">
                        <button onClick={() => { setEditing(it); setModalOpen(true); }} className="p-1.5 rounded-lg text-warm-500 hover:text-emerald-600 hover:bg-emerald-50 cursor-pointer" title="Edit" aria-label={`Edit ${it.name}`}><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={() => remove(it)} className="p-1.5 rounded-lg text-warm-500 hover:text-red-600 hover:bg-red-50 cursor-pointer" title="Delete" aria-label={`Delete ${it.name}`}><Trash2 className="h-3.5 w-3.5" /></button>
                      </div>
                    ) : <span className="text-warm-300">—</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        <Pagination meta={meta} page={page} onPageChange={setPage} />
      </div>

      {modalOpen && isRnd && (
        <ItemFormModal
          kind={tab}
          editing={editing}
          suppliers={suppliers}
          initialDraft={restoredDraft}
          initialToken={restoredToken}
          onClose={closeModal}
          onSaved={() => { closeModal(); void load(true); }}
        />
      )}
      </>}
    </div>
  );
}
