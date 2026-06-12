"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  ShoppingBag, Plus, Trash2, RefreshCw, ChevronLeft, Sparkles, Split,
  FileText, Upload, X, Receipt, Camera, Truck,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  ShoppingList, PurchaseOrder,
  listShoppingLists, getShoppingList, generateFromCycle, deleteShoppingList,
  updateListItem, generatePos, listPurchaseOrders, getPurchaseOrder,
  updatePurchaseOrder, deletePurchaseOrder, uploadAttachment, deleteAttachment,
} from "@/services/procurementService";
import { listSuppliers, Supplier } from "@/services/supplierService";
import { listCycles, CycleListItem } from "@/services/menuCycleService";

const STORAGE_BASE = process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://127.0.0.1:8000";
const peso = (n: number) => `₱${n.toFixed(2)}`;
const num = (s: string | null) => (s ? parseFloat(s) : 0);

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
  const load = useCallback(() => { getShoppingList(id).then(setList); }, [id]);
  useEffect(() => { load(); }, [load]);

  async function patchItem(itemId: number, patch: { supplier_id?: number | null; qty?: number; unit_price?: number }) {
    await updateListItem(itemId, patch);
    load();
  }
  async function doGeneratePos() {
    setBusy(true);
    try { await generatePos(id); onPosGenerated(); } finally { setBusy(false); }
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
        <Button variant="primary" onClick={doGeneratePos} loading={busy} className="px-4 py-2 flex items-center gap-2">
          <Split className="h-4 w-4" /> Generate POs by vendor
        </Button>
      </div>

      <div className="flex flex-wrap gap-3">
        <div className="px-4 py-2.5 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-700 text-xs font-semibold flex items-center gap-2">
          <span className="text-lg font-extrabold">{peso(total)}</span><span className="opacity-70">total</span>
        </div>
        {perDay != null && (
          <div className="px-4 py-2.5 rounded-xl border bg-zinc-50 border-zinc-200 text-zinc-700 text-xs font-semibold flex items-center gap-2">
            <span className="text-lg font-extrabold">{peso(perDay)}</span><span className="opacity-70">per day</span>
          </div>
        )}
      </div>

      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
        <table className="w-full text-xs">
          <thead className="bg-zinc-50 border-b border-zinc-100">
            <tr>{["Item", "Qty", "Unit", "Vendor", "Unit ₱", "Total", ""].map((h) => (
              <th key={h} className="px-3 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>
            ))}</tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {list.items.map((it) => (
              <tr key={it.id} className="hover:bg-zinc-50/60">
                <td className="px-3 py-2 font-semibold text-zinc-800">{it.ingredient_name}</td>
                <td className="px-3 py-2">
                  <input type="number" defaultValue={num(it.qty)} onBlur={(e) => patchItem(it.id, { qty: parseFloat(e.target.value) })}
                    className="w-20 px-2 py-1 border border-zinc-200 rounded focus:outline-none focus:ring-1 focus:ring-emerald-400" />
                </td>
                <td className="px-3 py-2 text-zinc-500">{it.unit}</td>
                <td className="px-3 py-2">
                  <select value={it.supplier_id ?? ""} onChange={(e) => patchItem(it.id, { supplier_id: e.target.value ? parseInt(e.target.value) : null })}
                    className="px-2 py-1 border border-zinc-200 rounded bg-white text-zinc-700 focus:outline-none focus:ring-1 focus:ring-emerald-400 cursor-pointer">
                    <option value="">— vendor —</option>
                    {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </select>
                </td>
                <td className="px-3 py-2">
                  <input type="number" defaultValue={num(it.unit_price)} step="0.01" onBlur={(e) => patchItem(it.id, { unit_price: parseFloat(e.target.value) })}
                    className="w-20 px-2 py-1 border border-zinc-200 rounded focus:outline-none focus:ring-1 focus:ring-emerald-400" />
                </td>
                <td className="px-3 py-2 font-mono text-emerald-700">{peso(num(it.total))}</td>
                <td className="px-3 py-2" />
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
function PoDetail({ id, onBack }: { id: number; onBack: () => void }) {
  const [po, setPo] = useState<PurchaseOrder | null>(null);
  const [orNumber, setOrNumber] = useState("");
  const [uploadType, setUploadType] = useState<"receipt" | "proof">("receipt");
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => { getPurchaseOrder(id).then((p) => { setPo(p); setOrNumber(p.or_number ?? ""); }); }, [id]);
  useEffect(() => { load(); }, [load]);

  async function saveField(patch: { or_number?: string; status?: "draft" | "ordered" | "received" }) {
    await updatePurchaseOrder(id, patch); load();
  }
  async function onFile(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setBusy(true);
    try { await uploadAttachment(id, file, uploadType); load(); } finally { setBusy(false); e.target.value = ""; }
  }
  async function removeAttachment(attId: number) { await deleteAttachment(attId); load(); }

  if (!po) return <div className="py-16 text-center text-xs text-zinc-400">Loading…</div>;

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-3">
        <button onClick={onBack} className="p-2 rounded-lg border border-zinc-200 hover:bg-zinc-50 text-zinc-500 cursor-pointer"><ChevronLeft className="h-4 w-4" /></button>
        <div>
          <h3 className="text-lg font-extrabold text-zinc-900 flex items-center gap-2"><FileText className="h-4 w-4 text-emerald-600" />{po.po_number}</h3>
          <div className="text-[10px] text-zinc-400 flex items-center gap-1"><Truck className="h-3 w-3" />{po.supplier?.name ?? "Unassigned vendor"}</div>
        </div>
      </div>

      <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div>
          <label className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">OR Number</label>
          <input value={orNumber} onChange={(e) => setOrNumber(e.target.value)} onBlur={() => saveField({ or_number: orNumber })}
            placeholder="Official receipt no." className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
        </div>
        <div>
          <label className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">Status</label>
          <select value={po.status} onChange={(e) => saveField({ status: e.target.value as "draft" | "ordered" | "received" })}
            className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
            {["draft", "ordered", "received"].map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
        <div>
          <span className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">Total</span>
          <div className="text-lg font-extrabold text-emerald-600">{peso(num(po.total_amount))}</div>
        </div>
        <div>
          <span className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">Order date</span>
          <div className="text-sm text-zinc-700 py-2">{po.order_date ?? "—"}</div>
        </div>
      </div>
      {po.status === "received" && <p className="text-[10px] text-emerald-600 font-semibold">✓ Received — quantities were added back into inventory.</p>}

      {/* Items */}
      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
        <table className="w-full text-xs">
          <thead className="bg-zinc-50 border-b border-zinc-100"><tr>{["Item", "Qty", "Unit", "Unit ₱", "Total"].map((h) => (
            <th key={h} className="px-3 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>
          ))}</tr></thead>
          <tbody className="divide-y divide-zinc-100">
            {(po.items ?? []).map((i) => (
              <tr key={i.id}>
                <td className="px-3 py-2 font-semibold text-zinc-800">{i.description}</td>
                <td className="px-3 py-2">{num(i.qty)}</td>
                <td className="px-3 py-2 text-zinc-500">{i.unit}</td>
                <td className="px-3 py-2 font-mono">{peso(num(i.unit_price))}</td>
                <td className="px-3 py-2 font-mono text-emerald-700">{peso(num(i.total_value))}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Attachments */}
      <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
        <div className="flex items-center justify-between mb-3">
          <h4 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Receipts &amp; Proof of Items</h4>
          <div className="flex items-center gap-2">
            <div className="flex rounded-lg bg-zinc-100 p-0.5">
              {(["receipt", "proof"] as const).map((t) => (
                <button key={t} onClick={() => setUploadType(t)}
                  className={`px-2.5 py-1 text-[10px] font-bold uppercase rounded-md cursor-pointer ${uploadType === t ? "bg-white shadow-sm text-zinc-900" : "text-zinc-500"}`}>
                  {t === "receipt" ? <Receipt className="h-3 w-3 inline mr-1" /> : <Camera className="h-3 w-3 inline mr-1" />}{t}
                </button>
              ))}
            </div>
            <label className={`flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 border border-emerald-200 rounded-lg px-3 py-1.5 cursor-pointer hover:bg-emerald-50 ${busy ? "opacity-50" : ""}`}>
              <Upload className="h-3 w-3" /> Upload {uploadType}
              <input type="file" accept="image/*" onChange={onFile} disabled={busy} className="hidden" />
            </label>
          </div>
        </div>
        {(po.attachments ?? []).length === 0 ? (
          <p className="text-[10px] text-zinc-400 text-center py-6">No receipts or proof photos yet.</p>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            {(po.attachments ?? []).map((a) => (
              <div key={a.id} className="relative group border border-zinc-200 rounded-lg overflow-hidden">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={`${STORAGE_BASE}/storage/${a.path}`} alt={a.type} className="w-full h-24 object-cover" />
                <span className="absolute top-1 left-1 px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-black/60 text-white">{a.type}</span>
                <button onClick={() => removeAttachment(a.id)} className="absolute top-1 right-1 p-0.5 rounded bg-black/60 text-white opacity-0 group-hover:opacity-100 cursor-pointer"><X className="h-3 w-3" /></button>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

// ═══ ROOT ════════════════════════════════════════════════════════════════════════
export default function ProcurementPage() {
  const [tab, setTab] = useState<"lists" | "pos">("lists");
  const [listDetail, setListDetail] = useState<number | null>(null);
  const [poDetail, setPoDetail] = useState<number | null>(null);

  const [lists, setLists] = useState<ShoppingList[]>([]);
  const [pos, setPos] = useState<PurchaseOrder[]>([]);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [cycles, setCycles] = useState<CycleListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [genOpen, setGenOpen] = useState(false);
  const [genCycle, setGenCycle] = useState("");
  const [genSpan, setGenSpan] = useState("7");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [l, p, s, c] = await Promise.all([listShoppingLists(), listPurchaseOrders(), listSuppliers(), listCycles()]);
      setLists(l); setPos(p); setSuppliers(s); setCycles(c);
    } finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);

  async function doGenerate() {
    if (!genCycle) return;
    const list = await generateFromCycle(parseInt(genCycle), parseInt(genSpan) || 7);
    setGenOpen(false); await load(); setTab("lists"); setListDetail(list.id);
  }

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
          <p className="text-xs text-zinc-500 mt-1">Build shopping lists from the menu, split them into vendor purchase orders, and attach receipts &amp; proof of purchase.</p>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          <button onClick={load} className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700"><RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh</button>
          {tab === "lists" && <Button variant="primary" onClick={() => setGenOpen(true)} className="px-4 py-2.5 flex items-center gap-2"><Plus className="h-4 w-4" /> Suggest from Menu</Button>}
        </div>
      </div>

      {/* Generate modal */}
      {genOpen && (
        <div className="bg-emerald-50/40 border border-emerald-100 rounded-2xl p-5 shadow-sm">
          <h3 className="text-xs font-extrabold text-emerald-700 uppercase tracking-wider mb-3 flex items-center gap-1.5"><Sparkles className="h-3.5 w-3.5" /> Suggested shopping list</h3>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">Menu cycle</label>
              <select value={genCycle} onChange={(e) => setGenCycle(e.target.value)} className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="">Select cycle…</option>
                {cycles.map((c) => <option key={c.id} value={c.id}>{c.name}{c.is_active ? " (active)" : ""}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">Day span</label>
              <input type="number" min="1" value={genSpan} onChange={(e) => setGenSpan(e.target.value)} className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            <div className="flex items-end gap-2">
              <Button variant="primary" onClick={doGenerate} className="px-4 py-2">Generate</Button>
              <button onClick={() => setGenOpen(false)} className="text-xs text-zinc-500 hover:text-zinc-700 cursor-pointer">Cancel</button>
            </div>
          </div>
          <p className="text-[10px] text-zinc-400 mt-2">Aggregates every ingredient the menu needs over the chosen number of days, with each item&apos;s default vendor pre-filled.</p>
        </div>
      )}

      {/* Tabs */}
      <div className="flex border-b border-zinc-200">
        {([["lists", "Shopping Lists"], ["pos", "Purchase Orders"]] as const).map(([k, label]) => (
          <button key={k} onClick={() => setTab(k)}
            className={`px-5 py-3 text-sm font-semibold border-b-2 transition-colors cursor-pointer ${tab === k ? "border-emerald-600 text-emerald-700" : "border-transparent text-zinc-500 hover:text-zinc-800"}`}>
            {label}
          </button>
        ))}
      </div>

      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
        {loading ? <div className="py-16 text-center text-xs text-zinc-400">Loading…</div> : tab === "lists" ? (
          lists.length === 0 ? <div className="py-16 text-center"><ShoppingBag className="h-8 w-8 text-zinc-300 mx-auto mb-3" /><p className="text-xs text-zinc-400 font-medium">No shopping lists yet. Suggest one from a menu.</p></div> : (
            <table className="w-full text-xs">
              <thead className="bg-zinc-50 border-b border-zinc-100"><tr>{["List", "Type", "Span", "Items", "Status", ""].map((h) => <th key={h} className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-zinc-100">
                {lists.map((l) => (
                  <tr key={l.id} className="hover:bg-zinc-50/60">
                    <td className="px-4 py-3"><button onClick={() => setListDetail(l.id)} className="font-semibold text-emerald-700 hover:underline cursor-pointer">{l.name}</button></td>
                    <td className="px-4 py-3 text-zinc-500">{l.list_type}</td>
                    <td className="px-4 py-3 text-zinc-500">{l.days_span ? `${l.days_span}d` : "—"}</td>
                    <td className="px-4 py-3 text-zinc-500">{l.items.length}</td>
                    <td className="px-4 py-3"><span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${l.status === "finalized" ? "bg-emerald-50 text-emerald-700 border-emerald-200" : "bg-zinc-100 text-zinc-500 border-zinc-200"}`}>{l.status}</span></td>
                    <td className="px-4 py-3"><button onClick={() => deleteShoppingList(l.id).then(load)} className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 cursor-pointer"><Trash2 className="h-3.5 w-3.5" /></button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )
        ) : (
          pos.length === 0 ? <div className="py-16 text-center"><FileText className="h-8 w-8 text-zinc-300 mx-auto mb-3" /><p className="text-xs text-zinc-400 font-medium">No purchase orders yet. Generate them from a shopping list.</p></div> : (
            <table className="w-full text-xs">
              <thead className="bg-zinc-50 border-b border-zinc-100"><tr>{["PO #", "Vendor", "OR #", "Total", "Status", ""].map((h) => <th key={h} className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-zinc-100">
                {pos.map((p) => (
                  <tr key={p.id} className="hover:bg-zinc-50/60">
                    <td className="px-4 py-3"><button onClick={() => setPoDetail(p.id)} className="font-semibold text-emerald-700 hover:underline cursor-pointer font-mono">{p.po_number}</button></td>
                    <td className="px-4 py-3 text-zinc-600">{p.supplier?.name ?? "—"}</td>
                    <td className="px-4 py-3 text-zinc-500">{p.or_number ?? "—"}</td>
                    <td className="px-4 py-3 font-mono text-zinc-700">{peso(num(p.total_amount))}</td>
                    <td className="px-4 py-3"><span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${p.status === "received" ? "bg-emerald-50 text-emerald-700 border-emerald-200" : p.status === "ordered" ? "bg-amber-50 text-amber-700 border-amber-200" : "bg-zinc-100 text-zinc-500 border-zinc-200"}`}>{p.status}</span></td>
                    <td className="px-4 py-3"><button onClick={() => deletePurchaseOrder(p.id).then(load)} className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 cursor-pointer"><Trash2 className="h-3.5 w-3.5" /></button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )
        )}
      </div>
    </div>
  );
}
