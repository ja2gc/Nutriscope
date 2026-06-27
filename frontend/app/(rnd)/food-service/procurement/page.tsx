"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  ShoppingBag, Plus, Trash2, RefreshCw, ChevronLeft, Sparkles, Split,
  FileText, X, Pencil, Check, Search, Eye,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  ShoppingList, PurchaseOrder, CostEfficiency, POVendorGroup, POAttachment,
  listShoppingLists, getShoppingList, generateByDates, deleteShoppingList,
  updateListItem, approveShoppingList, listPurchaseOrders, getPurchaseOrder,
  deletePurchaseOrder, deleteAttachment,
  createShoppingList, updateShoppingList, addListItem, deleteListItem,
  getCostEfficiency, updateVendorGroup, uploadVendorGroupAttachments,
} from "@/services/procurementService";
import { listSuppliers, Supplier } from "@/services/supplierService";
import { InventoryRow, listInventoryRows } from "@/services/inventoryService";
import { HistoryPanel } from "@/components/HistoryPanel";
import { SuppliersPanel } from "@/components/foodservice/SuppliersPanel";
import { ImageUploadGallery, type UploadImage } from "@/components/ui/ImageUploadGallery";

const STORAGE_BASE = process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://127.0.0.1:8000";
const peso = (n: number) => `₱${n.toFixed(2)}`;
const num = (s: string | null) => (s ? parseFloat(s) : 0);
const today = () => new Date().toISOString().slice(0, 10);
const attachmentImages = (attachments: POAttachment[] | undefined | null, type: "receipt" | "proof"): UploadImage[] =>
  (attachments ?? []).filter((a) => a.type === type).map((a) => ({
    id: String(a.id),
    name: a.caption ?? `${type} ${a.id}`,
    src: `${STORAGE_BASE}/storage/${a.path}`,
  }));
const spanLabel = (list?: ShoppingList) => list?.period_start && list?.period_end ? `${list.period_start} - ${list.period_end}` : list?.list_date ?? "Manual event";

function Crumbs({ children }: { children?: React.ReactNode }) {
  return (
    <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
      <Link href="/dashboard" className="hover:text-emerald-700">Home</Link><span>/</span>
      <span>Food Service</span><span>/</span><span className="font-bold text-zinc-600">Procurement</span>
      {children}
    </div>
  );
}

