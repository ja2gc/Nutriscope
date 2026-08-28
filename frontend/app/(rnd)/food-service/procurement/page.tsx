"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import {
  ShoppingBag, Plus, Trash2, RefreshCw, ChevronLeft, Sparkles, Split,
  FileText, Pencil, Check, Search, Eye,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import {
  ShoppingList, PurchaseOrder, POAttachment,
  listShoppingLists, getShoppingList, generateByDates, deleteShoppingList,
  updateListItem, approveShoppingList, listPurchaseOrders, getPurchaseOrder,
  deletePurchaseOrder, deleteAttachment,
  createShoppingList, updateShoppingList, addListItem, deleteListItem,
  updateVendorGroup, uploadVendorGroupAttachments,
  MissingMenuDaysError,
} from "@/services/procurementService";
import { listAllSuppliers, Supplier } from "@/services/supplierService";
import { searchCatalog, type CatalogItem } from "@/services/fsCatalogService";
import { AuditTrail } from "@/components/audit/AuditTrail";
import { SuppliersPanel } from "@/components/foodservice/SuppliersPanel";
import { ImageUploadGallery, type UploadImage } from "@/components/ui/ImageUploadGallery";
import { DatePicker } from "@/components/ui/DatePicker";
import { VendorChangeControls } from "@/components/foodservice/VendorChangeControls";
import { PurchaseValueComparison } from "@/components/foodservice/PurchaseValueComparison";

