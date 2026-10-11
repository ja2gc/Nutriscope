"use client";

import React, { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import {
  ShoppingBag, Plus, Trash2, ChevronLeft, ChevronDown, Split,
  FileText, Pencil, Check, Search, X,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import {
  ShoppingList, PurchaseOrder, POAttachment, ShoppingListItem, ShoppingListDraftItem,
  listShoppingLists, getShoppingList, generateByDates, deleteShoppingList,
  updateListItem, approveShoppingList, listPurchaseOrders, getPurchaseOrder,
  deletePurchaseOrder, deleteAttachment,
  createShoppingList, updateShoppingList,
  updateVendorGroup, uploadVendorGroupAttachments,
  MissingMenuDaysError,
} from "@/services/procurementService";
import { listAllSuppliers, Supplier } from "@/services/supplierService";
import { searchCatalog, type CatalogItem } from "@/services/fsCatalogService";
import { SuppliersPanel } from "@/components/foodservice/SuppliersPanel";
import { ImageUploadGallery, type UploadImage } from "@/components/ui/ImageUploadGallery";
import { DatePicker } from "@/components/ui/DatePicker";
import { InfoHint } from "@/components/ui/InfoHint";
import { VendorChangeControls } from "@/components/foodservice/VendorChangeControls";
import { PurchaseValueComparison } from "@/components/foodservice/PurchaseValueComparison";
import { ShoppingListCopyCard } from "@/components/foodservice/ShoppingListCopyCard";
import { formatFoodServiceNumber } from "@/lib/foodServiceFormat";
import { compatibleRecipeUnits, foodServiceUnitFactor } from "@/lib/foodServiceUnits";

const peso = (n: number) => `₱${n.toFixed(2)}`;
const num = (s: string | number | null | undefined) => (s != null ? parseFloat(String(s)) : 0);
const purchaseNumber = (value: string | number | null | undefined) => String(Number(num(value).toFixed(6)));
const poStatusLabel = (status: PurchaseOrder["lifecycle_status"]) => ({
  open_execution: "In progress",
  completed: "Completed",
  archived: "Archived",
}[status]);
const today = () => new Date().toISOString().slice(0, 10);
const attachmentImages = (attachments: POAttachment[] | undefined | null, type: "receipt" | "proof"): UploadImage[] =>
  (attachments ?? []).filter((a) => a.type === type).map((a) => ({
    id: String(a.id),
    name: a.caption ?? `${type} ${a.id}`,
    src: a.url,
  }));
const spanLabel = (list?: ShoppingList) =>
  list?.period_start && list?.period_end
    ? `${list.period_start} → ${list.period_end}`
    : list?.list_date ?? "Manual";

function Crumbs({ children }: { children?: React.ReactNode }) {
  return (
    <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
      <Link href="/dashboard" className="hover:text-emerald-700">Home</Link><span>/</span>
      <span>Food Service</span><span>/</span><span className="font-bold text-warm-600">Procurement</span>
      {children}
    </div>
  );
}

// ═══ Shopping list detail ═════════════════════════════════════════════════════
function ListDetail({ id, initialDraft, suppliers, onBack, onSaved, onPosGenerated }: {
  id: string | null; initialDraft?: ShoppingList | null; suppliers: Supplier[];
  onBack: () => void; onSaved: (saved: ShoppingList) => void; onPosGenerated: () => void;
}) {
  const [list, setList] = useState<ShoppingList | null>(null);
  const [savedSnapshot, setSavedSnapshot] = useState("");
  const [confirmMode, setConfirmMode] = useState<"back" | "first-save" | null>(null);
  const [closeAfterSave, setCloseAfterSave] = useState(false);
  const [saveError, setSaveError] = useState("");
  const [busy, setBusy] = useState(false);
  const [itemSearch, setItemSearch] = useState("");
  const [itemResults, setItemResults] = useState<CatalogItem[]>([]);
  const [selectedItem, setSelectedItem] = useState<CatalogItem | null>(null);
  const [addQty, setAddQty] = useState("1");
  const [addUnitPrice, setAddUnitPrice] = useState("0");
  const [addUnitPriceEdited, setAddUnitPriceEdited] = useState(false);
  const [addSupplier, setAddSupplier] = useState("");
  const [itemError, setItemError] = useState("");
  const [approveErr, setApproveErr] = useState("");
  const [editingName, setEditingName] = useState(false);
  const [nameDraft, setNameDraft] = useState("");
  const [addingItem, setAddingItem] = useState(false);
  const itemSearchRequest = useRef(0);

  const load = useCallback(() => {
    if (id) {
      getShoppingList(id).then((saved) => { setList(saved); setSavedSnapshot(JSON.stringify(saved)); });
    } else if (initialDraft) {
      setList(initialDraft);
      setSavedSnapshot(JSON.stringify(initialDraft));
    }
  }, [id, initialDraft]);
  useEffect(() => { load(); }, [load]);

  function patchItem(itemId: string, patch: Parameters<typeof updateListItem>[1]) {
    setList((current) => current ? { ...current, items: current.items.map((item) => {
      if (item.id !== itemId) return item;
      let next: ShoppingListItem = { ...item, ...patch as Partial<ShoppingListItem> };
      if (patch.purchase_unit && patch.purchase_unit !== (item.purchase_unit ?? item.unit)) {
        const factor = foodServiceUnitFactor(item.purchase_unit ?? item.unit, patch.purchase_unit);
        if (factor !== null) {
          const quantity = num(item.purchase_qty ?? item.qty) * factor;
          next = { ...next, purchase_qty: purchaseNumber(quantity),
            purchase_price: quantity > 0 ? purchaseNumber(num(item.total) / quantity) : "0" };
        }
      }
      if (patch.quantity_mode && patch.quantity_mode !== "manual") {
        const factor = foodServiceUnitFactor(item.unit, next.purchase_unit ?? item.unit);
        const calculated = num(item.qty) * (factor ?? 1);
        next.purchase_qty = purchaseNumber(patch.quantity_mode === "rounded" ? Math.round(calculated) : calculated);
      }
      if (patch.purchase_qty !== undefined) next.quantity_mode = "manual";
      next.total = (num(next.purchase_qty ?? next.qty) * num(next.purchase_price ?? next.unit_price)).toFixed(2);
      return next;
    }) } : current);
  }

  async function searchItems(q: string) {
    const requestId = ++itemSearchRequest.current;
    setItemSearch(q);
    setSelectedItem(null);
    if (q.trim().length < 2) { setItemResults([]); return; }
    setItemError("");
    try {
      const result = await searchCatalog(
        q,
        list?.procurement_track === "supplies" ? "supply" : "ingredient",
      );
      if (requestId === itemSearchRequest.current) setItemResults(result.slice(0, 5));
    } catch (e) {
      if (requestId === itemSearchRequest.current) {
        setItemResults([]);
        setItemError(e instanceof Error ? e.message : "Failed to search Inventory.");
      }
    }
  }

  async function showInitialItemSuggestions() {
    if (list?.procurement_track === "supplies" || itemSearch.trim() || selectedItem) return;

    const requestId = ++itemSearchRequest.current;
    setItemError("");
    try {
      const result = await searchCatalog("", "ingredient", 5, "non_auto");
      if (requestId === itemSearchRequest.current) {
        setItemResults(result);
      }
    } catch (e) {
      if (requestId === itemSearchRequest.current) {
        setItemResults([]);
        setItemError(e instanceof Error ? e.message : "Failed to search Inventory.");
      }
    }
  }

  function selectManualItem(item: CatalogItem) {
    setSelectedItem(item);
    setItemSearch(item.name);
    setItemResults([]);
    setAddUnitPrice(String(item.unit_cost ?? item.purchase_price ?? "0"));
    setAddUnitPriceEdited(false);
    setAddSupplier("");
  }

  async function addManualItem() {
    if (!list || addingItem) return;
    const qty = parseFloat(addQty);
    const unitPrice = parseFloat(addUnitPrice);
    if (!selectedItem || !Number.isFinite(qty) || qty <= 0) {
      setItemError("Pick an item and enter a quantity.");
      return;
    }
    setItemError("");
    setAddingItem(true);
    try {
      const unit = selectedItem.base_unit || "unit";
      const price = Number.isFinite(unitPrice) ? unitPrice : 0;
      const created: ShoppingListItem = {
        id: `draft:${crypto.randomUUID()}`,
        fs_item_id: null,
        fs_item_uuid: selectedItem.id,
        inventory_unit: unit,
        ingredient_name: selectedItem.name,
        qty: String(qty), unit, unit_price: String(price),
        purchase_qty: String(qty), purchase_unit: unit, purchase_price: String(price),
        total: (qty * price).toFixed(2), supplier_id: addSupplier || null,
        source: "manual", item_type: isSupplies ? "supply" : "ingredient",
        included_in_po: true, exclusion_note: null, quantity_mode: "manual",
      };
      setList({ ...list, items: [...list.items, created] });
      setItemSearch("");
      setSelectedItem(null);
      setAddQty("1");
      setAddUnitPrice("0");
      setAddUnitPriceEdited(false);
      setAddSupplier("");
    } catch (e) {
      setItemError(e instanceof Error ? e.message : "Failed to add Inventory item.");
    } finally {
      setAddingItem(false);
    }
  }

  function removeItem(itemId: string) {
    setList((current) => current ? { ...current, items: current.items.filter((item) => item.id !== itemId) } : current);
  }

  async function doGeneratePos() {
    setBusy(true);
    try { if (id) { await approveShoppingList(id); onPosGenerated(); } }
    catch (e) { setApproveErr(e instanceof Error ? e.message : "Failed to approve."); }
    finally { setBusy(false); }
  }

  function saveName() {
    if (!list || !nameDraft.trim()) return;
    setList({ ...list, name: nameDraft.trim() });
    setEditingName(false);
  }

  async function saveList(closeAfter = false) {
    if (!list) return;
    setBusy(true);
    setSaveError("");
    try {
      const items: ShoppingListDraftItem[] = list.items.map((item) => ({
        ...(id && !item.id.startsWith("draft:") ? { id: item.id } : {}),
        fs_item_uuid: item.fs_item_uuid ?? "",
        source: item.source,
        purchase_qty: num(item.purchase_qty ?? item.qty),
        purchase_unit: item.purchase_unit ?? item.unit,
        purchase_price: num(item.purchase_price ?? item.unit_price),
        supplier_id: item.supplier_id ? String(item.supplier_id) : null,
        quantity_mode: item.quantity_mode ?? "calculated",
        included_in_po: item.included_in_po,
        exclusion_note: item.exclusion_note,
      }));
      const saved = id
        ? await updateShoppingList(id, { name: list.name, items })
        : await generateByDates(list.period_start!, list.period_end!, list.estimate_population!, list.name, { items });
      setConfirmMode(null);
      setList(saved);
      setSavedSnapshot(JSON.stringify(saved));
      onSaved(saved);
      if (closeAfter) onBack();
    } catch (error) {
      setSaveError(error instanceof Error ? error.message : "Could not save Shopping List.");
    } finally { setBusy(false); }
  }

  const dirty = !id || JSON.stringify(list) !== savedSnapshot;
  function requestBack() {
    if (dirty) setConfirmMode("back");
    else onBack();
  }

  if (!list) return <div className="py-16 text-center text-sm text-warm-400">Loading…</div>;

  const isSupplies = list.procurement_track === "supplies";
  const estimatedTotal = list.items.filter((item) => item.included_in_po).reduce((sum, item) => sum + num(item.total), 0);
  const budgetPerHeadPerDay = list.estimate_population && list.days_span
    ? estimatedTotal / (list.estimate_population * list.days_span) : null;

  return (
    <div className="space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <div className="flex items-center gap-3">
          <button onClick={requestBack} aria-label="Back to Shopping Lists" className="p-2 rounded-lg border border-warm-200 hover:bg-warm-50 text-warm-500 cursor-pointer">
            <ChevronLeft className="h-4 w-4" />
          </button>
          <div>
            {editingName ? (
              <div className="flex items-center gap-2">
                <input aria-label="Shopping list name" value={nameDraft} onChange={(e) => setNameDraft(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") saveName(); }} className="rounded-lg border border-warm-200 px-2 py-1 text-lg font-extrabold text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                <button onClick={saveName} aria-label="Apply name" className="rounded-lg p-2 text-emerald-600 hover:bg-emerald-50"><Check className="h-4 w-4" /></button>
                <button onClick={() => { setNameDraft(list.name); setEditingName(false); }} aria-label="Cancel rename" className="rounded-lg p-2 text-warm-500 hover:bg-warm-100"><X className="h-4 w-4" /></button>
              </div>
            ) : (
              <div className="flex items-center gap-2">
                <h3 className="text-lg font-extrabold text-warm-900">{list.name}</h3>
                {list.status === "draft" && <button onClick={() => { setNameDraft(list.name); setEditingName(true); }} aria-label={`Rename ${list.name}`} className="rounded-lg p-1.5 text-warm-400 hover:bg-warm-100 hover:text-warm-700"><Pencil className="h-3.5 w-3.5" /></button>}
              </div>
            )}
            <div className="text-xs text-warm-400 flex items-center gap-2">
              {!isSupplies && list.list_type === "suggested" && (
                <span className="text-emerald-600">suggested</span>
              )}
              {!isSupplies && list.days_span && <span>· {list.days_span}-day span</span>}
              {isSupplies && <span>Supplies list</span>}
              <span>· {list.status}</span>
            </div>
          </div>
        </div>
        <div className="flex flex-col items-end gap-1">
          {list.status === "draft" && <Button variant="secondary" onClick={() => id ? void saveList() : setConfirmMode("first-save")} loading={busy} className="px-4 py-2">Save Shopping List</Button>}
          <Button
            variant="primary"
            onClick={doGeneratePos}
            loading={busy}
            disabled={!id || dirty || list.status === "converted" || !list.release_readiness?.ready}
            className="px-4 py-2 flex items-center gap-2"
          >
            <Split className="h-4 w-4" />
            {list.status === "converted" ? "PO released" : "Turn Into PO"}
          </Button>
          {!id || dirty ? <span className="text-xs text-warm-500">Save the list before Turn Into PO.</span> : null}
          {saveError && <span role="alert" className="text-xs text-red-600 font-semibold">{saveError}</span>}
          {approveErr && <span className="text-xs text-red-600 font-semibold">{approveErr}</span>}
        </div>
      </div>

      <ShoppingListCopyCard items={list.items} />

      {/* One estimate is captured when the suggested list is generated. */}
      {!isSupplies && list.period_start && list.period_end && (
        <div className="overflow-hidden rounded-2xl border border-warm-200 bg-white p-3 shadow-sm">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-5">
            <div className="min-w-0 flex flex-col gap-1">
              <span className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">
                Estimated population
              </span>
              <div className="break-words text-2xl font-extrabold text-warm-800">{list.estimate_population ?? "—"}</div>
            </div>

            <div className="min-w-0 flex flex-col gap-1">
              <span className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">
                Est. budget per head / day
              </span>
              <div className="break-words text-2xl font-extrabold text-warm-800">
                {budgetPerHeadPerDay != null ? peso(budgetPerHeadPerDay) : "—"}
              </div>
            </div>

            <div className="min-w-0 flex flex-col gap-1">
              <span className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">
                Total procurement cost
              </span>
              <div className="break-words text-2xl font-extrabold text-emerald-700">
                {peso(estimatedTotal)}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Total for supplies list */}
      {isSupplies && (
        <div className="flex items-center gap-3">
          <div className="px-4 py-2.5 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2">
            <span className="text-lg font-extrabold">{peso(estimatedTotal)}</span>
            <span className="opacity-70">total</span>
          </div>
        </div>
      )}

      {/* Manual additions work for food and supply lists. */}
      {list.status === "draft" && (
        <div className="bg-white border border-warm-200 rounded-2xl p-4 shadow-sm">
          <div className="text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-3">Add {isSupplies ? "supply" : "food"} item</div>
          <div className="grid grid-cols-1 md:grid-cols-[1.4fr_90px_110px_150px_auto] gap-3 items-end">
            <div className="relative">
              <label htmlFor="shopping-list-item-search" className="block text-xs font-extrabold text-warm-500 uppercase mb-1">Search {isSupplies ? "supplies" : "ingredients"}</label>
              <div className="flex items-center gap-2 px-3 py-2 border border-warm-200 rounded-lg">
                <Search className="h-3.5 w-3.5 text-warm-400" />
                <input
                  id="shopping-list-item-search"
                  value={itemSearch}
                  onChange={(e) => searchItems(e.target.value)}
                  onFocus={showInitialItemSuggestions}
                  className="w-full text-base outline-none"
                />
              </div>
              {itemResults.length > 0 && (
                <div className="absolute z-20 mt-1 w-full bg-white border border-warm-200 rounded-xl shadow-lg overflow-hidden">
                  {itemResults.map((item) => (
                    <button key={item.id} type="button" onClick={() => selectManualItem(item)}
                      className="w-full text-left px-3 py-2 hover:bg-warm-50 border-b border-warm-100 last:border-0 cursor-pointer">
                      <span className="block text-sm font-bold text-warm-900">{item.name}</span>
                      <span className="text-xs text-warm-400">
                        {item.base_unit} · {item.unit_cost ? peso(num(item.unit_cost)) : "no price"}
                      </span>
                    </button>
                  ))}
                </div>
              )}
            </div>
            <div>
              <label className="block text-xs font-extrabold text-warm-500 uppercase mb-1">Qty</label>
              <input type="number" min="0" step="any" value={addQty}
                onChange={(e) => setAddQty(e.target.value)}
                className="w-full px-3 py-2 text-base border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            <div>
              <label className="block text-xs font-extrabold text-warm-500 uppercase mb-1">Cost/unit (₱)</label>
              <input type="number" min="0" step="any" value={addUnitPriceEdited ? addUnitPrice : formatFoodServiceNumber(addUnitPrice)}
                onChange={(e) => { setAddUnitPriceEdited(true); setAddUnitPrice(e.target.value); }}
                className="w-full px-3 py-2 text-base border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            <div>
              <label className="block text-xs font-extrabold text-warm-500 uppercase mb-1">Vendor</label>
              <select value={addSupplier} onChange={(e) => setAddSupplier(e.target.value)}
                className="w-full px-3 py-2 text-base border border-warm-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                <option value="">— vendor —</option>
                {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </div>
            <Button variant="secondary" onClick={addManualItem} loading={addingItem} disabled={!selectedItem || addingItem} className="px-4 py-2 flex items-center gap-2">
              <Plus className="h-4 w-4" /> Add
            </Button>
          </div>
          {itemError && <p role="alert" className="mt-2 text-xs font-semibold text-red-600">{itemError}</p>}
        </div>
      )}

      {/* Cart table */}
      <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-x-auto">
        <div className="flex items-center gap-1 px-3 pt-2 text-xs text-warm-500">Quantity options<InfoHint label="About Shopping List quantities" title="Shopping List quantities">Calculated need follows Estimated servings. Round to the nearest whole unit or enter a purchase quantity. Changing either does not change the Food recipe or Estimated servings.</InfoHint></div>
        <table className="w-full text-sm">
          <thead className="bg-warm-50 border-b border-warm-100">
            <tr>
              {["Buy", "Item", "Qty", "Purchase Qty", "Unit", "Vendor", "Cost/unit", "Total", ""].map((h) => (
                <th key={h} className="px-3 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {list.items.length === 0 ? (
              <tr><td colSpan={9} className="px-3 py-8 text-center text-warm-400">No items yet.</td></tr>
            ) : list.items.map((it) => (
              <tr key={it.id} className={`hover:bg-warm-50/60 ${it.included_in_po ? "" : "opacity-55"}`}>
                <td className="px-3 py-2">
                  <input type="checkbox" checked={it.included_in_po} disabled={list.status === "converted"}
                    onChange={(e) => patchItem(it.id, { included_in_po: e.target.checked })}
                    className="h-4 w-4 accent-emerald-600" aria-label={`Include ${it.ingredient_name} in PO`} />
                </td>
                <td className="px-3 py-2 font-semibold text-warm-800">
                  {it.ingredient_name}
                  {!it.included_in_po && <input defaultValue={it.exclusion_note ?? ""}
                    disabled={list.status === "converted"} onBlur={(e) => patchItem(it.id, { exclusion_note: e.target.value || null })}
                    className="mt-1 block w-40 rounded border border-warm-200 px-2 py-1 text-xs font-normal" />}
                </td>
                <td className="px-3 py-2 font-mono text-warm-700">{purchaseNumber(it.qty)} {it.unit}</td>
                <td className="px-3 py-2"><input type="number" min="0" step="any" key={`${it.id}:purchase-qty:${purchaseNumber(it.purchase_qty ?? it.qty)}`} defaultValue={purchaseNumber(it.purchase_qty ?? it.qty)} disabled={list.status === "converted"}
                  onChange={(e) => { e.currentTarget.dataset.userEdited = "true"; }}
                  onBlur={(e) => {
                    const input = e.currentTarget;
                    if (input.dataset.userEdited !== "true") return;
                    const value = parseFloat(input.value);
                    if (!Number.isFinite(value)) return;
                    delete input.dataset.userEdited;
                    patchItem(it.id, isSupplies ? { qty: value } : { purchase_qty: value });
                  }}
                  className="w-24 rounded border border-warm-200 px-2 py-1 disabled:bg-warm-50" />
                  {!isSupplies && <select aria-label={`Quantity mode for ${it.ingredient_name}`} value={it.quantity_mode ?? "calculated"} disabled={list.status === "converted"} onChange={(e) => patchItem(it.id, { quantity_mode: e.target.value as "calculated" | "rounded" | "manual" })} className="mt-1 w-28 rounded border border-warm-200 bg-white text-xs"><option value="calculated">Calculated</option><option value="rounded">Round nearest</option><option value="manual">Manual</option></select>}</td>
                <td className="px-3 py-2">
                  <select value={it.purchase_unit ?? it.unit} disabled={list.status === "converted" || isSupplies}
                    onChange={(e) => patchItem(it.id, { purchase_unit: e.target.value })}
                    className="w-24 rounded border border-warm-200 bg-white px-2 py-1 disabled:bg-warm-50">
                    {[it.purchase_unit ?? it.unit, ...compatibleRecipeUnits(it.inventory_unit ?? it.unit, true)]
                      .filter((unit, index, units) => units.indexOf(unit) === index)
                      .map((unit) => <option key={unit} value={unit}>{unit}</option>)}
                  </select>
                </td>
                <td className="px-3 py-2">
                  <select
                    value={it.supplier_id ?? ""}
                    disabled={list.status === "converted"}
                    onChange={(e) => patchItem(it.id, { supplier_id: e.target.value ? e.target.value : null })}
                    className="px-2 py-1 border border-warm-200 rounded bg-white text-warm-700 focus:outline-none focus:ring-1 focus:ring-emerald-400 cursor-pointer disabled:bg-warm-50 disabled:text-warm-400"
                  >
                    <option value="">— vendor —</option>
                    {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </select>
                </td>
                <td className="px-3 py-2">
                  <input
                    type="number"
                    key={`${it.id}:unit-price:${purchaseNumber(it.purchase_price ?? it.unit_price)}`}
                    defaultValue={purchaseNumber(it.purchase_price ?? it.unit_price)}
                    step="any"
                    disabled={list.status === "converted"}
                    onChange={(e) => { e.currentTarget.dataset.userEdited = "true"; }}
                    onBlur={(e) => {
                      const input = e.currentTarget;
                      if (input.dataset.userEdited !== "true") return;
                      const value = parseFloat(input.value);
                      if (!Number.isFinite(value)) return;
                      delete input.dataset.userEdited;
                      patchItem(it.id, isSupplies ? { unit_price: value } : { purchase_price: value });
                    }}
                    className="w-20 px-2 py-1 border border-warm-200 rounded focus:outline-none focus:ring-1 focus:ring-emerald-400 disabled:bg-warm-50 disabled:text-warm-400"
                  />
                </td>
                <td className="px-3 py-2 font-mono text-emerald-700">{peso(num(it.total))}</td>
                <td className="px-3 py-2">
                  {it.source === "manual" && (
                    <button
                      onClick={() => removeItem(it.id)}
                      disabled={list.status === "converted"}
                      className="p-1.5 rounded-lg hover:bg-red-50 text-warm-500 hover:text-red-600 cursor-pointer disabled:opacity-40"
                      aria-label={`Remove ${it.ingredient_name}`}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
          {list.items.length > 0 && (
            <tfoot className="bg-warm-50 border-t border-warm-200">
              <tr>
                <td colSpan={7} className="px-3 py-2.5 text-sm font-bold text-warm-500 text-right uppercase tracking-wider">Included total</td>
                <td className="px-3 py-2.5 font-mono font-bold text-emerald-700">{peso(estimatedTotal)}</td>
                <td />
              </tr>
            </tfoot>
          )}
        </table>
      </div>
      <p className="text-xs text-warm-400">
        Inventory suggests the Vendor from the latest completed PO.
        Converting to a PO creates one purchase order grouped by vendor.
      </p>
      {confirmMode && <div role="dialog" aria-modal="true" aria-label={confirmMode === "back" ? "Unsaved Shopping List" : "Save Shopping List"} className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl space-y-4">
          {confirmMode === "first-save" ? <>
            <h3 className="text-lg font-bold text-warm-900">Save Shopping List?</h3>
            <p className="text-sm text-warm-600">This list uses the current estimated servings for the selected Menu Calendar service days. You can keep editing it after saving. Saving does not create a purchase order; Turn Into PO is a separate action.</p>
            <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => { setConfirmMode(null); setCloseAfterSave(false); }}>Keep Editing</Button><Button variant="primary" onClick={() => void saveList(closeAfterSave)} loading={busy}>Save Shopping List</Button></div>
          </> : <>
            <h3 className="text-lg font-bold text-warm-900">Unsaved Shopping List</h3>
            <p className="text-sm text-warm-600">Save your changes before leaving?</p>
            <div className="flex justify-end gap-2"><Button variant="secondary" onClick={onBack}>Discard</Button><Button variant="primary" onClick={() => { if (!id) { setCloseAfterSave(true); setConfirmMode("first-save"); } else void saveList(true); }} loading={busy}>Save</Button></div>
          </>}
          {saveError && <p role="alert" className="text-sm text-red-600">{saveError}</p>}
        </div>
      </div>}
    </div>
  );
}

// ═══ PO detail ════════════════════════════════════════════════════════════════
function PurchaseEventDetailView({ po, suppliers, onBack, reload }: { po: PurchaseOrder; suppliers: Supplier[]; onBack: () => void; reload: () => void }) {
  const [plannedTotalOpen, setPlannedTotalOpen] = useState(false);
  const [groupId, setGroupId] = useState<string | null>(null);
  const [orDraft, setOrDraft] = useState("");
  const [busy, setBusy] = useState(false);
  const [uploadingKey, setUploadingKey] = useState<string | null>(null);
  const [deletingImageId, setDeletingImageId] = useState<string | null>(null);
  const [imageError, setImageError] = useState<string | null>(null);
  const [actualError, setActualError] = useState<string | null>(null);
  const [actualDraft, setActualDraft] = useState<Record<number, { qty: string; price: string; total: string; totalEdited: boolean }>>({});
  const group = po.vendor_groups?.find((g) => g.id === groupId) ?? null;
  const locked = po.lifecycle_status !== "open_execution";

  useEffect(() => { setOrDraft(group?.or_number ?? ""); }, [group?.id, group?.or_number]);
  useEffect(() => {
    setActualDraft(Object.fromEntries((group?.items ?? []).map((item) => [item.id, {
      qty: String(num(item.actual_qty)),
      price: String(num(item.actual_unit_price)),
      total: String(num(item.actual_total)),
      totalEdited: false,
    }])));
  }, [group]);

  async function saveGroup(next: Parameters<typeof updateVendorGroup>[1]) {
    if (!group) return;
    setBusy(true);
    setActualError(null);
    try { await updateVendorGroup(group.id, next); reload(); }
    catch (error) { setActualError(error instanceof Error ? error.message : "Could not save actual values."); }
    finally { setBusy(false); }
  }

  async function saveActuals(markReceived = false) {
    if (!group) return;
    const items = (group.items ?? []).map((item) => {
      const draft = actualDraft[item.id];
      return {
        id: item.id,
        ...(draft?.totalEdited
          ? { receipt_total: parseFloat(draft.total) }
          : { actual_qty: parseFloat(draft?.qty ?? item.actual_qty) }),
        actual_unit_price: parseFloat(draft?.price ?? item.actual_unit_price),
      };
    });
    if (items.some((item) => !Number.isFinite(item.actual_unit_price) || item.actual_unit_price <= 0 ||
      ("receipt_total" in item ? !Number.isFinite(item.receipt_total) || item.receipt_total < 0 :
        !Number.isFinite(item.actual_qty) || item.actual_qty < 0))) {
      setActualError("Enter a valid quantity, unit price, and total for each item.");
      return;
    }
    await saveGroup({ items, ...(markReceived ? { status: "received" as const } : {}) });
  }
  async function uploadGroupFiles(type: "receipt" | "proof", files: File[]) {
    if (!group || files.length === 0) return;
    setUploadingKey(`${group.id}:${type}`);
    setImageError(null);
    try {
      await uploadVendorGroupAttachments(group.id, files, type);
      reload();
    } catch (e) {
      setImageError(e instanceof Error ? e.message : "Upload failed.");
    } finally {
      setUploadingKey(null);
    }
  }
  async function syncImages(type: "receipt" | "proof", next: UploadImage[]) {
    if (!group) return;
    const keep = new Set(next.map((image) => image.id));
    const removed = (group.attachments ?? []).filter((a) => a.type === type && !keep.has(String(a.id)));
    if (removed.length === 0) return;
    setDeletingImageId(String(removed[0].id));
    setImageError(null);
    try {
      await Promise.all(removed.map((a) => deleteAttachment(a.id)));
      reload();
    } catch (e) {
      setImageError(e instanceof Error ? e.message : "Delete failed.");
    } finally { setDeletingImageId(null); }
  }

  if (group) {
    return (
      <div className="space-y-5">
        <div className="flex items-center gap-2 text-sm font-semibold text-warm-400">
          <button onClick={() => setGroupId(null)} className="hover:text-emerald-700">Procurement</button>
          <span>/</span><span>{po.po_number}</span><span>/</span>
          <span className="text-warm-700">{group.supplier?.name ?? "Unspecified"}</span>
        </div>
        <div className="flex items-center gap-3">
          <button onClick={() => setGroupId(null)} className="p-2 rounded-lg border border-warm-200 hover:bg-warm-50 text-warm-500 cursor-pointer">
            <ChevronLeft className="h-4 w-4" />
          </button>
          <div>
            <h3 className="text-lg font-extrabold text-warm-900">{group.supplier?.name ?? "Unspecified"}</h3>
            <div className="text-xs text-warm-400">Status: {group.status === "received" ? "Received" : "Pending"} · Total {peso(num(group.total_amount))}</div>
          </div>
        </div>

        <div className="rounded-2xl border border-warm-200 bg-white p-4 shadow-sm">
          <div className="mb-2 text-xs font-extrabold uppercase tracking-wider text-warm-500">Vendor for this group</div>
          <VendorChangeControls
            group={group}
            vendors={suppliers}
            disabled={locked}
            onChanged={() => { setGroupId(null); reload(); }}
          />
        </div>

        <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3 items-end">
          <label className="block">
            <span className="block text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-1">OR number (optional)</span>
            <input value={orDraft} onChange={(e) => setOrDraft(e.target.value)} disabled={locked}
              className="w-full px-3 py-2 text-base border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:bg-warm-50 disabled:text-warm-400" />
          </label>
          <Button variant="ghost" onClick={() => saveGroup({ or_number: orDraft || null })} loading={busy} disabled={locked} className="px-4 py-2">Save</Button>
        </div>

        <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-warm-50 border-b border-warm-100">
              <tr>{["Item", "Actual Quantity", "Unit", "Actual Cost/unit", "Actual Total", "Actions"].map((h) => (
                <th key={h} className="px-3 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider">{h}</th>
              ))}</tr>
            </thead>
            <tbody className="divide-y divide-zinc-100">
              {(group.items ?? []).map((item) => {
                const draft = actualDraft[item.id];
                const actualPrice = num(draft?.price ?? item.actual_unit_price);
                const actualQty = draft?.totalEdited && actualPrice > 0
                  ? num(draft.total) / actualPrice
                  : num(draft?.qty ?? item.actual_qty);
                return <tr key={item.id}>
                  <td className="px-3 py-3">
                    <div className="font-semibold text-warm-800">{item.description}</div>
                  </td>
                  <td className="px-3 py-2"><input type="number" min="0" step="1" disabled={locked}
                    value={draft?.totalEdited && actualPrice > 0 ? String(Math.round(num(draft.total) / actualPrice * 1000) / 1000) : draft?.qty ?? String(num(item.actual_qty))}
                    onChange={(e) => setActualDraft((current) => {
                      const currentDraft = current[item.id] ?? { qty: String(num(item.actual_qty)), price: String(num(item.actual_unit_price)), total: String(num(item.actual_total)), totalEdited: false };
                      return { ...current, [item.id]: { ...currentDraft, qty: e.target.value, totalEdited: false } };
                    })}
                    className="w-24 rounded border border-warm-200 px-2 py-1 disabled:bg-warm-50" /></td>
                  <td className="px-3 py-2 text-warm-600" aria-label={`Unit for ${item.description}`}>{item.purchase_unit ?? item.unit}</td>
                  <td className="px-3 py-2">
                    <input type="number" min="0" step="any" disabled={locked}
                      value={draft?.price ?? String(num(item.actual_unit_price))}
                      onChange={(e) => setActualDraft((current) => {
                        const currentDraft = current[item.id] ?? { qty: String(num(item.actual_qty)), price: String(num(item.actual_unit_price)), total: String(num(item.actual_total)), totalEdited: false };
                        return { ...current, [item.id]: { ...currentDraft, price: e.target.value } };
                      })}
                      className="w-24 rounded border border-warm-200 px-2 py-1 disabled:bg-warm-50" />
                    <PurchaseValueComparison item={item} actualQty={String(actualQty)} actualPrice={String(actualPrice)} />
                  </td>
                  <td className="px-3 py-2"><input type="number" min="0" step="any" disabled={locked}
                    aria-label={`Actual Total for ${item.description}`}
                    value={draft?.totalEdited ? draft.total : (actualQty * actualPrice).toFixed(2)}
                    onChange={(e) => setActualDraft((current) => {
                      const currentDraft = current[item.id] ?? { qty: String(num(item.actual_qty)), price: String(num(item.actual_unit_price)), total: String(num(item.actual_total)), totalEdited: false };
                      return { ...current, [item.id]: { ...currentDraft, total: e.target.value, totalEdited: true } };
                    })}
                    className="w-24 rounded border border-warm-200 px-2 py-1 disabled:bg-warm-50" /></td>
                  <td className="px-3 py-2">
                    <VendorChangeControls
                      group={group}
                      vendors={suppliers}
                      itemId={item.id}
                      disabled={locked}
                      onChanged={() => { setGroupId(null); reload(); }}
                    />
                  </td>
                </tr>;
              })}
            </tbody>
            <tfoot className="bg-warm-50 border-t border-warm-200"><tr><td colSpan={4} className="px-3 py-3 text-right font-bold text-warm-600">Receipt Total</td><td className="px-3 py-3 font-mono font-bold text-emerald-700">{peso((group.items ?? []).reduce((sum, item) => {
              const draft = actualDraft[item.id];
              return sum + (draft?.totalEdited ? num(draft.total) : num(draft?.qty ?? item.actual_qty) * num(draft?.price ?? item.actual_unit_price));
            }, 0))}</td><td /></tr></tfoot>
          </table>
        </div>

        {!locked && <div className="flex flex-wrap items-end gap-3 rounded-2xl border border-warm-200 bg-white p-4">
          <Button variant="secondary" onClick={() => saveActuals(false)} loading={busy}>Save actual values</Button>
          <Button variant="primary" onClick={() => saveActuals(true)} loading={busy}
            disabled={!group.evidence_requirements?.receipt_uploaded || !group.evidence_requirements?.proof_uploaded}>
            Mark vendor received
          </Button>
          <span className="text-xs text-warm-500">Receipt and proof are required. OR number is not.</span>
          {actualError && <span role="alert" className="text-xs font-semibold text-red-600">{actualError}</span>}
        </div>}

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm">
            <ImageUploadGallery
              images={attachmentImages(group.attachments, "receipt")}
              onImagesChange={(images) => syncImages("receipt", images)}
              onFilesSelected={(files) => uploadGroupFiles("receipt", files)}
              label="Receipt images" emptyText="No receipt images yet."
              uploading={uploadingKey === `${group.id}:receipt`}
              deletingImageId={deletingImageId}
              error={imageError}
              disabled={locked}
              maxBytes={5 * 1024 * 1024}
              allowedTypes={["image/jpeg", "image/png", "image/webp"]}
              maxFiles={15}
              existingFilesCountTowardsLimit={false}
            />
          </div>
          <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm">
            <ImageUploadGallery
              images={attachmentImages(group.attachments, "proof")}
              onImagesChange={(images) => syncImages("proof", images)}
              onFilesSelected={(files) => uploadGroupFiles("proof", files)}
              label="Proof of purchase" emptyText="No proof photos yet."
              uploading={uploadingKey === `${group.id}:proof`}
              deletingImageId={deletingImageId}
              error={imageError}
              disabled={locked}
              maxBytes={5 * 1024 * 1024}
              allowedTypes={["image/jpeg", "image/png", "image/webp"]}
              maxFiles={15}
              existingFilesCountTowardsLimit={false}
            />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-3">
        <button onClick={onBack} className="p-2 rounded-lg border border-warm-200 hover:bg-warm-50 text-warm-500 cursor-pointer">
          <ChevronLeft className="h-4 w-4" />
        </button>
        <div>
          <h3 className="text-lg font-extrabold text-warm-900 flex items-center gap-2">
            <FileText className="h-4 w-4 text-emerald-600" />{po.po_number}
          </h3>
          <div className="text-xs text-warm-400">
            {po.shopping_list?.name ?? "Manual procurement"} · {po.po_number} · Status: {poStatusLabel(po.lifecycle_status)}
          </div>
        </div>
        {po.lifecycle_status !== "open_execution" && po.actual_budget_per_head_per_day != null && (
          <div className="ml-auto flex flex-col items-end">
            <span className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">Food purchase cost per served patient-day</span>
            <span className="text-2xl font-extrabold text-emerald-700">{peso(num(po.actual_budget_per_head_per_day))}</span>
            <span className="text-xs text-warm-400">final total ÷ total served population</span>
          </div>
        )}
      </div>
      <div className="overflow-hidden rounded-2xl border border-warm-200 bg-white shadow-sm">
        <Collapsible open={plannedTotalOpen} onOpenChange={setPlannedTotalOpen}>
          <CollapsibleTrigger asChild>
            <button type="button" aria-label={plannedTotalOpen ? "Hide planned total" : "Show planned total"}
              className="flex min-h-10 w-full items-center justify-between gap-2 px-4 py-2 text-left text-xs font-extrabold uppercase tracking-wider text-warm-600 hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500/30">
              <span>Planned total</span>
              <ChevronDown className={`h-4 w-4 shrink-0 text-warm-400 transition-transform ${plannedTotalOpen ? "rotate-180" : ""}`} aria-hidden="true" />
            </button>
          </CollapsibleTrigger>
          <CollapsibleContent data-testid="planned-total-content" className="border-t border-warm-100 px-4 py-3">
            <div className="text-2xl font-extrabold text-warm-900">{peso(num(po.total_amount))}</div>
          </CollapsibleContent>
        </Collapsible>
      </div>
      {po.served_population_progress && (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div className="bg-white border border-warm-200 rounded-xl p-4">
            <div className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">Served days</div>
            <div className="text-lg font-extrabold text-warm-900">
              {po.served_population_progress.done} / {po.served_population_progress.expected}
            </div>
          </div>
          <div className="bg-white border border-warm-200 rounded-xl p-4">
            <div className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">Total served population</div>
            <div className="text-lg font-extrabold text-warm-900">{po.served_population_progress.served}</div>
          </div>
          <div className="bg-white border border-warm-200 rounded-xl p-4">
            <div className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">Completion gate</div>
            <div className="text-sm font-semibold text-warm-600">
              {po.lifecycle_status === "completed"
                ? "Vendor evidence and served population complete"
                : "Needs each vendor received with receipt, proof, actual quantities and costs, and all span served populations"}
            </div>
          </div>
        </div>
      )}
      <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-warm-50 border-b border-warm-100">
            <tr>{["Vendor", "Items", "OR #", "Evidence", "Total", "Actions"].map((h) => (
              <th key={h} className="px-4 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider">{h}</th>
            ))}</tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {(po.vendor_groups ?? []).map((g) => (
              <tr key={g.id} className="hover:bg-warm-50/60">
                <td className="px-4 py-3 font-semibold text-warm-800">{g.supplier?.name ?? "Unassigned vendor"}</td>
                <td className="px-4 py-3 text-warm-500">{g.items?.length ?? 0}</td>
                <td className="px-4 py-3 text-warm-500">{g.or_number_display}</td>
                <td className="px-4 py-3 text-warm-500">
                  {g.status === "received" ? "complete" : "receipt + proof + actuals needed"}
                </td>
                <td className="px-4 py-3 font-mono text-warm-700">{peso(num(g.total_amount))}</td>
                <td className="px-4 py-3">
                  <button onClick={() => setGroupId(g.id)} className="text-sm font-semibold text-emerald-700 hover:underline cursor-pointer">
                    Open
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function PoDetail({ id, suppliers, onBack }: { id: string; suppliers: Supplier[]; onBack: () => void }) {
  const [po, setPo] = useState<PurchaseOrder | null>(null);
  const load = useCallback(() => { getPurchaseOrder(id).then(setPo); }, [id]);
  useEffect(() => { load(); }, [load]);
  if (!po) return <div className="py-16 text-center text-sm text-warm-400">Loading…</div>;
  return <PurchaseEventDetailView po={po} suppliers={suppliers} onBack={onBack} reload={load} />;
}

// ═══ ROOT ═════════════════════════════════════════════════════════════════════
export default function ProcurementPage() {
  const searchParams = useSearchParams();
  const targetPoId = searchParams.get("poId") || null;
  const [tab, setTab] = useState<"food-lists" | "supplies-lists" | "pos" | "suppliers">("food-lists");
  const [listDetail, setListDetail] = useState<string | null>(null);
  const [draftPreview, setDraftPreview] = useState<ShoppingList | null>(null);
  const [poDetail, setPoDetail] = useState<string | null>(null);

  const [lists, setLists] = useState<ShoppingList[]>([]);
  const [pos, setPos] = useState<PurchaseOrder[]>([]);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [genOpen, setGenOpen] = useState(false);
  const [genStartDate, setGenStartDate] = useState(today());
  const [genEndDate, setGenEndDate] = useState(today());
  const [genEstimate, setGenEstimate] = useState("");
  const [genName, setGenName] = useState("");
  const [genError, setGenError] = useState("");
  const [genMissing, setGenMissing] = useState<Record<string, string>>({});
  const [newListName, setNewListName] = useState("");
  const [manualTrack, setManualTrack] = useState<"food" | "supplies" | null>(null);
  const [manualListDate, setManualListDate] = useState("");
  const [manualListError, setManualListError] = useState("");
  const [listPage, setListPage] = useState(1);
  const [poPage, setPoPage] = useState(1);
  const [listMeta, setListMeta] = useState<PaginationMeta | null>(null);
  const [poMeta, setPoMeta] = useState<PaginationMeta | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [l, p, s] = await Promise.all([listShoppingLists(listPage), listPurchaseOrders(poPage), listAllSuppliers()]);
      setLists(l.data); setListMeta(l.meta); setPos(p.data); setPoMeta(p.meta); setSuppliers(s);
    } finally { setLoading(false); }
  }, [listPage, poPage]);
  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    if (!targetPoId || poDetail === targetPoId) return;
    setListDetail(null);
    setDraftPreview(null);
    setTab("pos");
    setPoDetail(targetPoId);
  }, [targetPoId, poDetail]);

  async function doGenerate() {
    if (!genStartDate || !genEndDate) return;
    if (!genName.trim()) {
      setGenError("Enter a name for the shopping list.");
      return;
    }
    if (genEndDate < genStartDate) {
      setGenError("End date must be on or after the start date.");
      return;
    }
    const estimate = parseInt(genEstimate, 10);
    if (!Number.isFinite(estimate) || estimate < 1) {
      setGenError("Enter the estimated servings for the selected span.");
      return;
    }
    setGenError(""); setGenMissing({});
    try {
      const list = await generateByDates(genStartDate, genEndDate, estimate, genName.trim(), { preview: true });
      setGenOpen(false);
      setTab("food-lists");
      setDraftPreview(list);
    } catch (e) {
      if (e instanceof MissingMenuDaysError) {
        setGenError(e.message);
        setGenMissing(e.missingByDate);
      } else {
        setGenError(e instanceof Error ? e.message : "Failed to generate list.");
      }
    }
  }

  async function createManualList(track: "food" | "supplies") {
    const name = track === "food" ? "Manual Food List" : newListName.trim();
    if (!name || !manualListDate) return;
    const created = await createShoppingList({
      name,
      list_type: "manual",
      procurement_track: track,
      status: "draft",
      list_date: manualListDate,
    });
    setLists((current) => [created, ...current]);
    if (track === "supplies") setNewListName("");
    setManualTrack(null);
    setManualListDate("");
    setManualListError("");
    setTab(track === "supplies" ? "supplies-lists" : "food-lists");
    setListDetail(created.id);
  }

  function openManualList(track: "food" | "supplies") {
    setManualTrack(track);
    setManualListDate("");
    setManualListError("");
  }

  async function removeList(id: string) {
    await deleteShoppingList(id);
    setLists((current) => current.filter((list) => list.id !== id));
  }

  async function removePo(id: string) {
    await deletePurchaseOrder(id);
    setPos((current) => current.filter((po) => po.id !== id));
  }

  const foodLists = lists.filter((list) => (list.procurement_track ?? "food") === "food");
  const suppliesLists = lists.filter((list) => list.procurement_track === "supplies");
  const visibleLists = tab === "supplies-lists" ? suppliesLists : foodLists;
  // POs tab shows all converted procurement events.

  if (listDetail || draftPreview) return (
    <div className="space-y-6 font-sans">
      <Crumbs />
      <ListDetail
        id={listDetail}
        initialDraft={draftPreview}
        suppliers={suppliers}
        onBack={() => { setListDetail(null); setDraftPreview(null); void load(); }}
        onSaved={(saved) => { setLists((current) => [saved, ...current.filter((list) => list.id !== saved.id)]); setListDetail(saved.id); setDraftPreview(null); }}
        onPosGenerated={() => { setListDetail(null); setDraftPreview(null); setTab("pos"); void load(); }}
      />
    </div>
  );
  if (poDetail) return (
    <div className="space-y-6 font-sans">
      <Crumbs />
      <PoDetail id={poDetail} suppliers={suppliers} onBack={() => { setPoDetail(null); void load(); }} />
    </div>
  );

  return (
    <div className="space-y-6 font-sans">
      <Crumbs />

      <div className="border-b border-warm-200 pb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
            <ShoppingBag className="h-5 w-5 text-emerald-600" /> Procurement
          </h2>
         </div>
         <div className="flex items-center gap-3 shrink-0">
          {tab === "supplies-lists" && (
            <div className="flex items-center gap-2">
              <input
                value={newListName}
                aria-label="Supplies list name"
                onChange={(e) => setNewListName(e.target.value)}
                onKeyDown={(e) => { if (e.key === "Enter") openManualList("supplies"); }}
                className="w-44 px-3 py-2 text-sm border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
                <Button variant="secondary" onClick={() => openManualList("supplies")} className="px-3 py-2 flex items-center gap-1.5">
                <Plus className="h-3.5 w-3.5" /> New supplies list
              </Button>
            </div>
          )}
          {tab === "food-lists" && (
            <div className="flex items-center gap-2">
              <Button variant="secondary" onClick={() => openManualList("food")} className="px-3 py-2"><Plus className="h-3.5 w-3.5" /> Manual food list</Button>
              <Button variant="primary" onClick={() => setGenOpen(true)} className="px-4 py-2.5 flex items-center gap-2">
                Suggest from Menu
              </Button>
            </div>
          )}
        </div>
      </div>

      {manualTrack && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
          <section role="dialog" aria-modal="true" aria-labelledby="manual-list-title" className="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-xl">
            <div>
              <h3 id="manual-list-title" className="text-base font-extrabold text-warm-900">New {manualTrack === "food" ? "manual food" : "supplies"} list</h3>
              <p className="mt-1 text-sm text-warm-500">Choose the procurement date for this list.</p>
            </div>
            <label className="block text-xs font-bold text-warm-600">
              Procurement date
              <DatePicker ariaLabel="Procurement date" required value={manualListDate} onChange={setManualListDate} />
            </label>
            {manualListError && <p role="alert" className="text-sm font-semibold text-red-600">{manualListError}</p>}
            <div className="flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setManualTrack(null)} className="px-4 py-2">Cancel</Button>
              <Button aria-label="Create manual shopping list" variant="primary" disabled={!manualListDate || (manualTrack === "supplies" && !newListName.trim())} onClick={() => {
                void createManualList(manualTrack).catch((error: unknown) => setManualListError(error instanceof Error ? error.message : "Failed to create list."));
              }} className="px-4 py-2">Create list</Button>
            </div>
          </section>
        </div>
      )}

      {/* Generate food list modal */}
      {genOpen && tab === "food-lists" && (
        <div className="bg-emerald-50/40 border border-emerald-100 rounded-2xl p-5 shadow-sm">
          <h3 className="mb-3 flex items-center gap-2 text-sm font-extrabold uppercase tracking-wider text-emerald-700">
            Suggested food shopping list
            <InfoHint label="About suggested lists">Review the suggested quantities before saving. Turn Into PO is a separate step and locks the list.</InfoHint>
          </h3>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div>
              <DatePicker label="From date" value={genStartDate} onChange={(next) => {
                setGenStartDate(next);
                if (genEndDate < next) setGenEndDate(next);
              }} />
            </div>
            <div>
              <DatePicker label="To date" value={genEndDate} min={genStartDate} onChange={setGenEndDate} />
            </div>
            <label className="block"><span className="mb-1 block text-xs font-bold text-warm-600">Estimated servings</span>
              <input type="number" min="1" value={genEstimate} onChange={(e) => setGenEstimate(e.target.value)}
                className="w-full rounded-lg border border-warm-200 px-3 py-2" />
            </label>
            <label className="block"><span className="mb-1 block text-xs font-bold text-warm-600">Shopping list name</span>
              <input aria-label="Shopping list name" required maxLength={255} value={genName} onChange={(e) => setGenName(e.target.value)}
                className="w-full rounded-lg border border-warm-200 px-3 py-2" />
            </label>
            <div className="flex items-end gap-2">
              <Button variant="primary" onClick={doGenerate} disabled={!genName.trim() || !genEstimate.trim()} className="px-4 py-2">Generate</Button>
              <button onClick={() => setGenOpen(false)} className="text-sm text-warm-500 hover:text-warm-700 cursor-pointer">Cancel</button>
            </div>
          </div>
          {genError && <p className="text-xs text-red-600 mt-2 font-semibold">{genError}</p>}
          {Object.keys(genMissing).length > 0 && (
            <div className="mt-2 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
              <div className="text-xs font-extrabold text-amber-800 mb-1">Fill these days before generating:</div>
              <ul className="space-y-0.5">
                {Object.entries(genMissing).sort(([a], [b]) => a.localeCompare(b)).map(([date, reason]) => (
                  <li key={date} className="text-xs text-amber-800">
                    <span className="font-semibold">{date}</span> — {reason}
                  </li>
                ))}
              </ul>
            </div>
          )}
          <p className="text-xs text-warm-400 mt-2">
            Sums all required ingredients for each day in the span. Generation is all-or-nothing: every day must have a menu cycle and menu items assigned, or the entire creation is blocked with the exact missing days listed above.
          </p>
        </div>
      )}

      {/* Tabs */}
      <div className="flex border-b border-warm-200">
        {([
          ["food-lists", "Food Shopping Lists"],
          ["supplies-lists", "Supplies Lists"],
          ["pos", "Purchase Orders"],
          ["suppliers", "Suppliers"],
        ] as const).map(([k, label]) => (
          <button key={k} onClick={() => setTab(k)}
            className={`px-5 py-3 text-base font-semibold border-b-2 transition-colors cursor-pointer ${
              tab === k ? "border-emerald-600 text-emerald-700" : "border-transparent text-warm-500 hover:text-warm-800"
            }`}>
            {label}
          </button>
        ))}
      </div>

      {tab === "suppliers" ? <SuppliersPanel /> : (
        <div className={`bg-white border border-warm-200 rounded-2xl shadow-sm ${tab === "pos" ? "overflow-x-auto" : ""}`}>
          {loading ? (
            <div className="py-16 text-center text-sm text-warm-400">Loading…</div>
          ) : (tab === "food-lists" || tab === "supplies-lists") ? (
            <>
            {
            visibleLists.length === 0 ? (
              <div className="py-16 text-center">
                <ShoppingBag className="h-8 w-8 text-warm-300 mx-auto mb-3" />
                <p className="text-sm text-warm-400 font-medium">
                  {tab === "food-lists"
                    ? "No food shopping lists yet. Click \"Suggest from Menu\" to generate one."
                    : "No supplies lists yet. Enter a name above and click \"New supplies list\"."}
                </p>
              </div>
            ) : (
              <table className="block w-full text-sm sm:table">
                <thead className="hidden bg-warm-50 border-b border-warm-100 sm:table-header-group">
                  <tr>
                    {["Name", tab === "food-lists" ? "Span" : "Date", "Items", "Status", "Actions"].map((h) => (
                      <th key={h} className="px-3 py-2 text-left text-xs font-bold text-warm-500 uppercase tracking-wider">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="block divide-y divide-zinc-100 sm:table-row-group">
                  {visibleLists.map((l) => (
                    <tr key={l.id} className="grid grid-cols-2 gap-x-4 gap-y-1 px-3 py-2 hover:bg-warm-50/60 sm:table-row sm:px-0 sm:py-0">
                      <td className="col-span-2 min-w-0 px-0 py-1 sm:table-cell sm:px-3 sm:py-2">
                        <span className="mb-1 block text-xs font-bold uppercase tracking-wider text-warm-400 sm:hidden">Name</span>
                        <span className="block min-w-0 truncate font-semibold text-warm-800">{l.name}</span>
                      </td>
                      <td className="flex items-center justify-between gap-2 px-0 py-1 text-warm-500 sm:table-cell sm:px-3 sm:py-2">
                        <span className="text-xs font-bold uppercase tracking-wider text-warm-400 sm:hidden">{tab === "food-lists" ? "Span" : "Date"}</span>
                        {tab === "food-lists"
                          ? (l.days_span ? `${spanLabel(l)} (${l.days_span}d)` : "—")
                          : (l.list_date ?? "—")}
                      </td>
                      <td className="flex items-center justify-between gap-2 px-0 py-1 text-warm-500 sm:table-cell sm:px-3 sm:py-2">
                        <span className="text-xs font-bold uppercase tracking-wider text-warm-400 sm:hidden">Items</span>{l.items.length}
                      </td>
                      <td className="flex items-center justify-between gap-2 px-0 py-1 sm:table-cell sm:px-3 sm:py-2">
                        <span className="text-xs font-bold uppercase tracking-wider text-warm-400 sm:hidden">Status</span>
                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold ${
                          l.status === "converted"
                            ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
                            : "bg-warm-100 text-warm-500"
                        }`}>
                          {l.status}
                        </span>
                      </td>
                      <td className="col-span-2 flex items-center justify-end gap-1 border-t border-warm-100 pt-1 sm:table-cell sm:border-0 sm:px-3 sm:py-2 sm:pt-2">
                        <div className="flex items-center gap-1">
                          <button type="button" onClick={() => setListDetail(l.id)}
                            className="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg text-warm-500 hover:bg-warm-100 hover:text-emerald-700 cursor-pointer"
                            aria-label={`Open list: ${l.name}`} title="Open list">
                            <Pencil className="h-3.5 w-3.5" />
                          </button>
                          <button type="button" onClick={() => removeList(l.id)} className="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg hover:bg-red-50 text-warm-500 hover:text-red-600 cursor-pointer" aria-label={`Delete ${l.name}`} title="Delete">
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
            </>
          ) : (
            pos.length === 0 ? (
              <div className="py-16 text-center">
                <FileText className="h-8 w-8 text-warm-300 mx-auto mb-3" />
                <p className="text-sm text-warm-400 font-medium">No purchase orders yet. Convert a shopping list to generate a PO.</p>
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead className="bg-warm-50 border-b border-warm-100">
                  <tr>{["Procurement span", "Track", "Estimated total", "Vendors", "Status", "Actions"].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider">{h}</th>
                  ))}</tr>
                </thead>
                <tbody className="divide-y divide-zinc-100">
                  {pos.map((po) => (
                    <tr key={po.id} className="hover:bg-warm-50/60">
                      <td className="px-4 py-3">
                        <span className="font-semibold text-warm-800">{po.po_number}</span>
                        <div className="text-xs text-warm-400">{po.order_date ?? "No order date"}</div>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-bold ${
                          po.procurement_track === "supplies"
                            ? "bg-amber-50 text-amber-700 border border-amber-200"
                            : "bg-emerald-50 text-emerald-700 border border-emerald-200"
                        }`}>
                          {po.procurement_track ?? "food"}
                        </span>
                      </td>
                      <td className="px-4 py-3 font-mono text-warm-700">
                        {peso(num(po.total_amount))}
                      </td>
                      <td className="px-4 py-3 text-warm-500">{po?.vendor_groups?.length ?? 0}</td>
                      <td className="px-4 py-3 text-warm-500">{poStatusLabel(po.lifecycle_status)}</td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-1">
                          <button
                            onClick={() => setPoDetail(po.id)}
                            className="p-1.5 rounded-lg hover:bg-warm-100 text-warm-500 cursor-pointer"
                            title="Edit purchase order"
                            aria-label={`Edit ${po.po_number}`}
                          >
                            <Pencil className="h-3.5 w-3.5" />
                          </button>
                          {po.lifecycle_status === "open_execution" && (
                            <button
                              onClick={() => removePo(po.id)}
                              className="p-1.5 rounded-lg hover:bg-red-50 text-warm-500 hover:text-red-600 cursor-pointer"
                              aria-label={`Delete ${po.po_number}`}
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )
          )}
          {tab !== "pos" && <Pagination meta={listMeta} page={listPage} onPageChange={setListPage} />}
          {tab === "pos" && <Pagination meta={poMeta} page={poPage} onPageChange={setPoPage} />}
        </div>
      )}
    </div>
  );
}
