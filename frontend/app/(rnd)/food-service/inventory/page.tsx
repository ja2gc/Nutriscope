"use client";

import React, { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { Boxes, Plus, Pencil, Trash2, RefreshCw, X, Search } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { useAuth } from "@/contexts/AuthContext";
import {
  CatalogItem, FsItemKind, listCatalog, createFsItem, updateFsItem, deleteFsItem, CreateFsItemPayload,
} from "@/services/fsCatalogService";
import { listSuppliers, Supplier } from "@/services/supplierService";
import { CATALOG_UNIT_OPTIONS } from "@/lib/units";

const peso = (n: number | string | null) => `₱${(n ? parseFloat(String(n)) : 0).toFixed(2)}`;
const inputCls = "w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500";

const TABS: { key: FsItemKind; label: string }[] = [
  { key: "ingredient", label: "Ingredients" },
  { key: "supply", label: "Supplies" },
];

function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">{children}</label>;
}

interface FormState {
  name: string; category: string; default_supplier_id: string;
  base_unit: string; purchase_price: string;
}
const emptyForm: FormState = { name: "", category: "", default_supplier_id: "", base_unit: "", purchase_price: "" };

function ItemFormModal({ kind, editing, suppliers, onClose, onSaved }: {
  kind: FsItemKind; editing: CatalogItem | null; suppliers: Supplier[];
  onClose: () => void; onSaved: () => void;
}) {
  const isSupply = kind === "supply";
  const [form, setForm] = useState<FormState>(emptyForm);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (editing) {
      setForm({
        name: editing.name,
        category: editing.category ?? "",
        default_supplier_id: editing.default_supplier_id ? String(editing.default_supplier_id) : "",
        base_unit: editing.base_unit ?? "",
        purchase_price: editing.purchase_price ?? "",
      });
    } else {
      setForm(emptyForm);
    }
  }, [editing]);

  const set = (k: keyof FormState, v: string) => setForm((f) => ({ ...f, [k]: v }));

  async function save() {
    setError(null);
    if (!form.name.trim()) { setError("Name is required."); return; }
    // Ingredients carry a single unit; supplies have no unit input (cost-per-unit only).
    if (!isSupply && !form.base_unit.trim()) { setError("Unit is required."); return; }
    const price = parseFloat(form.purchase_price);
    if (!Number.isFinite(price) || price < 0) { setError("Enter a valid cost."); return; }

    setSaving(true);
    try {
      const payload: CreateFsItemPayload = {
        name: form.name.trim(),
        kind,
        category: form.category.trim() || null,
        base_unit: isSupply ? "unit" : form.base_unit.trim(),
        purchase_price: price,
        default_supplier_id: form.default_supplier_id ? parseInt(form.default_supplier_id) : null,
      };
      if (editing) {
        await updateFsItem(editing.id, payload);
      } else {
        await createFsItem(payload);
      }
      onSaved();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" onClick={onClose}>
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-extrabold text-zinc-900 uppercase tracking-wider">
            {editing ? "Edit" : "New"} {TABS.find((t) => t.key === kind)?.label.replace(/s$/, "")}
          </h3>
          <button onClick={onClose} className="text-zinc-400 hover:text-zinc-700"><X className="h-4 w-4" /></button>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="sm:col-span-2">
            <Label>Name</Label>
            <input value={form.name} onChange={(e) => set("name", e.target.value)} className={inputCls} />
          </div>
          <div>
            <Label>Category</Label>
            <input value={form.category} onChange={(e) => set("category", e.target.value)} className={inputCls} />
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
              <Label>Cost / unit (₱)</Label>
              <input type="number" min="0" step="0.01" value={form.purchase_price} onChange={(e) => set("purchase_price", e.target.value)} className={inputCls} />
            </div>
          ) : (
            <>
              <div>
                <Label>Unit</Label>
                <select value={form.base_unit} onChange={(e) => set("base_unit", e.target.value)} className={`${inputCls} bg-white`}>
                  <option value="">Select unit…</option>
                  {CATALOG_UNIT_OPTIONS.map((u) => <option key={u} value={u}>{u}</option>)}
                </select>
              </div>
              <div>
                <Label>Unit / cost (₱)</Label>
                <input type="number" min="0" step="0.01" value={form.purchase_price} onChange={(e) => set("purchase_price", e.target.value)} className={inputCls} />
              </div>
            </>
          )}
        </div>

        {error && <p className="text-xs text-red-600 font-semibold">{error}</p>}
        <div className="flex justify-end gap-3 pt-1">
          <Button variant="secondary" onClick={onClose} className="px-4 py-2">Cancel</Button>
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
  const [items, setItems] = useState<CatalogItem[]>([]);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<CatalogItem | null>(null);

  // Load the whole catalog once; Ingredients covers food items, Supplies covers supplies.
  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [c, s] = await Promise.all([listCatalog(), listSuppliers()]);
      setItems(c); setSuppliers(s);
    } finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);

  const filtered = useMemo(
    () => items
      .filter((i) => tab === "supply" ? i.kind === "supply" : i.kind === "ingredient")
      .filter((i) => i.name.toLowerCase().includes(search.trim().toLowerCase())),
    [items, tab, search],
  );

  async function remove(item: CatalogItem) {
    if (!confirm(`Delete "${item.name}" from the catalog?`)) return;
    try { await deleteFsItem(item.id); load(); }
    catch (e) { alert(e instanceof Error ? e.message : "Failed to delete."); }
  }

  const isSupply = tab === "supply";

  return (
    <div className="space-y-6 font-sans">
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700">Home</Link><span>/</span>
        <span>Food Service</span><span>/</span>
        <span className="font-bold text-zinc-600">Inventory</span>
      </div>

      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Boxes className="h-5 w-5 text-emerald-600" /> Inventory — Reference Catalog
          </h2>
          <p className="text-xs text-zinc-500 mt-1"> Catalogs of foods and Supplies</p>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          <button onClick={load} className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700">
            <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh
          </button>
          {isRnd && (
            <Button variant="primary" onClick={() => { setEditing(null); setModalOpen(true); }} className="px-4 py-2.5 flex items-center gap-2">
              <Plus className="h-4 w-4" /> New {TABS.find((t) => t.key === tab)?.label.replace(/s$/, "")}
            </Button>
          )}
        </div>
      </div>

      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div className="flex gap-2">
          {TABS.map((t) => (
            <button key={t.key} onClick={() => setTab(t.key)}
              className={`px-3 py-1.5 text-xs font-semibold border-b-2 ${tab === t.key ? "border-emerald-600 text-emerald-700" : "border-transparent text-zinc-500 hover:text-zinc-800"}`}>
              {t.label}
            </button>
          ))}
        </div>
        <div className="flex items-center gap-2 px-3 py-1.5 border border-zinc-200 rounded-lg">
          <Search className="h-3.5 w-3.5 text-zinc-400" />
          <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" className="text-sm outline-none w-48" />
        </div>
      </div>

      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
        {loading ? (
          <div className="py-16 text-center text-xs text-zinc-400">Loading…</div>
        ) : filtered.length === 0 ? (
          <div className="py-16 text-center text-xs text-zinc-400">No items yet.</div>
        ) : (
          <table className="w-full text-xs">
            <thead className="bg-zinc-50 border-b border-zinc-100">
              <tr>
                {(isSupply
                  ? ["Name", "Category", "Vendor", "Cost", "Actions"]
                  : ["Name", "Category", "Vendor", "Unit", "Cost", "Actions"]
                ).map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100">
              {filtered.map((it) => (
                <tr key={it.id} className="hover:bg-zinc-50/60">
                  <td className="px-4 py-3 font-semibold text-zinc-800">
                    {it.name}
                  </td>
                  <td className="px-4 py-3 text-zinc-500">{it.category ?? "—"}</td>
                  <td className="px-4 py-3 text-zinc-500">{it.vendor ?? "—"}{it.vendor_locked && <span className="ml-1 text-[9px] text-amber-600 font-bold">🔒</span>}</td>
                  {!isSupply && <td className="px-4 py-3 text-zinc-500">{it.base_unit}</td>}
                  <td className="px-4 py-3 text-zinc-700 font-mono">
                    {isSupply ? peso(it.purchase_price) : `${peso(it.purchase_price)} / ${it.base_unit}`}
                  </td>
                  <td className="px-4 py-3">
                    {isRnd ? (
                      <div className="flex items-center gap-1">
                        <button onClick={() => { setEditing(it); setModalOpen(true); }} className="p-1.5 rounded-lg text-zinc-500 hover:text-emerald-600 hover:bg-emerald-50 cursor-pointer" title="Edit" aria-label={`Edit ${it.name}`}><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={() => remove(it)} className="p-1.5 rounded-lg text-zinc-500 hover:text-red-600 hover:bg-red-50 cursor-pointer" title="Delete" aria-label={`Delete ${it.name}`}><Trash2 className="h-3.5 w-3.5" /></button>
                      </div>
                    ) : <span className="text-zinc-300">—</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {modalOpen && isRnd && (
        <ItemFormModal
          kind={tab}
          editing={editing}
          suppliers={suppliers}
          onClose={() => setModalOpen(false)}
          onSaved={() => { setModalOpen(false); load(); }}
        />
      )}
    </div>
  );
}