const peso = (n: number) => `₱${n.toFixed(2)}`;
const num = (s: string | number | null | undefined) => (s != null ? parseFloat(String(s)) : 0);
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
function ListDetail({ id, suppliers, onBack, onPosGenerated }: {
  id: string; suppliers: Supplier[]; onBack: () => void; onPosGenerated: () => void;
}) {
  const [list, setList] = useState<ShoppingList | null>(null);
  const [busy, setBusy] = useState(false);
  const [itemSearch, setItemSearch] = useState("");
  const [itemResults, setItemResults] = useState<CatalogItem[]>([]);
  const [selectedItem, setSelectedItem] = useState<CatalogItem | null>(null);
  const [addQty, setAddQty] = useState("1");
  const [addUnitPrice, setAddUnitPrice] = useState("0");
  const [addSupplier, setAddSupplier] = useState("");
  const [itemError, setItemError] = useState("");
  const [approveErr, setApproveErr] = useState("");

  const load = useCallback(() => {
    getShoppingList(id).then(setList);
  }, [id]);
  useEffect(() => { load(); }, [load]);

  // Reload the full list after any item change so estimated totals recalculate server-side.
  async function patchItem(itemId: string, patch: Parameters<typeof updateListItem>[1]) {
    await updateListItem(itemId, patch);
    load();
  }

  async function searchItems(q: string) {
    setItemSearch(q);
    setSelectedItem(null);
    if (q.trim().length < 2) { setItemResults([]); return; }
    const result = await searchCatalog(
      q,
      list?.procurement_track === "supplies" ? "supply" : "ingredient",
    );
    setItemResults(result.slice(0, 5));
  }

  function selectManualItem(item: CatalogItem) {
    setSelectedItem(item);
    setItemSearch(item.name);
    setItemResults([]);
    setAddUnitPrice(String(item.unit_cost ?? item.purchase_price ?? "0"));
    setAddSupplier("");
  }

  async function addManualItem() {
    if (!list) return;
    const qty = parseFloat(addQty);
    const unitPrice = parseFloat(addUnitPrice);
    if (!selectedItem || !Number.isFinite(qty) || qty <= 0) {
      setItemError("Pick an item and enter a quantity.");
      return;
    }
    setItemError("");
    const created = await addListItem(list.id, {
      fs_item_id: selectedItem.id,
      qty,
      unit: selectedItem.base_unit || "unit",
      unit_price: Number.isFinite(unitPrice) ? unitPrice : 0,
      supplier_id: addSupplier ? addSupplier : null,
    });
    setList({ ...list, items: [...list.items, created] });
    setItemSearch("");
    setSelectedItem(null);
    setAddQty("1");
    setAddUnitPrice("0");
    setAddSupplier("");
  }

  async function removeItem(itemId: string) {
    await deleteListItem(itemId);
    load();
  }

  async function doGeneratePos() {
    setBusy(true);
    try { await approveShoppingList(id); onPosGenerated(); }
    catch (e) { setApproveErr(e instanceof Error ? e.message : "Failed to approve."); }
    finally { setBusy(false); }
  }

  if (!list) return <div className="py-16 text-center text-sm text-warm-400">Loading…</div>;

  const isSupplies = list.procurement_track === "supplies";
  const estimatedTotal = num(list.estimated_total);
  const budgetPerHeadPerDay = list.estimated_budget_per_head_per_day;

  return (
    <div className="space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <div className="flex items-center gap-3">
          <button onClick={onBack} className="p-2 rounded-lg border border-warm-200 hover:bg-warm-50 text-warm-500 cursor-pointer">
            <ChevronLeft className="h-4 w-4" />
          </button>
          <div>
            <h3 className="text-lg font-extrabold text-warm-900">{list.name}</h3>
            <div className="text-xs text-warm-400 flex items-center gap-2">
              {!isSupplies && list.list_type === "suggested" && (
                <span className="inline-flex items-center gap-0.5 text-emerald-600">
                  <Sparkles className="h-3 w-3" /> suggested
                </span>
              )}
              {!isSupplies && list.days_span && <span>· {list.days_span}-day span</span>}
              {isSupplies && <span>Supplies list</span>}
              <span>· {list.status}</span>
            </div>
          </div>
        </div>
        <div className="flex flex-col items-end gap-1">
          <Button
            variant="primary"
            onClick={doGeneratePos}
            loading={busy}
            disabled={list.status === "converted" || !list.release_readiness?.ready}
            className="px-4 py-2 flex items-center gap-2"
          >
            <Split className="h-4 w-4" />
            {list.status === "converted" ? "PO released" : "Create and release PO"}
          </Button>
          {approveErr && <span className="text-xs text-red-600 font-semibold">{approveErr}</span>}
        </div>
      </div>

      {list.status === "draft" && list.release_readiness && !list.release_readiness.ready && (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
          <div className="text-xs font-extrabold uppercase tracking-wider text-amber-800">Before PO release</div>
          <ul className="mt-1 list-disc pl-5 text-sm text-amber-900">
            {list.release_readiness.blockers.map((blocker) => <li key={blocker.code}>{blocker.message}</li>)}
          </ul>
        </div>
      )}

      {/* One estimate is captured when the suggested list is generated. */}
      {!isSupplies && list.period_start && list.period_end && (
        <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm">
          <div className="flex flex-wrap items-end gap-6">
            <div className="flex flex-col gap-1">
              <span className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">
                Estimated population
              </span>
              <div className="text-2xl font-extrabold text-warm-800">{list.estimate_population ?? "—"}</div>
              <span className="text-xs text-warm-400">Applies uniformly across the selected span</span>
            </div>

            <div className="flex flex-col gap-1">
              <span className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">
                Est. budget per head / day
              </span>
              <div className="text-2xl font-extrabold text-warm-800">
                {budgetPerHeadPerDay != null ? peso(budgetPerHeadPerDay) : "—"}
              </div>
              <span className="text-xs text-warm-400">
                total cost ÷ (days × estimated population)
              </span>
            </div>

            <div className="flex flex-col gap-1 ml-auto">
              <span className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">
                Total procurement cost
              </span>
              <div className="text-2xl font-extrabold text-emerald-700">
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
              <label className="block text-xs font-extrabold text-warm-500 uppercase mb-1">Search {isSupplies ? "supplies" : "ingredients"}</label>
              <div className="flex items-center gap-2 px-3 py-2 border border-warm-200 rounded-lg">
                <Search className="h-3.5 w-3.5 text-warm-400" />
                <input
                  value={itemSearch}
                  onChange={(e) => searchItems(e.target.value)}
                  placeholder={`Search ${isSupplies ? "supply" : "ingredient"} catalog…`}
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
              <input type="number" min="0" step="0.01" value={addQty}
                onChange={(e) => setAddQty(e.target.value)}
                className="w-full px-3 py-2 text-base border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            <div>
              <label className="block text-xs font-extrabold text-warm-500 uppercase mb-1">Cost / unit (₱)</label>
              <input type="number" min="0" step="0.01" value={addUnitPrice}
                onChange={(e) => setAddUnitPrice(e.target.value)}
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
            <Button variant="secondary" onClick={addManualItem} className="px-4 py-2 flex items-center gap-2">
              <Plus className="h-4 w-4" /> Add
            </Button>
          </div>
          {itemError && <p className="mt-2 text-xs font-semibold text-red-600">{itemError}</p>}
        </div>
      )}

      {/* Cart table */}
      <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-warm-50 border-b border-warm-100">
            <tr>
              {["Buy", "Item", "Calculated need", "Purchase qty", "Purchase unit", "Vendor", "Price / purchase unit", "Total", ""].map((h) => (
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
                  <span className="ml-2 rounded-full bg-warm-100 px-2 py-0.5 text-xs font-bold uppercase text-warm-500">{it.source}</span>
                  {!it.included_in_po && <input defaultValue={it.exclusion_note ?? ""} placeholder="Optional review note"
                    disabled={list.status === "converted"} onBlur={(e) => patchItem(it.id, { exclusion_note: e.target.value || null })}
                    className="mt-1 block w-40 rounded border border-warm-200 px-2 py-1 text-xs font-normal" />}
                </td>
                <td className="px-3 py-2 font-mono text-warm-700">{num(it.qty).toFixed(3)} {it.unit}</td>
                <td className="px-3 py-2"><input type="number" min="0" step="0.001" defaultValue={num(it.purchase_qty ?? it.qty)} disabled={list.status === "converted"}
                  onBlur={(e) => patchItem(it.id, isSupplies ? { qty: parseFloat(e.target.value) } : { purchase_qty: parseFloat(e.target.value) })}
                  className="w-24 rounded border border-warm-200 px-2 py-1 disabled:bg-warm-50" /></td>
                <td className="px-3 py-2"><input defaultValue={it.purchase_unit ?? it.unit} disabled={list.status === "converted" || isSupplies}
                  onBlur={(e) => patchItem(it.id, { purchase_unit: e.target.value })}
                  className="w-24 rounded border border-warm-200 px-2 py-1 disabled:bg-warm-50" /></td>
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
                    defaultValue={num(it.purchase_price ?? it.unit_price)}
                    step="0.01"
                    disabled={list.status === "converted"}
                    onBlur={(e) => patchItem(it.id, isSupplies ? { unit_price: parseFloat(e.target.value) } : { purchase_price: parseFloat(e.target.value) })}
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
        Vendor is auto-suggested from the latest procurement and auto-updates unless locked.
        Converting to a PO creates one purchase order grouped by vendor.
      </p>
    </div>
  );
}

// ═══ PO detail ════════════════════════════════════════════════════════════════
function PurchaseEventDetailView({ po, suppliers, onBack, reload }: { po: PurchaseOrder; suppliers: Supplier[]; onBack: () => void; reload: () => void }) {
  const [groupId, setGroupId] = useState<string | null>(null);
  const [orDraft, setOrDraft] = useState("");
  const [busy, setBusy] = useState(false);
  const [uploadingKey, setUploadingKey] = useState<string | null>(null);
  const [deletingImageId, setDeletingImageId] = useState<string | null>(null);
  const [imageError, setImageError] = useState<string | null>(null);
  const [actualDraft, setActualDraft] = useState<Record<number, { qty: string; price: string }>>({});
  const [receiptTotal, setReceiptTotal] = useState("");
  const group = po.vendor_groups?.find((g) => g.id === groupId) ?? null;
  const locked = po.lifecycle_status !== "open_execution";

  useEffect(() => { setOrDraft(group?.or_number ?? ""); }, [group?.id, group?.or_number]);
  useEffect(() => {
    setActualDraft(Object.fromEntries((group?.items ?? []).map((item) => [item.id, { qty: item.actual_qty, price: item.actual_unit_price }])));
    setReceiptTotal("");
  }, [group]);

  async function saveGroup(next: Parameters<typeof updateVendorGroup>[1]) {
    if (!group) return;
    setBusy(true);
    try { await updateVendorGroup(group.id, next); reload(); }
    finally { setBusy(false); }
  }
  async function saveActuals(markReceived = false) {
    if (!group) return;
    const singleReceiptTotal = receiptTotal && (group.items ?? []).length === 1 ? parseFloat(receiptTotal) : undefined;
    const items = (group.items ?? []).map((item) => ({
      id: item.id,
      ...(singleReceiptTotal === undefined ? { actual_qty: parseFloat(actualDraft[item.id]?.qty ?? item.actual_qty) } : { receipt_total: singleReceiptTotal }),
      actual_unit_price: parseFloat(actualDraft[item.id]?.price ?? item.actual_unit_price),
    }));
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
          <span className="text-warm-700">{group.supplier?.name ?? "Unassigned vendor"}</span>
        </div>
        <div className="flex items-center gap-3">
          <button onClick={() => setGroupId(null)} className="p-2 rounded-lg border border-warm-200 hover:bg-warm-50 text-warm-500 cursor-pointer">
            <ChevronLeft className="h-4 w-4" />
          </button>
          <div>
            <h3 className="text-lg font-extrabold text-warm-900">{group.supplier?.name ?? "Unassigned vendor"}</h3>
            <div className="text-xs text-warm-400">Status: {group.status} · Total {peso(num(group.total_amount))}</div>
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
              <tr>{["Item and reference", "Actual quantity", "Actual unit price", "Actual total", "Actions"].map((h) => (
                <th key={h} className="px-3 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider">{h}</th>
              ))}</tr>
            </thead>
            <tbody className="divide-y divide-zinc-100">
              {(group.items ?? []).map((item) => (
                <tr key={item.id}>
                  <td className="px-3 py-3">
                    <div className="mb-1 font-semibold text-warm-800">{item.description}</div>
                    <PurchaseValueComparison
                      item={item}
                      actualQty={actualDraft[item.id]?.qty ?? item.actual_qty}
                      actualPrice={actualDraft[item.id]?.price ?? item.actual_unit_price}
                    />
                  </td>
                  <td className="px-3 py-2"><input type="number" min="0" step="0.001" disabled={locked}
                    value={actualDraft[item.id]?.qty ?? item.actual_qty}
                    onChange={(e) => setActualDraft((current) => ({ ...current, [item.id]: { qty: e.target.value, price: current[item.id]?.price ?? item.actual_unit_price } }))}
                    className="w-24 rounded border border-warm-200 px-2 py-1 disabled:bg-warm-50" /></td>
                  <td className="px-3 py-2"><input type="number" min="0" step="0.01" disabled={locked}
                    value={actualDraft[item.id]?.price ?? item.actual_unit_price}
                    onChange={(e) => setActualDraft((current) => ({ ...current, [item.id]: { qty: current[item.id]?.qty ?? item.actual_qty, price: e.target.value } }))}
                    className="w-24 rounded border border-warm-200 px-2 py-1 disabled:bg-warm-50" /></td>
                  <td className="px-3 py-2 font-mono text-emerald-700">{peso(num(actualDraft[item.id]?.qty ?? item.actual_qty) * num(actualDraft[item.id]?.price ?? item.actual_unit_price))}</td>
                  <td className="px-3 py-2">
                    <VendorChangeControls
                      group={group}
                      vendors={suppliers}
                      itemId={item.id}
                      disabled={locked}
                      onChanged={() => { setGroupId(null); reload(); }}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {!locked && <div className="flex flex-wrap items-end gap-3 rounded-2xl border border-warm-200 bg-white p-4">
          <label><span className="mb-1 block text-xs font-bold uppercase text-warm-500">Receipt total (optional)</span>
            <input type="number" min="0" step="0.01" value={receiptTotal} onChange={(e) => setReceiptTotal(e.target.value)}
              placeholder="Derives weight when one item" className="w-52 rounded border border-warm-200 px-3 py-2" /></label>
          <Button variant="secondary" onClick={() => saveActuals(false)} loading={busy}>Save actual values</Button>
          <Button variant="primary" onClick={() => saveActuals(true)} loading={busy}
            disabled={!group.evidence_requirements?.receipt_uploaded || !group.evidence_requirements?.proof_uploaded}>
            Mark vendor received
          </Button>
          <span className="text-xs text-warm-500">Receipt and proof are required. OR number is not.</span>
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
            {po.shopping_list?.name ?? "Manual procurement"} · {po.po_number} · Lifecycle: {po.lifecycle_status} · Total {peso(num(po.total_amount))}
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
                : "Needs each vendor received with receipt, proof, reviewed actuals, and all span served populations"}
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
      <AuditTrail path={`/api/fss/purchase-orders/${po.id}/activity`} title="Purchase order activity" />
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
  const [poDetail, setPoDetail] = useState<string | null>(null);

  const [lists, setLists] = useState<ShoppingList[]>([]);
  const [pos, setPos] = useState<PurchaseOrder[]>([]);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [genOpen, setGenOpen] = useState(false);
  const [genStartDate, setGenStartDate] = useState(today());
  const [genEndDate, setGenEndDate] = useState(today());
  const [genEstimate, setGenEstimate] = useState("");
  const [genError, setGenError] = useState("");
  const [genMissing, setGenMissing] = useState<Record<string, string>>({});
  const [newListName, setNewListName] = useState("");
  const [editingListId, setEditingListId] = useState<string | null>(null);
  const [editingListName, setEditingListName] = useState("");
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
    setTab("pos");
    setPoDetail(targetPoId);
  }, [targetPoId, poDetail]);

  async function doGenerate() {
    if (!genStartDate || !genEndDate) return;
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
      const list = await generateByDates(genStartDate, genEndDate, estimate);
      setGenOpen(false);
      setLists((current) => [list, ...current]);
      setTab("food-lists");
      setListDetail(list.id);
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
    if (!newListName.trim()) return;
    const created = await createShoppingList({
      name: newListName.trim(),
      list_type: "manual",
      procurement_track: track,
      status: "draft",
      list_date: new Date().toISOString().slice(0, 10),
    });
    setLists((current) => [created, ...current]);
    setNewListName("");
    setTab(track === "supplies" ? "supplies-lists" : "food-lists");
    setListDetail(created.id);
  }

  async function saveListName(list: ShoppingList) {
    if (!editingListName.trim()) { setEditingListId(null); return; }
    const updated = await updateShoppingList(list.id, { name: editingListName.trim() });
    setLists((current) => current.map((item) => item.id === list.id ? updated : item));
    setEditingListId(null);
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

  if (listDetail) return (
    <div className="space-y-6 font-sans">
      <Crumbs />
      <ListDetail
        id={listDetail}
        suppliers={suppliers}
        onBack={() => { setListDetail(null); void load(); }}
        onPosGenerated={() => { setListDetail(null); setTab("pos"); void load(); }}
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
          <p className="text-sm text-warm-500 mt-1">
            Food and supplies procurement are separate tracks. Each converts to its own PO with its own vendor grouping.
          </p>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          <button onClick={load} className="flex items-center gap-1.5 text-sm text-warm-500 hover:text-warm-700">
            <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh
          </button>
          {tab === "supplies-lists" && (
            <div className="flex items-center gap-2">
              <input
                value={newListName}
                onChange={(e) => setNewListName(e.target.value)}
                onKeyDown={(e) => { if (e.key === "Enter") void createManualList("supplies"); }}
                placeholder="Supplies list name…"
                className="w-44 px-3 py-2 text-sm border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
              <Button variant="secondary" onClick={() => createManualList("supplies")} className="px-3 py-2 flex items-center gap-1.5">
                <Plus className="h-3.5 w-3.5" /> New supplies list
              </Button>
            </div>
          )}
          {tab === "food-lists" && (
            <div className="flex items-center gap-2">
              <input value={newListName} onChange={(e) => setNewListName(e.target.value)}
                onKeyDown={(e) => { if (e.key === "Enter") void createManualList("food"); }}
                placeholder="Event or manual list name…" className="w-48 rounded-lg border border-warm-200 px-3 py-2 text-sm" />
              <Button variant="secondary" onClick={() => createManualList("food")} className="px-3 py-2"><Plus className="h-3.5 w-3.5" /> Manual food list</Button>
              <Button variant="primary" onClick={() => setGenOpen(true)} className="px-4 py-2.5 flex items-center gap-2">
                <Sparkles className="h-4 w-4" /> Suggest from Menu
              </Button>
            </div>
          )}
        </div>
      </div>

      {/* Generate food list modal */}
      {genOpen && tab === "food-lists" && (
        <div className="bg-emerald-50/40 border border-emerald-100 rounded-2xl p-5 shadow-sm">
          <h3 className="text-sm font-extrabold text-emerald-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
            <Sparkles className="h-3.5 w-3.5" /> Suggested food shopping list
          </h3>
          <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
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
              <input type="number" min="1" value={genEstimate} onChange={(e) => setGenEstimate(e.target.value)} placeholder="For the whole span"
                className="w-full rounded-lg border border-warm-200 px-3 py-2" />
            </label>
            <div className="flex items-end gap-2">
              <Button variant="primary" onClick={doGenerate} className="px-4 py-2">Generate</Button>
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
        <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-x-auto">
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
              <table className="w-full text-sm">
                <thead className="bg-warm-50 border-b border-warm-100">
                  <tr>
                    {["Name", tab === "food-lists" ? "Span" : "Date", "Items", "Status", "Actions"].map((h) => (
                      <th key={h} className="px-4 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-zinc-100">
                  {visibleLists.map((l) => (
                    <tr key={l.id} className="hover:bg-warm-50/60">
                      <td className="px-4 py-3">
                        {editingListId === l.id ? (
                          <div className="flex items-center gap-2">
                            <input
                              value={editingListName}
                              onChange={(e) => setEditingListName(e.target.value)}
                              onKeyDown={(e) => { if (e.key === "Enter") void saveListName(l); }}
                              className="w-48 px-2 py-1 border border-warm-200 rounded text-warm-900 focus:outline-none focus:ring-1 focus:ring-emerald-400"
                            />
                            <button onClick={() => saveListName(l)} className="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 cursor-pointer" aria-label="Save">
                              <Check className="h-3.5 w-3.5" />
                            </button>
                          </div>
                        ) : (
                          <span className="font-semibold text-warm-800">{l.name}</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-warm-500">
                        {tab === "food-lists"
                          ? (l.days_span ? `${spanLabel(l)} (${l.days_span}d)` : "—")
                          : (l.list_date ?? "—")}
                      </td>
                      <td className="px-4 py-3 text-warm-500">{l.items.length}</td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold ${
                          l.status === "converted"
                            ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
                            : "bg-warm-100 text-warm-500"
                        }`}>
                          {l.status}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-1">
                          <button onClick={() => setListDetail(l.id)} className="p-1.5 rounded-lg hover:bg-warm-100 text-warm-500 cursor-pointer" aria-label={`Open ${l.name}`} title="Open">
                            <Eye className="h-3.5 w-3.5" />
                          </button>
                          <button
                            onClick={() => { setEditingListId(l.id); setEditingListName(l.name); }}
                            className="p-1.5 rounded-lg text-warm-500 hover:text-warm-700 hover:bg-warm-100 cursor-pointer"
                            aria-label={`Edit ${l.name}`} title="Edit"
                          >
                            <Pencil className="h-3.5 w-3.5" />
                          </button>
                          <button onClick={() => removeList(l.id)} className="p-1.5 rounded-lg hover:bg-red-50 text-warm-500 hover:text-red-600 cursor-pointer" aria-label={`Delete ${l.name}`} title="Delete">
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
            <Pagination meta={listMeta} page={listPage} onPageChange={setListPage} />
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
                  <tr>{["Procurement span", "Track", "Estimated total", "Vendors", "Lifecycle", "Actions"].map((h) => (
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
                      <td className="px-4 py-3 text-warm-500">{po ? po.lifecycle_status : "—"}</td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-1">
                          <button
                            onClick={() => setPoDetail(po.id)}
                            className="p-1.5 rounded-lg hover:bg-warm-100 text-warm-500 cursor-pointer"
                            title="Open"
                          >
                            <Eye className="h-3.5 w-3.5" />
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
          {tab === "pos" && <Pagination meta={poMeta} page={poPage} onPageChange={setPoPage} />}
        </div>
      )}
    </div>
  );
}
