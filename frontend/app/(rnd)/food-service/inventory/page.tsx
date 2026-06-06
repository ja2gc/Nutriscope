"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import {
  Salad, Plus, Search, RefreshCw, Pencil, Trash2, ChevronDown, X, AlertTriangle,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  InventoryItem,
  CreateInventoryPayload,
  UpdateInventoryPayload,
  StockStatus,
  getStockStatus,
  listInventory,
  createInventory,
  updateInventory,
  deleteInventory,
  restockInventory,
} from "@/services/inventoryService";

// ─── Types ────────────────────────────────────────────────────────────────────

interface FoodItemOption {
  id: number;
  name: string;
  serving_unit?: string;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const STATUS_META: Record<StockStatus, { label: string; cls: string }> = {
  low:      { label: "Low Stock",     cls: "bg-red-50 text-red-700 border border-red-200" },
  expiring: { label: "Expiring Soon", cls: "bg-amber-50 text-amber-700 border border-amber-200" },
  ok:       { label: "OK",            cls: "bg-emerald-50 text-emerald-700 border border-emerald-200" },
};

function StatusBadge({ status }: { status: StockStatus }) {
  const { label, cls } = STATUS_META[status];
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ${cls}`}>
      {label}
    </span>
  );
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("en-PH", {
    month: "short", day: "numeric", year: "numeric",
  });
}

// ─── Modal ────────────────────────────────────────────────────────────────────

interface ModalProps {
  mode: "add" | "edit";
  item?: InventoryItem;
  foodItems: FoodItemOption[];
  onClose: () => void;
  onSaved: (updated: InventoryItem) => void;
}

function InventoryModal({ mode, item, foodItems, onClose, onSaved }: ModalProps) {
  const [foodItemId, setFoodItemId] = useState<number | "">(item?.food_item_id ?? "");
  const [qty, setQty]               = useState(item ? parseFloat(item.quantity_in_stock).toString() : "");
  const [unit, setUnit]             = useState(item?.unit ?? "");
  const [threshold, setThreshold]   = useState(item?.minimum_stock_threshold ?? "");
  const [usageRate, setUsageRate]   = useState(item?.usage_rate ?? "");
  const [expiry, setExpiry]         = useState(item?.expiry_date ?? "");
  const [notes, setNotes]           = useState(item?.notes ?? "");
  const [saving, setSaving]         = useState(false);
  const [error, setError]           = useState("");

  useEffect(() => {
    if (mode === "add" && foodItemId) {
      const found = foodItems.find((f) => f.id === foodItemId);
      if (found?.serving_unit && !unit) setUnit(found.serving_unit);
    }
  }, [foodItemId, foodItems, mode, unit]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!foodItemId || !qty || !unit) {
      setError("Food item, quantity, and unit are required.");
      return;
    }
    setSaving(true);
    setError("");
    try {
      let result: InventoryItem;
      if (mode === "add") {
        const payload: CreateInventoryPayload = {
          food_item_id:             foodItemId as number,
          quantity_in_stock:        parseFloat(qty),
          unit,
          expiry_date:              expiry || null,
          usage_rate:               usageRate ? parseFloat(usageRate as string) : null,
          minimum_stock_threshold:  threshold ? parseFloat(threshold as string) : null,
          notes:                    notes || null,
        };
        result = await createInventory(payload);
      } else {
        const payload: UpdateInventoryPayload = {
          quantity_in_stock:        parseFloat(qty),
          unit,
          expiry_date:              expiry || null,
          usage_rate:               usageRate ? parseFloat(usageRate as string) : null,
          minimum_stock_threshold:  threshold ? parseFloat(threshold as string) : null,
          notes:                    notes || null,
        };
        result = await updateInventory(item!.id, payload);
      }
      onSaved(result);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "An error occurred.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
          <h3 className="text-sm font-bold text-zinc-900">
            {mode === "add" ? "Add Stock Entry" : "Edit Stock Entry"}
          </h3>
          <button onClick={onClose} className="text-zinc-400 hover:text-zinc-600 transition-colors">
            <X className="h-4 w-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="px-6 py-5 space-y-4">
          {error && (
            <div className="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
              <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
              {error}
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold text-zinc-700 mb-1.5">
              Food Item <span className="text-red-500">*</span>
            </label>
            {mode === "edit" ? (
              <div className="px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-lg text-xs text-zinc-600 font-medium">
                {item?.food_item.name}
              </div>
            ) : (
              <select
                value={foodItemId}
                onChange={(e) => setFoodItemId(e.target.value ? parseInt(e.target.value) : "")}
                className="w-full px-3 py-2 text-xs border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                required
              >
                <option value="">Select food item...</option>
                {foodItems.map((f) => (
                  <option key={f.id} value={f.id}>{f.name}</option>
                ))}
              </select>
            )}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold text-zinc-700 mb-1.5">
                Qty in Stock <span className="text-red-500">*</span>
              </label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={qty}
                onChange={(e) => setQty(e.target.value)}
                className="w-full px-3 py-2 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                required
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-zinc-700 mb-1.5">
                Unit <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={unit}
                onChange={(e) => setUnit(e.target.value)}
                placeholder="kg, pcs, L..."
                className="w-full px-3 py-2 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                required
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold text-zinc-700 mb-1.5">Min Threshold</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={threshold}
                onChange={(e) => setThreshold(e.target.value)}
                placeholder="e.g. 5"
                className="w-full px-3 py-2 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-zinc-700 mb-1.5">Usage Rate / day</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={usageRate}
                onChange={(e) => setUsageRate(e.target.value)}
                placeholder="e.g. 2.5"
                className="w-full px-3 py-2 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-zinc-700 mb-1.5">Expiry Date</label>
            <input
              type="date"
              value={expiry}
              onChange={(e) => setExpiry(e.target.value)}
              className="w-full px-3 py-2 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-zinc-700 mb-1.5">Notes</label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={2}
              placeholder="Optional notes..."
              className="w-full px-3 py-2 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent resize-none"
            />
          </div>

          <div className="flex gap-2 pt-1">
            <Button type="button" variant="secondary" onClick={onClose} className="flex-1">
              Cancel
            </Button>
            <Button type="submit" variant="primary" disabled={saving} className="flex-1">
              {saving ? "Saving..." : mode === "add" ? "Add Entry" : "Save Changes"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ─── Restock Row ──────────────────────────────────────────────────────────────

function RestockRow({
  item,
  onRestocked,
  onClose,
}: {
  item: InventoryItem;
  onRestocked: (updated: InventoryItem) => void;
  onClose: () => void;
}) {
  const [qty, setQty]       = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError]   = useState("");

  async function handleRestock() {
    const amount = parseFloat(qty);
    if (!amount || amount <= 0) { setError("Enter valid quantity."); return; }
    setSaving(true);
    setError("");
    try {
      const updated = await restockInventory(item.id, amount);
      onRestocked(updated);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Restock failed.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <tr className="bg-emerald-50/60 border-t border-emerald-100">
      <td colSpan={7} className="px-4 py-3">
        <div className="flex items-center gap-3 flex-wrap">
          <span className="text-xs text-zinc-600 font-medium">
            Add stock for <strong>{item.food_item.name}</strong>:
          </span>
          <input
            type="number"
            min="0.01"
            step="0.01"
            value={qty}
            onChange={(e) => setQty(e.target.value)}
            placeholder="Qty to add"
            className="w-32 px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
            autoFocus
          />
          <span className="text-xs text-zinc-500">{item.unit}</span>
          {error && <span className="text-xs text-red-600">{error}</span>}
          <Button
            variant="primary"
            onClick={handleRestock}
            disabled={saving}
            className="!py-1.5 !px-3 text-xs"
          >
            {saving ? "..." : "Confirm"}
          </Button>
          <button onClick={onClose} className="text-zinc-400 hover:text-zinc-600">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      </td>
    </tr>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

type FilterTab = "all" | StockStatus;

export default function InventoryPage() {
  const [items, setItems]       = useState<InventoryItem[]>([]);
  const [foodItems, setFoodItems] = useState<FoodItemOption[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState("");
  const [search, setSearch]     = useState("");
  const [tab, setTab]           = useState<FilterTab>("all");
  const [modal, setModal]       = useState<null | { mode: "add" } | { mode: "edit"; item: InventoryItem }>(null);
  const [restockId, setRestockId] = useState<number | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const data = await listInventory();
      setItems(data);
    } catch {
      setError("Failed to load inventory.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    fetch("/api/rnd/food-items")
      .then((r) => r.json())
      .then((json) => setFoodItems(json.data ?? []))
      .catch(() => {});
  }, []);

  const lowCount      = items.filter((i) => getStockStatus(i) === "low").length;
  const expiringCount = items.filter((i) => getStockStatus(i) === "expiring").length;

  const filtered = items.filter((item) => {
    const matchSearch = item.food_item.name.toLowerCase().includes(search.toLowerCase());
    const matchTab    = tab === "all" || getStockStatus(item) === tab;
    return matchSearch && matchTab;
  });

  function handleSaved(updated: InventoryItem) {
    setItems((prev) => {
      const idx = prev.findIndex((i) => i.id === updated.id);
      if (idx >= 0) {
        const next = [...prev];
        next[idx] = updated;
        return next;
      }
      return [updated, ...prev];
    });
    setModal(null);
  }

  function handleRestocked(updated: InventoryItem) {
    setItems((prev) => prev.map((i) => (i.id === updated.id ? updated : i)));
    setRestockId(null);
  }

  async function handleDelete() {
    if (!deleteId) return;
    setDeleting(true);
    try {
      await deleteInventory(deleteId);
      setItems((prev) => prev.filter((i) => i.id !== deleteId));
      setDeleteId(null);
    } catch {
      // keep confirm open
    } finally {
      setDeleting(false);
    }
  }

  const TABS: { key: FilterTab; label: string }[] = [
    { key: "all",      label: "All" },
    { key: "low",      label: `Low Stock${lowCount ? ` (${lowCount})` : ""}` },
    { key: "expiring", label: `Expiring${expiringCount ? ` (${expiringCount})` : ""}` },
  ];

  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-400">Food Service</span>
        <span className="text-zinc-300">/</span>
        <span className="font-bold text-zinc-650">Inventory Logs</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Salad className="h-5 w-5 text-emerald-600" />
            Kitchen &amp; Food Service Inventory
          </h2>
          <p className="text-xs text-zinc-500 mt-1 select-none">
            Monitor raw ingredient stock, capture unit pricing, and track expiration milestones.
          </p>
        </div>
        <Button
          variant="primary"
          onClick={() => setModal({ mode: "add" })}
          className="sm:w-auto px-4 py-2.5 shrink-0 flex items-center justify-center gap-2"
        >
          <Plus className="h-4 w-4" />
          Add Stock Entry
        </Button>
      </div>

      {/* Stat chips */}
      <div className="flex flex-wrap gap-3">
        {[
          {
            label: "Total Items",
            value: items.length,
            cls: "bg-zinc-50 border-zinc-200 text-zinc-700",
          },
          {
            label: "Low Stock",
            value: lowCount,
            cls: lowCount > 0
              ? "bg-red-50 border-red-200 text-red-700"
              : "bg-zinc-50 border-zinc-200 text-zinc-400",
          },
          {
            label: "Expiring Soon",
            value: expiringCount,
            cls: expiringCount > 0
              ? "bg-amber-50 border-amber-200 text-amber-700"
              : "bg-zinc-50 border-zinc-200 text-zinc-400",
          },
        ].map(({ label, value, cls }) => (
          <div
            key={label}
            className={`px-4 py-2.5 rounded-xl border text-xs font-semibold flex items-center gap-2 ${cls}`}
          >
            <span className="text-lg font-extrabold">{value}</span>
            <span className="opacity-70">{label}</span>
          </div>
        ))}
      </div>

      {/* Search + Tabs */}
      <div className="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
        <div className="relative flex-1 max-w-sm">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search food item..."
            className="w-full pl-9 pr-3 py-2 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
          />
        </div>
        <div className="flex gap-1 bg-zinc-100 rounded-lg p-1">
          {TABS.map(({ key, label }) => (
            <button
              key={key}
              onClick={() => setTab(key)}
              className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors ${
                tab === key
                  ? "bg-white text-zinc-900 shadow-sm"
                  : "text-zinc-500 hover:text-zinc-700"
              }`}
            >
              {label}
            </button>
          ))}
        </div>
        <button
          onClick={load}
          className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700 transition-colors"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} />
          Refresh
        </button>
      </div>

      {/* Table */}
      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-hidden">
        {loading ? (
          <div className="py-16 text-center text-xs text-zinc-400">Loading inventory...</div>
        ) : error ? (
          <div className="py-16 text-center text-xs text-red-500">{error}</div>
        ) : filtered.length === 0 ? (
          <div className="py-16 text-center">
            <Salad className="h-8 w-8 text-zinc-300 mx-auto mb-3" />
            <p className="text-xs text-zinc-400 font-medium">
              {search || tab !== "all" ? "No items match your filter." : "No inventory entries yet."}
            </p>
            {!search && tab === "all" && (
              <button
                onClick={() => setModal({ mode: "add" })}
                className="mt-3 text-xs text-emerald-600 font-semibold hover:underline"
              >
                Add first stock entry
              </button>
            )}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead className="bg-zinc-50 border-b border-zinc-100">
                <tr>
                  {["Food Item", "Qty in Stock", "Unit", "Min Threshold", "Expiry Date", "Status", "Actions"].map(
                    (h) => (
                      <th
                        key={h}
                        className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap"
                      >
                        {h}
                      </th>
                    )
                  )}
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {filtered.map((item) => {
                  const status      = getStockStatus(item);
                  const isRestocking = restockId === item.id;
                  const isConfirmDelete = deleteId === item.id;

                  return (
                    <React.Fragment key={item.id}>
                      <tr className={`hover:bg-zinc-50 transition-colors ${isRestocking ? "bg-emerald-50/30" : ""}`}>
                        <td className="px-4 py-3 font-semibold text-zinc-800 whitespace-nowrap">
                          {item.food_item.name}
                        </td>
                        <td className="px-4 py-3 font-mono text-zinc-700">
                          {parseFloat(item.quantity_in_stock).toFixed(2)}
                        </td>
                        <td className="px-4 py-3 text-zinc-500">{item.unit}</td>
                        <td className="px-4 py-3 text-zinc-500">
                          {item.minimum_stock_threshold
                            ? parseFloat(item.minimum_stock_threshold).toFixed(2)
                            : "—"}
                        </td>
                        <td className="px-4 py-3 text-zinc-500 whitespace-nowrap">
                          {formatDate(item.expiry_date)}
                        </td>
                        <td className="px-4 py-3">
                          <StatusBadge status={status} />
                        </td>
                        <td className="px-4 py-3">
                          {isConfirmDelete ? (
                            <div className="flex items-center gap-2">
                              <span className="text-red-600 text-[10px] font-semibold">Delete?</span>
                              <button
                                onClick={handleDelete}
                                disabled={deleting}
                                className="text-[10px] font-bold text-red-600 hover:underline disabled:opacity-50"
                              >
                                {deleting ? "..." : "Yes"}
                              </button>
                              <button
                                onClick={() => setDeleteId(null)}
                                className="text-[10px] font-bold text-zinc-500 hover:underline"
                              >
                                No
                              </button>
                            </div>
                          ) : (
                            <div className="flex items-center gap-1">
                              <button
                                onClick={() => setRestockId(isRestocking ? null : item.id)}
                                title="Restock"
                                className={`p-1.5 rounded-lg transition-colors ${
                                  isRestocking
                                    ? "bg-emerald-100 text-emerald-700"
                                    : "hover:bg-zinc-100 text-zinc-500"
                                }`}
                              >
                                <ChevronDown
                                  className={`h-3.5 w-3.5 transition-transform ${isRestocking ? "rotate-180" : ""}`}
                                />
                              </button>
                              <button
                                onClick={() => setModal({ mode: "edit", item })}
                                title="Edit"
                                className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 transition-colors"
                              >
                                <Pencil className="h-3.5 w-3.5" />
                              </button>
                              <button
                                onClick={() => { setDeleteId(item.id); setRestockId(null); }}
                                title="Delete"
                                className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 transition-colors"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </button>
                            </div>
                          )}
                        </td>
                      </tr>
                      {isRestocking && (
                        <RestockRow
                          item={item}
                          onRestocked={handleRestocked}
                          onClose={() => setRestockId(null)}
                        />
                      )}
                    </React.Fragment>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Add/Edit Modal */}
      {modal && (
        <InventoryModal
          mode={modal.mode}
          item={modal.mode === "edit" ? modal.item : undefined}
          foodItems={foodItems}
          onClose={() => setModal(null)}
          onSaved={handleSaved}
        />
      )}
    </div>
  );
}