// ═══ Shopping list detail ════════════════════════════════════════════════════════
function ListDetail({ id, suppliers, onBack, onPosGenerated }: {
  id: number; suppliers: Supplier[]; onBack: () => void; onPosGenerated: () => void;
}) {
  const [list, setList] = useState<ShoppingList | null>(null);
  const [busy, setBusy] = useState(false);
  const [itemSearch, setItemSearch] = useState("");
  const [itemResults, setItemResults] = useState<InventoryRow[]>([]);
  const [selectedItem, setSelectedItem] = useState<InventoryRow | null>(null);
  const [itemTab, setItemTab] = useState<"ingredient" | "supply">("ingredient");
  const [addQty, setAddQty] = useState("1");
  const [addUnit, setAddUnit] = useState("unit");
  const [addUnitPrice, setAddUnitPrice] = useState("0");
  const [addSupplier, setAddSupplier] = useState("");
  const [itemError, setItemError] = useState("");
  const [eff, setEff] = useState<CostEfficiency | null>(null);
  const [warnDismissed, setWarnDismissed] = useState(false);
  const [approveErr, setApproveErr] = useState("");
  const [populationDraft, setPopulationDraft] = useState("");
  const [populationErr, setPopulationErr] = useState("");
  const [savingPopulation, setSavingPopulation] = useState(false);
  const loadEff = useCallback(() => { getCostEfficiency(id).then(setEff).catch(() => setEff(null)); }, [id]);
  const load = useCallback(() => {
    getShoppingList(id).then((next) => {
      setList(next);
      setPopulationDraft(next.estimate_population ? String(next.estimate_population) : "");
    });
    loadEff();
  }, [id, loadEff]);
  useEffect(() => { load(); }, [load]);

  async function patchItem(itemId: number, patch: { supplier_id?: number | null; qty?: number; unit_price?: number }) {
    const updated = await updateListItem(itemId, patch);
    setList((current) => current ? {
      ...current,
      items: current.items.map((item) => item.id === itemId ? { ...item, ...updated } : item),
    } : current);
  }
  async function searchItems(q: string) {
    setItemSearch(q);
    setSelectedItem(null);
    if (q.trim().length < 2) {
      setItemResults([]);
      return;
    }
    const result = await listInventoryRows({ search: q, type: itemTab, per_page: 8 });
    setItemResults(result.data);
  }
  function selectManualItem(item: InventoryRow) {
    setSelectedItem(item);
    setItemSearch(item.name);
    setItemResults([]);
    setAddUnit(item.base_unit ?? item.unit ?? "unit");
    setAddUnitPrice(item.unit_cost ?? item.unit_price ?? "0");
    setAddSupplier("");
  }
  async function addManualItem() {
    if (!list) return;
    const qty = parseFloat(addQty);
    const unitPrice = parseFloat(addUnitPrice);
    if (!selectedItem || !Number.isFinite(qty) || qty <= 0 || !addUnit.trim()) {
      setItemError("Pick an item, quantity, and unit.");
      return;
    }
    setItemError("");
    const created = await addListItem(list.id, {
      fs_item_id: selectedItem.itemId,
      qty,
      unit: addUnit.trim(),
      unit_price: Number.isFinite(unitPrice) ? unitPrice : 0,
      supplier_id: addSupplier ? parseInt(addSupplier) : null,
    });
    setList({ ...list, items: [...list.items, created] });
    setItemSearch("");
    setSelectedItem(null);
    setAddQty("1");
    setAddUnit("unit");
    setAddUnitPrice("0");
    setAddSupplier("");
  }
  async function removeItem(itemId: number) {
    await deleteListItem(itemId);
    setList((current) => current ? { ...current, items: current.items.filter((item) => item.id !== itemId) } : current);
  }
  async function doGeneratePos() {
    setBusy(true);
    try { await approveShoppingList(id); onPosGenerated(); }
    catch (e) { setApproveErr(e instanceof Error ? e.message : "Failed to approve."); }
    finally { setBusy(false); }
  }
  async function savePopulation() {
    if (!list) return;
    const population = parseInt(populationDraft, 10);
    if (!Number.isFinite(population) || population <= 0) {
      setPopulationErr("Enter a positive headcount.");
      return;
    }
    setSavingPopulation(true);
    setPopulationErr("");
    try {
      const updated = await updateShoppingList(list.id, { estimate_population: population });
      setList(updated);
      setPopulationDraft(updated.estimate_population ? String(updated.estimate_population) : "");
      loadEff();
    } catch (e) {
      setPopulationErr(e instanceof Error ? e.message : "Failed to update population.");
    } finally {
      setSavingPopulation(false);
    }
  }

  if (!list) return <div className="py-16 text-center text-xs text-zinc-400">Loading…</div>;
  const total = list.items.reduce((s, i) => s + num(i.total), 0);
  const perDay = list.days_span ? total / list.days_span : null;

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <div className="flex items-center gap-3">
          <button onClick={onBack} className="p-2 rounded-lg border border-zinc-200 hover:bg-zinc-50 text-zinc-500 cursor-pointer"><ChevronLeft className="h-4 w-4" /></button>
          <div>
            <h3 className="text-lg font-extrabold text-zinc-900">{list.name}</h3>
            <div className="text-[10px] text-zinc-400 flex items-center gap-2">
              {list.list_type === "suggested" && <span className="inline-flex items-center gap-0.5 text-emerald-600"><Sparkles className="h-3 w-3" /> suggested</span>}
              {list.days_span && <span>· {list.days_span}-day span</span>}
              <span>· {list.status}</span>
            </div>
          </div>
        </div>
        <div className="flex flex-col items-end gap-1">
          <Button variant="primary" onClick={doGeneratePos} loading={busy} disabled={list.status === "converted"} className="px-4 py-2 flex items-center gap-2">
            <Split className="h-4 w-4" /> {list.status === "converted" ? "Converted" : "Convert to PO"}
          </Button>
          {approveErr && <span className="text-[10px] text-red-600 font-semibold">{approveErr}</span>}
        </div>
      </div>

      {list.coverage_status === "partial" && list.uncovered_dates.length > 0 && !warnDismissed && (
        <div className="flex items-start justify-between gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
          <div className="text-[11px] text-amber-800">
            <span className="font-extrabold">Partial coverage.</span> No menu plan for{" "}
            <span className="font-semibold">{list.uncovered_dates.join(", ")}</span> — those days were skipped.
            Add the missing menu plan and regenerate to include them before converting to POs.
          </div>
          <button onClick={() => setWarnDismissed(true)} className="text-amber-500 hover:text-amber-700 cursor-pointer shrink-0" aria-label="Dismiss"><X className="h-4 w-4" /></button>
        </div>
      )}

      <div className="flex flex-wrap gap-3">
        <div className="px-4 py-2.5 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-700 text-xs font-semibold flex items-center gap-2">
          <span className="text-lg font-extrabold">{peso(total)}</span><span className="opacity-70">total</span>
        </div>
        {perDay != null && (
          <div className="px-4 py-2.5 rounded-xl border bg-zinc-50 border-zinc-200 text-zinc-700 text-xs font-semibold flex items-center gap-2">
            <span className="text-lg font-extrabold">{peso(perDay)}</span><span className="opacity-70">per day</span>
          </div>
        )}
        {list.period_start && list.period_end && (
          <div className="px-4 py-2.5 rounded-xl border bg-white border-zinc-200 text-zinc-700 text-xs font-semibold flex flex-wrap items-end gap-2">
            <label className="flex flex-col gap-1">
              <span className="text-[10px] font-extrabold text-zinc-500 uppercase">Estimated population</span>
              <input
                type="number"
                min={1}
                value={populationDraft}
                onChange={(e) => setPopulationDraft(e.target.value)}
                disabled={list.status !== "draft"}
                className="w-28 px-2 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-400 disabled:bg-zinc-50 disabled:text-zinc-400"
              />
            </label>
            <Button variant="ghost" size="sm" onClick={savePopulation} loading={savingPopulation} disabled={list.status !== "draft"} className="!px-3 !py-1.5 text-xs">
              Save
            </Button>
            {populationErr && <span className="basis-full text-[10px] text-red-600 font-semibold">{populationErr}</span>}
          </div>
        )}
      </div>

      {/* Calculated budget per head per day — estimated now; actual once the span closes */}
      <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
        <h4 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider mb-3">Budget per head / day</h4>
        <div className="flex flex-wrap items-end gap-6">
          <div>
            <div className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Estimated</div>
            <div className="text-2xl font-extrabold text-zinc-800">
              {eff?.estimated != null ? peso(eff.estimated) : "—"}
            </div>
            <div className="text-[10px] text-zinc-400">planned menu cost ÷ estimated heads</div>
          </div>
          <div>
            <div className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Actual</div>
            {eff && !eff.pending && eff.actual != null ? (
              <>
                <div className="text-2xl font-extrabold text-emerald-600">{peso(eff.actual)}</div>
                <div className="text-[10px] text-zinc-400">procurement cash ÷ {eff.served_population} served</div>
              </>
            ) : (
              <>
                <div className="text-2xl font-extrabold text-amber-500">Pending</div>
                <div className="text-[10px] text-amber-600">{eff?.pending_reason ?? "Awaiting data"}</div>
              </>
            )}
          </div>
          {eff && eff.service_days_expected > 0 && (
            <div className="ml-auto text-right">
              <div className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Meal prep</div>
              <div className="text-sm font-bold text-zinc-700">{eff.service_days_done}/{eff.service_days_expected} days served</div>
              <div className="text-[10px] text-zinc-400">served pop sums from meal-prep days</div>
            </div>
          )}
        </div>
      </div>

      <div className="bg-white border border-zinc-200 rounded-2xl p-4 shadow-sm">
        <div className="flex gap-2 mb-3">
          {(["ingredient", "supply"] as const).map((key) => (
            <button
              key={key}
              onClick={() => { setItemTab(key); setItemSearch(""); setSelectedItem(null); setItemResults([]); }}
              className={`px-3 py-1.5 text-xs font-semibold border-b-2 capitalize ${itemTab === key ? "border-emerald-600 text-emerald-700" : "border-transparent text-zinc-500 hover:text-zinc-800"}`}
            >
              {key === "ingredient" ? "Ingredients" : "Supplies"}
            </button>
          ))}
        </div>
        <div className="grid grid-cols-1 md:grid-cols-[1.4fr_90px_90px_110px_150px_auto] gap-3 items-end">
          <div className="relative">
            <label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">Add item</label>
            <div className="flex items-center gap-2 px-3 py-2 border border-zinc-200 rounded-lg">
              <Search className="h-3.5 w-3.5 text-zinc-400" />
              <input value={itemSearch} onChange={(e) => searchItems(e.target.value)}
                disabled={list.status === "converted"}
                placeholder={`Search ${itemTab === "ingredient" ? "ingredients" : "supplies"}...`} className="w-full text-sm outline-none disabled:bg-white disabled:text-zinc-400" />
            </div>
            {itemResults.length > 0 && (
              <div className="absolute z-20 mt-1 w-full bg-white border border-zinc-200 rounded-xl shadow-lg overflow-hidden">
                {itemResults.map((item) => (
                  <button key={item.itemId} type="button" onClick={() => selectManualItem(item)}
                    className="w-full text-left px-3 py-2 hover:bg-zinc-50 border-b border-zinc-100 last:border-0 cursor-pointer">
                    <span className="block text-xs font-bold text-zinc-900">{item.name}</span>
                    <span className="text-[10px] text-zinc-400">{item.base_unit ?? item.unit} · {item.unit_cost ? peso(num(item.unit_cost)) : "no price"}</span>
                  </button>
                ))}
              </div>
            )}
          </div>
          <div>
            <label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">Quantity</label>
            <input type="number" min="0" step="0.01" value={addQty} onChange={(e) => setAddQty(e.target.value)} disabled={list.status === "converted"}
              className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          </div>
          <div>
            <label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">Unit</label>
            <input value={addUnit} onChange={(e) => setAddUnit(e.target.value)} disabled={list.status === "converted"}
              className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          </div>
          <div>
            <label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">Cost / unit</label>
            <input type="number" min="0" step="0.01" value={addUnitPrice} onChange={(e) => setAddUnitPrice(e.target.value)} disabled={list.status === "converted"}
              className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          </div>
          <div>
            <label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">Vendor</label>
            <select value={addSupplier} onChange={(e) => setAddSupplier(e.target.value)} disabled={list.status === "converted"}
              className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
              <option value="">Unassigned</option>
              {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </div>
          <Button variant="secondary" onClick={addManualItem} disabled={list.status === "converted"} className="px-4 py-2 flex items-center gap-2">
            <Plus className="h-4 w-4" /> Add
          </Button>
        </div>
        {itemError && <p className="mt-2 text-[10px] font-semibold text-red-600">{itemError}</p>}
      </div>

      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
        <table className="w-full text-xs">
          <thead className="bg-zinc-50 border-b border-zinc-100">
            <tr>{["Item", "Quantity", "Unit", "Vendor", "Cost / unit", "Total", ""].map((h) => (
              <th key={h} className="px-3 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>
            ))}</tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {list.items.map((it) => (
              <tr key={it.id} className="hover:bg-zinc-50/60">
                <td className="px-3 py-2 font-semibold text-zinc-800">{it.ingredient_name}</td>
                <td className="px-3 py-2">
                  <input type="number" defaultValue={num(it.qty)} disabled={list.status === "converted"} onBlur={(e) => patchItem(it.id, { qty: parseFloat(e.target.value) })}
                    className="w-20 px-2 py-1 border border-zinc-200 rounded focus:outline-none focus:ring-1 focus:ring-emerald-400 disabled:bg-zinc-50 disabled:text-zinc-400" />
                </td>
                <td className="px-3 py-2 text-zinc-500">{it.unit}</td>
                <td className="px-3 py-2">
                  <select value={it.supplier_id ?? ""} disabled={list.status === "converted"} onChange={(e) => patchItem(it.id, { supplier_id: e.target.value ? parseInt(e.target.value) : null })}
                    className="px-2 py-1 border border-zinc-200 rounded bg-white text-zinc-700 focus:outline-none focus:ring-1 focus:ring-emerald-400 cursor-pointer disabled:bg-zinc-50 disabled:text-zinc-400">
                    <option value="">— vendor —</option>
                    {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </select>
                </td>
                <td className="px-3 py-2">
                  <input type="number" defaultValue={num(it.unit_price)} step="0.01" disabled={list.status === "converted"} onBlur={(e) => patchItem(it.id, { unit_price: parseFloat(e.target.value) })}
                    className="w-20 px-2 py-1 border border-zinc-200 rounded focus:outline-none focus:ring-1 focus:ring-emerald-400 disabled:bg-zinc-50 disabled:text-zinc-400" />
                </td>
                <td className="px-3 py-2 font-mono text-emerald-700">{peso(num(it.total))}</td>
                <td className="px-3 py-2">
                  <button onClick={() => removeItem(it.id)} disabled={list.status === "converted"} className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 cursor-pointer disabled:opacity-40" aria-label={`Remove ${it.ingredient_name}`}>
                    <Trash2 className="h-3.5 w-3.5" />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="text-[10px] text-zinc-400">Picking a vendor remembers it as the default for that item next time. Generating POs splits this list into one draft purchase order per vendor.</p>
    </div>
  );
}

// ═══ PO detail ════════════════════════════════════════════════════════════════════
function PurchaseEventDetailView({ po, onBack, reload }: { po: PurchaseOrder; onBack: () => void; reload: () => void }) {
  const [groupId, setGroupId] = useState<number | null>(null);
  const [orDraft, setOrDraft] = useState("");
  const [busy, setBusy] = useState(false);
  const group = po.vendor_groups?.find((g) => g.id === groupId) ?? null;
  const locked = po.lifecycle_status !== "open_execution";

  useEffect(() => { setOrDraft(group?.or_number ?? ""); }, [group?.id, group?.or_number]);

  async function saveGroup(next: Partial<Pick<POVendorGroup, "or_number" | "status">>) {
    if (!group) return;
    setBusy(true);
    try { await updateVendorGroup(group.id, next); reload(); }
    finally { setBusy(false); }
  }
  async function uploadGroupFiles(type: "receipt" | "proof", files: File[]) {
    if (!group || files.length === 0) return;
    setBusy(true);
    try { await uploadVendorGroupAttachments(group.id, files, type); reload(); }
    finally { setBusy(false); }
  }
  async function syncImages(type: "receipt" | "proof", next: UploadImage[]) {
    if (!group) return;
    const keep = new Set(next.map((image) => image.id));
    const removed = (group.attachments ?? []).filter((attachment) => attachment.type === type && !keep.has(String(attachment.id)));
    if (removed.length === 0) return;
    setBusy(true);
    try {
      await Promise.all(removed.map((attachment) => deleteAttachment(attachment.id)));
      reload();
    } finally { setBusy(false); }
  }

  if (group) {
    return (
      <div className="space-y-5">
        <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400">
          <button onClick={() => setGroupId(null)} className="hover:text-emerald-700">Procurement</button><span>/</span>
          <span>{po.po_number}</span><span>/</span><span className="text-zinc-700">{group.supplier?.name ?? "Unassigned vendor"}</span>
        </div>
        <div className="flex items-center gap-3">
          <button onClick={() => setGroupId(null)} className="p-2 rounded-lg border border-zinc-200 hover:bg-zinc-50 text-zinc-500 cursor-pointer"><ChevronLeft className="h-4 w-4" /></button>
          <div>
            <h3 className="text-lg font-extrabold text-zinc-900">{group.supplier?.name ?? "Unassigned vendor"}</h3>
            <div className="text-[10px] text-zinc-400">Status: {group.status} · Total {peso(num(group.total_amount))}</div>
          </div>
        </div>

        <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm grid grid-cols-1 md:grid-cols-[1fr_auto_auto] gap-3 items-end">
          <label className="block">
            <span className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">OR number</span>
            <input value={orDraft} onChange={(e) => setOrDraft(e.target.value)} disabled={locked}
              className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:bg-zinc-50 disabled:text-zinc-400" />
          </label>
          <Button variant="ghost" onClick={() => saveGroup({ or_number: orDraft || null })} loading={busy} disabled={locked} className="px-4 py-2">Save</Button>
          <Button variant="ghost" onClick={() => saveGroup({ status: "received" })} loading={busy} disabled={locked || group.status === "received"} className="px-4 py-2">Mark received</Button>
        </div>

        <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
          <table className="w-full text-xs">
            <thead className="bg-zinc-50 border-b border-zinc-100"><tr>{["Item", "Quantity", "Unit", "Cost / unit", "Total"].map((h) => (
              <th key={h} className="px-3 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>
            ))}</tr></thead>
            <tbody className="divide-y divide-zinc-100">
              {(group.items ?? []).map((item) => (
                <tr key={item.id}>
                  <td className="px-3 py-2 font-semibold text-zinc-800">{item.description}</td>
                  <td className="px-3 py-2">{num(item.purchase_qty ?? item.qty)}</td>
                  <td className="px-3 py-2 text-zinc-500">{item.purchase_unit ?? item.unit}</td>
                  <td className="px-3 py-2 font-mono">{peso(num(item.purchase_price ?? item.unit_price))}</td>
                  <td className="px-3 py-2 font-mono text-emerald-700">{peso(num(item.total_value))}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
            <ImageUploadGallery images={attachmentImages(group.attachments, "receipt")} onImagesChange={(images) => { void syncImages("receipt", images); }} onFilesSelected={(files) => uploadGroupFiles("receipt", files)} label="Receipt images" emptyText="No receipt images yet." />
          </div>
          <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
            <ImageUploadGallery images={attachmentImages(group.attachments, "proof")} onImagesChange={(images) => { void syncImages("proof", images); }} onFilesSelected={(files) => uploadGroupFiles("proof", files)} label="Proof of purchase" emptyText="No proof photos yet." />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-3">
        <button onClick={onBack} className="p-2 rounded-lg border border-zinc-200 hover:bg-zinc-50 text-zinc-500 cursor-pointer"><ChevronLeft className="h-4 w-4" /></button>
        <div>
          <h3 className="text-lg font-extrabold text-zinc-900 flex items-center gap-2"><FileText className="h-4 w-4 text-emerald-600" />{po!.po_number}</h3>
          <div className="text-[10px] text-zinc-400">Lifecycle: {po.lifecycle_status} · Total {peso(num(po.total_amount))}</div>
        </div>
      </div>
      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
        <table className="w-full text-xs">
          <thead className="bg-zinc-50 border-b border-zinc-100"><tr>{["Vendor", "Items", "OR #", "Receipt", "Total", ""].map((h) => (
            <th key={h} className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>
          ))}</tr></thead>
          <tbody className="divide-y divide-zinc-100">
            {(po.vendor_groups ?? []).map((g) => (
              <tr key={g.id} className="hover:bg-zinc-50/60">
                <td className="px-4 py-3 font-semibold text-zinc-800">{g.supplier?.name ?? "Unassigned vendor"}</td>
                <td className="px-4 py-3 text-zinc-500">{g.items?.length ?? 0}</td>
                <td className="px-4 py-3 text-zinc-500">{g.or_number ?? "—"}</td>
                <td className="px-4 py-3 text-zinc-500">{(g.attachments ?? []).some((a) => a.type === "receipt") ? "uploaded" : "missing"}</td>
                <td className="px-4 py-3 font-mono text-zinc-700">{peso(num(g.total_amount))}</td>
                <td className="px-4 py-3"><button onClick={() => setGroupId(g.id)} className="text-xs font-semibold text-emerald-700 hover:underline cursor-pointer">Open</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <HistoryPanel path={`/api/fss/purchase-orders/${po.id}/activity`} title="Purchase order history" />
    </div>
  );
}

function PoDetail({ id, onBack }: { id: number; onBack: () => void }) {
  const [po, setPo] = useState<PurchaseOrder | null>(null);

  const load = useCallback(() => { getPurchaseOrder(id).then(setPo); }, [id]);
  useEffect(() => { load(); }, [load]);

  if (!po) return <div className="py-16 text-center text-xs text-zinc-400">Loading…</div>;
  return <PurchaseEventDetailView po={po} onBack={onBack} reload={load} />;
}

// ═══ ROOT ════════════════════════════════════════════════════════════════════════
export default function ProcurementPage() {
  const [tab, setTab] = useState<"lists" | "pos" | "suppliers">("lists");
  const [listDetail, setListDetail] = useState<number | null>(null);
  const [poDetail, setPoDetail] = useState<number | null>(null);

  const [lists, setLists] = useState<ShoppingList[]>([]);
  const [pos, setPos] = useState<PurchaseOrder[]>([]);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [genOpen, setGenOpen] = useState(false);
  const [genStartDate, setGenStartDate] = useState(today());
  const [genEndDate, setGenEndDate] = useState(today());
  const [genError, setGenError] = useState("");
  const [newListName, setNewListName] = useState("");
  const [editingListId, setEditingListId] = useState<number | null>(null);
  const [editingListName, setEditingListName] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [l, p, s] = await Promise.all([listShoppingLists(), listPurchaseOrders(), listSuppliers()]);
      setLists(l); setPos(p); setSuppliers(s);
    } finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);

  async function doGenerate() {
    if (!genStartDate || !genEndDate) return;
    if (genEndDate < genStartDate) {
      setGenError("End date must be on or after the start date.");
      return;
    }
    setGenError("");
    try {
      const list = await generateByDates(genStartDate, genEndDate);
      setGenOpen(false); setLists((current) => [list, ...current]); setTab("lists"); setListDetail(list.id);
    } catch (e) {
      setGenError(e instanceof Error ? e.message : "Failed to generate list.");
    }
  }

  async function createManualList() {
    if (!newListName.trim()) return;
    const created = await createShoppingList({
      name: newListName.trim(),
      list_type: "manual",
      status: "draft",
      list_date: new Date().toISOString().slice(0, 10),
    });
    setLists((current) => [created, ...current]);
    setNewListName("");
    setTab("lists");
    setListDetail(created.id);
  }

  async function saveListName(list: ShoppingList) {
    if (!editingListName.trim()) {
      setEditingListId(null);
      return;
    }
    const updated = await updateShoppingList(list.id, { name: editingListName.trim() });
    setLists((current) => current.map((item) => item.id === list.id ? updated : item));
    setEditingListId(null);
  }

  async function removeList(id: number) {
    await deleteShoppingList(id);
    setLists((current) => current.filter((list) => list.id !== id));
  }

  async function removePo(id: number) {
    await deletePurchaseOrder(id);
    setPos((current) => current.filter((po) => po.id !== id));
  }

  const poByList = new Map(pos.filter((po) => po.shopping_list_id != null).map((po) => [po.shopping_list_id, po]));
  const procurementEvents = lists.map((list) => ({ list, po: poByList.get(list.id) ?? null }));

  if (listDetail) return (
    <div className="space-y-6 font-sans"><Crumbs /><ListDetail id={listDetail} suppliers={suppliers} onBack={() => { setListDetail(null); load(); }} onPosGenerated={() => { setListDetail(null); setTab("pos"); load(); }} /></div>
  );
  if (poDetail) return (
    <div className="space-y-6 font-sans"><Crumbs /><PoDetail id={poDetail} onBack={() => { setPoDetail(null); load(); }} /></div>
  );

  return (
    <div className="space-y-6 font-sans">
      <Crumbs />
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5"><ShoppingBag className="h-5 w-5 text-emerald-600" /> Procurement</h2>
          <p className="text-xs text-zinc-500 mt-1">Build draft shopping lists, convert each procurement event into one PO, then complete vendor-group receipts and proof uploads.</p>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          <button onClick={load} className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700"><RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh</button>
          {tab === "lists" && (
            <div className="flex items-center gap-2">
              <input value={newListName} onChange={(e) => setNewListName(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") void createManualList(); }}
                placeholder="Supplies / ad-hoc list name" className="w-44 px-3 py-2 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
              <Button variant="secondary" onClick={createManualList} className="px-3 py-2 flex items-center gap-1.5">
                <Plus className="h-3.5 w-3.5" /> New supplies list
              </Button>
            </div>
          )}
          {tab === "lists" && <Button variant="primary" onClick={() => setGenOpen(true)} className="px-4 py-2.5 flex items-center gap-2"><Plus className="h-4 w-4" /> Suggest from Menu</Button>}
        </div>
      </div>

      {/* Generate modal */}
      {genOpen && (
        <div className="bg-emerald-50/40 border border-emerald-100 rounded-2xl p-5 shadow-sm">
          <h3 className="text-xs font-extrabold text-emerald-700 uppercase tracking-wider mb-3 flex items-center gap-1.5"><Sparkles className="h-3.5 w-3.5" /> Suggested shopping list</h3>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">From date</label>
              <input type="date" value={genStartDate} onChange={(e) => {
                const next = e.target.value;
                setGenStartDate(next);
                if (genEndDate < next) setGenEndDate(next);
              }} className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            <div>
              <label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">To date</label>
              <input type="date" value={genEndDate} min={genStartDate} onChange={(e) => setGenEndDate(e.target.value)}
                className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            <div className="flex items-end gap-2">
              <Button variant="primary" onClick={doGenerate} className="px-4 py-2">Generate</Button>
              <button onClick={() => setGenOpen(false)} className="text-xs text-zinc-500 hover:text-zinc-700 cursor-pointer">Cancel</button>
            </div>
          </div>
          {genError && <p className="text-[10px] text-red-600 mt-2 font-semibold">{genError}</p>}
          <p className="text-[10px] text-zinc-400 mt-2">Sums exactly the ingredients the menu plans for each day in the date range — even a Fri→Mon span that crosses into next week&apos;s cycle. Each item&apos;s default vendor is pre-filled; days with no menu plan are flagged on the list.</p>
        </div>
      )}

      {/* Tabs */}
      <div className="flex border-b border-zinc-200">
        {([["lists", "Shopping Lists"], ["pos", "Procurement Events"], ["suppliers", "Suppliers"]] as const).map(([k, label]) => (
          <button key={k} onClick={() => setTab(k)}
            className={`px-5 py-3 text-sm font-semibold border-b-2 transition-colors cursor-pointer ${tab === k ? "border-emerald-600 text-emerald-700" : "border-transparent text-zinc-500 hover:text-zinc-800"}`}>
            {label}
          </button>
        ))}
      </div>

      {tab === "suppliers" ? <SuppliersPanel /> : (
      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
        {loading ? <div className="py-16 text-center text-xs text-zinc-400">Loading…</div> : tab === "lists" ? (
          lists.length === 0 ? <div className="py-16 text-center"><ShoppingBag className="h-8 w-8 text-zinc-300 mx-auto mb-3" /><p className="text-xs text-zinc-400 font-medium">No shopping lists yet. Suggest one from a menu.</p></div> : (
            <table className="w-full text-xs">
              <thead className="bg-zinc-50 border-b border-zinc-100"><tr>{["List", "Type", "Span", "Items", "Status", "Actions"].map((h) => <th key={h} className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-zinc-100">
                {lists.map((l) => (
                  <tr key={l.id} className="hover:bg-zinc-50/60">
                    <td className="px-4 py-3">
                      {editingListId === l.id ? (
                        <div className="flex items-center gap-2">
                          <input value={editingListName} onChange={(e) => setEditingListName(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") void saveListName(l); }}
                            className="w-48 px-2 py-1 border border-zinc-200 rounded text-zinc-900 focus:outline-none focus:ring-1 focus:ring-emerald-400" />
                          <button onClick={() => saveListName(l)} className="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 cursor-pointer" aria-label="Save list name">
                            <Check className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      ) : (
                        <span className="font-semibold text-zinc-800">{l.name}</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-zinc-500">{l.list_type}</td>
                    <td className="px-4 py-3 text-zinc-500">{l.days_span ? `${l.days_span}d` : "—"}</td>
                    <td className="px-4 py-3 text-zinc-500">{l.items.length}</td>
                    <td className="px-4 py-3">
                      <select value={l.status} onChange={async (e) => {
                        const updated = await updateShoppingList(l.id, { status: e.target.value as "draft" | "converted" });
                        setLists((current) => current.map((item) => item.id === l.id ? updated : item));
                      }} className="px-2 py-1 border border-zinc-200 rounded bg-white text-zinc-700 focus:outline-none focus:ring-1 focus:ring-emerald-400 cursor-pointer">
                        <option value="draft">draft</option>
                        <option value="converted">converted</option>
                      </select>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-1">
                        <button onClick={() => setListDetail(l.id)} className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 cursor-pointer" aria-label={`Open ${l.name}`} title="Open"><Eye className="h-3.5 w-3.5" /></button>
                        <button onClick={() => { setEditingListId(l.id); setEditingListName(l.name); }} className="p-1.5 rounded-lg text-zinc-500 hover:text-zinc-700 hover:bg-zinc-100 cursor-pointer" aria-label={`Edit ${l.name}`} title="Edit"><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={() => removeList(l.id)} className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 cursor-pointer" aria-label={`Delete ${l.name}`} title="Delete"><Trash2 className="h-3.5 w-3.5" /></button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )
        ) : (
          procurementEvents.length === 0 ? <div className="py-16 text-center"><FileText className="h-8 w-8 text-zinc-300 mx-auto mb-3" /><p className="text-xs text-zinc-400 font-medium">No procurement events yet. Generate or create a shopping list first.</p></div> : (
            <table className="w-full text-xs">
              <thead className="bg-zinc-50 border-b border-zinc-100"><tr>{["Procurement span", "Estimated total", "Vendors", "Lifecycle", "Coverage", "Actions"].map((h) => <th key={h} className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-zinc-100">
                {procurementEvents.map(({ list, po }) => (
                  <tr key={list.id} className="hover:bg-zinc-50/60">
                    <td className="px-4 py-3">
                      <span className="font-semibold text-zinc-800">{spanLabel(list)}</span>
                      <div className="text-[10px] text-zinc-400">{list.name}</div>
                    </td>
                    <td className="px-4 py-3 font-mono text-zinc-700">{peso(list.items.reduce((sum, item) => sum + num(item.total), 0))}</td>
                    <td className="px-4 py-3 text-zinc-500">{po?.vendor_groups?.length ?? 0}</td>
                    <td className="px-4 py-3 text-zinc-500">{po ? po.lifecycle_status : "Draft"}</td>
                    <td className="px-4 py-3 text-zinc-500">{list.coverage_status}</td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-1">
                        <button onClick={() => po ? setPoDetail(po.id) : setListDetail(list.id)} className="text-xs font-semibold text-emerald-700 hover:underline cursor-pointer">Open</button>
                        {po && po.lifecycle_status === "open_execution" && (
                          <button onClick={() => removePo(po.id)} className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 cursor-pointer" aria-label={`Delete ${po.po_number}`}><Trash2 className="h-3.5 w-3.5" /></button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )
        )}
      </div>
      )}
    </div>
  );
}
