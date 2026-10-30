"use client";

import React, { useEffect, useState, useCallback, useRef, useImperativeHandle, type Ref } from "react";
import { Truck, Plus, Pencil, Trash2, X, AlertTriangle } from "lucide-react";
import { useUnsavedChanges, type EditorLeaveHandle } from "@/hooks/useUnsavedChanges";
import { ContactDetailsFields, ContactDetailsSummary } from '@/components/ui/ContactDetailsFields';
import { contactDetailsFrom, contactDetailsPayload, contactDetailsErrors, type ContactDetailsErrors } from '@/lib/contactDetails';
import { Button } from "@/components/ui/Button";
import { SectionLoading } from "@/components/ui/SectionLoading";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import SearchInput from "@/components/ui/SearchInput";
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

const EMPTY: SupplierPayload = { name: "", category: "", contact: "", address: "", payment_terms: "", notes: "", additional_contacts: [], preferred_contact_id: null };

function SupplierForm({ initial, editingId, onSaved, onCancel, leaveRef }: {
  initial: SupplierPayload;
  editingId: number | null;
  onSaved: (saved: Supplier) => void;
  onCancel: () => void;
  leaveRef?: Ref<EditorLeaveHandle>;
}) {
  const [form, setForm] = useState<SupplierPayload>(initial);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [contactErrors, setContactErrors] = useState<ContactDetailsErrors>({});

  const set = (patch: Partial<SupplierPayload>) => setForm((f) => ({ ...f, ...patch }));

  const guard = useUnsavedChanges({ dirty: JSON.stringify(form) !== JSON.stringify(initial), onSave: handleSave });
  useImperativeHandle(leaveRef, () => ({ requestLeave: guard.requestLeave }), [guard.requestLeave]);

  async function handleSave() {
    if (!form.name.trim()) { setError("Vendor name is required."); return false; }
    const contacts = contactDetailsFrom(form);
    const validation = contactDetailsErrors(contacts);
    setContactErrors(validation);
    if (Object.keys(validation).length) return false;
    setSaving(true); setError("");
    try {
      const saved = await saveSupplier(editingId, {
        ...form,
        ...contactDetailsPayload(contacts),
        name: form.name.trim(),
        category: form.category || null,
        address: form.address || null,
        payment_terms: form.payment_terms || null,
        notes: form.notes || null,
      });
      onSaved(saved);
      return true;
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Save failed.");
      return false;
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="bg-white border border-warm-200 rounded-2xl p-6 space-y-4 shadow-sm">
      {guard.dialog}
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-extrabold text-warm-900 uppercase tracking-wider">
          {editingId ? "Edit Vendor" : "New Vendor"}
        </h3>
        {error && (
          <span role="alert" className="flex items-center gap-1 text-sm text-red-600 font-semibold">
            <AlertTriangle className="h-3 w-3" /> {error}
          </span>
        )}
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <Label>Vendor Name *</Label>
          <input aria-label="Vendor name" value={form.name} maxLength={255} onChange={(e) => set({ name: e.target.value })} className={inputCls} autoFocus />
        </div>
        <div>
          <Label>Description</Label>
          <input aria-label="Description" value={form.category ?? ""} onChange={(e) => set({ category: e.target.value })}
            className={inputCls} />
        </div>
        <div>
          <Label>Payment Terms</Label>
          <input aria-label="Payment terms" value={form.payment_terms ?? ""} onChange={(e) => set({ payment_terms: e.target.value })}
            className={inputCls} />
        </div>
        <div className="sm:col-span-2">
          <Label>Address</Label>
          <input aria-label="Address" value={form.address ?? ""} onChange={(e) => set({ address: e.target.value })} className={inputCls} />
        </div>
        <div className="sm:col-span-2">
          <Label>Notes</Label>
          <textarea aria-label="Notes" value={form.notes ?? ""} onChange={(e) => set({ notes: e.target.value })} rows={2}
            className={`${inputCls} resize-none`} />
        </div>
      </div>
      <section className="space-y-3"><h4 className="text-sm font-bold uppercase tracking-wider text-warm-700">Contact details</h4><ContactDetailsFields value={contactDetailsFrom(form)} onChange={contacts => { set(contacts); setContactErrors({}); setError(''); }} errors={contactErrors} disabled={saving} /></section>
      <div className="flex gap-2">
        <Button variant="primary" onClick={handleSave} disabled={saving} className="!py-1.5 !px-4 text-sm">
          {saving ? "Saving…" : editingId ? "Save Changes" : "Add Vendor"}
        </Button>
        <button onClick={() => guard.requestLeave(onCancel)} className="text-sm text-warm-500 hover:text-warm-700 flex items-center gap-1">
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
export function SuppliersPanel({ leaveRef }: { leaveRef?: Ref<EditorLeaveHandle> } = {}) {
  const formLeaveRef = useRef<EditorLeaveHandle | null>(null);
  const [formVersion, setFormVersion] = useState(0);
  useImperativeHandle(leaveRef, () => ({ requestLeave: next => formLeaveRef.current ? formLeaveRef.current.requestLeave(next) : next() }), []);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Supplier | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async (quiet = false) => {
    if (!quiet) setLoading(true);
    setError("");
    try {
      const result = await listSuppliers(page, search);
      setSuppliers(result.data);
      setMeta(result.meta);
    } catch {
      setError("Failed to load suppliers.");
    } finally {
      setLoading(false);
    }
  }, [page, search]);

  useEffect(() => {
    const timer = setTimeout(() => { void load(); }, 250);
    return () => clearTimeout(timer);
  }, [load]);

  useEffect(() => { setPage(1); }, [search]);

  function openNew() { const next = () => { setFormVersion(value => value + 1); setEditing(null); setFormOpen(true); }; if (formLeaveRef.current) formLeaveRef.current.requestLeave(next); else next(); }
  function openEdit(s: Supplier) { const next = () => { setFormVersion(value => value + 1); setEditing(s); setFormOpen(true); }; if (formLeaveRef.current) formLeaveRef.current.requestLeave(next); else next(); }
  function onSaved(saved: Supplier) {
    setFormOpen(false);
    setEditing(null);
    if (editing) setSuppliers(current => current.map(item => item.id === saved.id ? saved : item));
    if (!editing || editing.name !== saved.name || search.trim()) void load(true);
  }

  async function handleDelete(id: number) {
    setDeleting(true);
    try {
      await deleteSupplier(id);
      setDeleteId(null);
      await load(true);
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Delete failed."); } finally { setDeleting(false); }
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p className="text-sm text-warm-500">
          Descriptions and contacts appear on procurement reports.
        </p>
        <div className="flex items-center gap-3 shrink-0">
          <Button variant="primary" onClick={openNew} className="px-4 py-2.5 flex items-center gap-2">
            <Plus className="h-4 w-4" /> New Vendor
          </Button>
        </div>
      </div>

      {formOpen && (
        <SupplierForm
          key={`${editing?.id ?? 'new'}-${formVersion}`}
          leaveRef={formLeaveRef}
          initial={editing
            ? { name: editing.name, category: editing.category, ...contactDetailsFrom(editing), address: editing.address, payment_terms: editing.payment_terms, notes: editing.notes }
            : EMPTY}
          editingId={editing?.id ?? null}
          onSaved={onSaved}
          onCancel={() => { setFormOpen(false); setEditing(null); }}
        />
      )}

      {/* Search */}
      <SearchInput className="max-w-sm" label="Search vendors" value={search} onChange={setSearch} loading={loading} />

      {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
      {/* Table */}
      <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-x-auto">
        {loading ? (
          <SectionLoading label="Loading vendors…" skeleton />
        ) : suppliers.length === 0 ? (
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
              {suppliers.map((s) => (
                <tr key={s.id} className="hover:bg-warm-50/60 transition-colors">
                  <td className="px-4 py-3 font-semibold text-warm-800">{s.name}</td>
                  <td className="px-4 py-3 text-warm-600">{s.category || "—"}</td>
                  <td className="min-w-64 px-4 py-3 text-warm-600"><ContactDetailsSummary value={contactDetailsFrom(s)} compact /></td>
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
                        <button onClick={() => openEdit(s)} title="Edit" aria-label={`Edit ${s.name}`}
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
        <Pagination meta={meta} page={page} onPageChange={setPage} />
      </div>
    </div>
  );
}
