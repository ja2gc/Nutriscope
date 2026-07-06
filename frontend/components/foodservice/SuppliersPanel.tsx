"use client";

import React, { useEffect, useState, useCallback } from "react";
import { Truck, Plus, Pencil, Trash2, X, RefreshCw, Search, AlertTriangle } from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  Supplier,
  SupplierPayload,
  listSuppliers,
  saveSupplier,
  deleteSupplier,
} from "@/services/supplierService";

const inputCls =
  "w-full px-3 py-2 text-base border border-warm-200 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all";

function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-1">{children}</label>;
}

const EMPTY: SupplierPayload = { name: "", category: "", contact: "", address: "", payment_terms: "", notes: "" };

function SupplierForm({ initial, editingId, onSaved, onCancel }: {
  initial: SupplierPayload;
  editingId: number | null;
  onSaved: () => void;
  onCancel: () => void;
}) {
  const [form, setForm] = useState<SupplierPayload>(initial);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const set = (patch: Partial<SupplierPayload>) => setForm((f) => ({ ...f, ...patch }));

  async function handleSave() {
    if (!form.name.trim()) { setError("Vendor name is required."); return; }
    setSaving(true); setError("");
    try {
      await saveSupplier(editingId, {
        ...form,
        name: form.name.trim(),
        category: form.category || null,
        contact: form.contact || null,
        address: form.address || null,
        payment_terms: form.payment_terms || null,
        notes: form.notes || null,
      });
      onSaved();
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Save failed.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="bg-emerald-50/40 border border-emerald-100 rounded-2xl p-6 space-y-4 shadow-sm">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-extrabold text-emerald-700 uppercase tracking-wider">
          {editingId ? "Edit Vendor" : "New Vendor"}
        </h3>
        {error && (
          <span className="flex items-center gap-1 text-sm text-red-600 font-semibold">
            <AlertTriangle className="h-3 w-3" /> {error}
          </span>
        )}
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <Label>Vendor Name *</Label>
          <input value={form.name} onChange={(e) => set({ name: e.target.value })} className={inputCls} autoFocus />
        </div>
        <div>
          <Label>Description</Label>
          <input value={form.category ?? ""} onChange={(e) => set({ category: e.target.value })}
            placeholder="e.g. vegetables, meats" className={inputCls} />
        </div>
        <div>
          <Label>Contact</Label>
          <input value={form.contact ?? ""} onChange={(e) => set({ contact: e.target.value })}
            placeholder="phone / person" className={inputCls} />
        </div>
        <div>
          <Label>Payment Terms</Label>
          <input value={form.payment_terms ?? ""} onChange={(e) => set({ payment_terms: e.target.value })}
            placeholder="e.g. COD, 30 days" className={inputCls} />
        </div>
        <div className="sm:col-span-2">
          <Label>Address</Label>
          <input value={form.address ?? ""} onChange={(e) => set({ address: e.target.value })} className={inputCls} />
        </div>
        <div className="sm:col-span-2">
          <Label>Notes</Label>
          <textarea value={form.notes ?? ""} onChange={(e) => set({ notes: e.target.value })} rows={2}
            className={`${inputCls} resize-none`} />
        </div>
      </div>
      <div className="flex gap-2">
        <Button variant="primary" onClick={handleSave} disabled={saving} className="!py-1.5 !px-4 text-sm">
          {saving ? "Saving…" : editingId ? "Save Changes" : "Add Vendor"}
        </Button>
        <button onClick={onCancel} className="text-sm text-warm-500 hover:text-warm-700 flex items-center gap-1">
          <X className="h-3 w-3" /> Cancel
        </button>
      </div>
    </div>
  );
}

/**
 * Suppliers/Vendors CRUD, rendered as a tab inside the Procurement page
 * (A3 — merged from the former standalone /food-service/suppliers route).
 */
export function SuppliersPanel() {
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Supplier | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true); setError("");
    try {
      setSuppliers(await listSuppliers());
    } catch {
      setError("Failed to load suppliers.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  function openNew() { setEditing(null); setFormOpen(true); }
  function openEdit(s: Supplier) { setEditing(s); setFormOpen(true); }
  function onSaved() { setFormOpen(false); setEditing(null); load(); }

  async function handleDelete(id: number) {
    setDeleting(true);
    try {
      await deleteSupplier(id);
      setDeleteId(null);
      await load();
    } catch { } finally { setDeleting(false); }
  }

  const filtered = suppliers.filter((s) =>
    !search ||
    s.name.toLowerCase().includes(search.toLowerCase()) ||
    (s.category ?? "").toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p className="text-sm text-warm-500">
          Vendors used across procurement. Set a description (vegetables, meats…) and contact for reports.
        </p>
        <div className="flex items-center gap-3 shrink-0">
          <button onClick={load} className="flex items-center gap-1.5 text-sm text-warm-500 hover:text-warm-700 transition-colors">
            <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} />
            Refresh
          </button>
          <Button variant="primary" onClick={openNew} className="px-4 py-2.5 flex items-center gap-2">
            <Plus className="h-4 w-4" /> New Vendor
          </Button>
        </div>
      </div>

      {formOpen && (
        <SupplierForm
          initial={editing
            ? { name: editing.name, category: editing.category, contact: editing.contact, address: editing.address, payment_terms: editing.payment_terms, notes: editing.notes }
            : EMPTY}
          editingId={editing?.id ?? null}
          onSaved={onSaved}
          onCancel={() => { setFormOpen(false); setEditing(null); }}
        />
      )}

      {/* Search */}
      <div className="relative max-w-sm">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-warm-400" />
        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search vendors…"
          className="w-full pl-9 pr-3 py-2 text-sm border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
      </div>

      {/* Table */}
      <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-x-auto">
        {loading ? (
          <div className="py-16 text-center text-sm text-warm-400">Loading…</div>
        ) : error ? (
          <div className="py-16 text-center text-sm text-red-500">{error}</div>
        ) : filtered.length === 0 ? (
          <div className="py-16 text-center">
            <Truck className="h-8 w-8 text-warm-300 mx-auto mb-3" />
            <p className="text-sm text-warm-400 font-medium">No vendors yet. Add your first one.</p>
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-warm-50 border-b border-warm-100">
              <tr>
                {["Vendor", "Description", "Contact", "Payment Terms", "Actions"].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100">
              {filtered.map((s) => (
                <tr key={s.id} className="hover:bg-warm-50/60 transition-colors">
                  <td className="px-4 py-3 font-semibold text-warm-800">{s.name}</td>
                  <td className="px-4 py-3 text-warm-600">{s.category || "—"}</td>
                  <td className="px-4 py-3 text-warm-600">{s.contact || "—"}</td>
                  <td className="px-4 py-3 text-warm-500">{s.payment_terms || "—"}</td>
                  <td className="px-4 py-3">
                    {deleteId === s.id ? (
                      <div className="flex items-center gap-2">
                        <span className="text-red-600 text-xs font-semibold">Delete?</span>
                        <button onClick={() => handleDelete(s.id)} disabled={deleting}
                          className="text-xs font-bold text-red-600 hover:underline disabled:opacity-50">
                          {deleting ? "…" : "Yes"}
                        </button>
                        <button onClick={() => setDeleteId(null)} className="text-xs font-bold text-warm-500 hover:underline">No</button>
                      </div>
                    ) : (
                      <div className="flex items-center gap-1">
                        <button onClick={() => openEdit(s)} title="Edit"
                          className="p-1.5 rounded-lg hover:bg-warm-100 text-warm-500 transition-colors cursor-pointer">
                          <Pencil className="h-3.5 w-3.5" />
                        </button>
                        <button onClick={() => setDeleteId(s.id)} title="Delete"
                          className="p-1.5 rounded-lg hover:bg-red-50 text-warm-500 hover:text-red-600 transition-colors cursor-pointer">
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
