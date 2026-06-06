"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import {
  Salad, Search, RefreshCw, Pencil, Trash2, ChevronDown, X,
  AlertTriangle, Package, BookOpen,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  InventoryRow,
  InventoryRecord,
  FoodItemRef,
  RecipeRef,
  StockStatus,
  buildInventoryRows,
  listInventory,
  upsertInventory,
  deleteInventory,
  restockInventory,
} from "@/services/inventoryService";

// ─── Helpers ──────────────────────────────────────────────────────────────────

type FilterTab = "all" | StockStatus;

const STATUS_META: Record<StockStatus, { label: string; cls: string }> = {
  low:       { label: "Low Stock",     cls: "bg-red-50 text-red-700 border border-red-200" },
  expiring:  { label: "Expiring Soon", cls: "bg-amber-50 text-amber-700 border border-amber-200" },
  ok:        { label: "OK",            cls: "bg-emerald-50 text-emerald-700 border border-emerald-200" },
  untracked: { label: "Untracked",     cls: "bg-zinc-100 text-zinc-500 border border-zinc-200" },
};

function StatusBadge({ status }: { status: StockStatus }) {
  const { label, cls } = STATUS_META[status];
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ${cls}`}>
      {label}
    </span>
  );
}

function TypeBadge({ type }: { type: "food_item" | "recipe" }) {
  return type === "recipe" ? (
    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-50 text-violet-700 border border-violet-200">
      <BookOpen className="h-2.5 w-2.5" /> Recipe
    </span>
  ) : (
    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 text-sky-700 border border-sky-200">
      <Package className="h-2.5 w-2.5" /> Ingredient
    </span>
  );
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("en-PH", {
    month: "short", day: "numeric", year: "numeric",
  });
}

// ─── Inline Edit Row ──────────────────────────────────────────────────────────

interface EditRowProps {
  row: InventoryRow;
  onSaved: (record: InventoryRecord) => void;
  onClose: () => void;
}

function EditRow({ row, onSaved, onClose }: EditRowProps) {
  const [qty, setQty]             = useState(row.inventoryId ? parseFloat(row.quantity_in_stock).toString() : "");
  const [unit, setUnit]           = useState(row.unit || (row.itemType === "recipe" ? "servings" : ""));
  const [threshold, setThreshold] = useState(row.minimum_stock_threshold ?? "");
  const [usageRate, setUsageRate] = useState(row.usage_rate ?? "");
  const [expiry, setExpiry]       = useState(row.expiry_date ?? "");
  const [notes, setNotes]         = useState(row.notes ?? "");
  const [saving, setSaving]       = useState(false);
  const [error, setError]         = useState("");

  async function handleSave() {
    if (!qty || !unit) { setError("Quantity and unit are required."); return; }
    setSaving(true);
    setError("");
    try {
      const result = await upsertInventory(row.inventoryId, {
        item_type:                row.itemType,
        food_item_id:             row.itemType === "food_item" ? row.itemId : null,
        recipe_id:                row.itemType === "recipe" ? row.itemId : null,
        quantity_in_stock:        parseFloat(qty),
        unit,
        expiry_date:              expiry || null,
        usage_rate:               usageRate ? parseFloat(usageRate as string) : null,
        minimum_stock_threshold:  threshold ? parseFloat(threshold as string) : null,
        notes:                    notes || null,
      });
      onSaved(result);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Save failed.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <tr className="bg-emerald-50/40 border-t border-emerald-100">
      <td colSpan={8} className="px-4 py-4">
        <div className="space-y-3">
          <div className="flex items-center gap-2">
            <span className="text-xs font-bold text-zinc-700">
              {row.inventoryId ? "Edit stock" : "Set stock"} for{" "}
              <span className="text-emerald-700">{row.name}</span>
            </span>
            {error && (
              <span className="flex items-center gap-1 text-xs text-red-600">
                <AlertTriangle className="h-3 w-3" /> {error}
              </span>
            )}
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">
                Qty in Stock *
              </label>
              <input
                type="number" min="0" step="0.01"
                value={qty} onChange={(e) => setQty(e.target.value)}
                autoFocus
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">
                Unit *
              </label>
              <input
                type="text"
                value={unit} onChange={(e) => setUnit(e.target.value)}
                placeholder={row.itemType === "recipe" ? "servings" : "kg, pcs, L…"}
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">
                Min Threshold
              </label>
              <input
                type="number" min="0" step="0.01"
                value={threshold} onChange={(e) => setThreshold(e.target.value)}
                placeholder="e.g. 5"
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">
                Usage / day
              </label>
              <input
                type="number" min="0" step="0.01"
                value={usageRate} onChange={(e) => setUsageRate(e.target.value)}
                placeholder="e.g. 2.5"
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">
                Expiry Date
              </label>
              <input
                type="date"
                value={expiry} onChange={(e) => setExpiry(e.target.value)}
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">
                Notes
              </label>
              <input
                type="text"
                value={notes} onChange={(e) => setNotes(e.target.value)}
                placeholder="Optional…"
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
          </div>
          <div className="flex gap-2">
            <Button variant="primary" onClick={handleSave} disabled={saving} className="!py-1.5 !px-4 text-xs">
              {saving ? "Saving…" : "Save"}
            </Button>
            <button onClick={onClose} className="text-xs text-zinc-500 hover:text-zinc-700 flex items-center gap-1">
              <X className="h-3 w-3" /> Cancel
            </button>
          </div>
        </div>
      </td>
    </tr>
  );
}

// ─── Restock Row ──────────────────────────────────────────────────────────────

function RestockRow({
  row,
  onRestocked,
  onClose,
}: {
  row: InventoryRow;
  onRestocked: (record: InventoryRecord) => void;
  onClose: () => void;
}) {
  const [qty, setQty]       = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError]   = useState("");

  async function handleRestock() {
    const amount = parseFloat(qty);
    if (!amount || amount <= 0) { setError("Enter valid qty."); return; }
    if (!row.inventoryId) return;
    setSaving(true);
    setError("");
    try {
      const updated = await restockInventory(row.inventoryId, amount);
      onRestocked(updated);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Restock failed.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <tr className="bg-sky-50/40 border-t border-sky-100">
      <td colSpan={8} className="px-4 py-3">
        <div className="flex items-center gap-3 flex-wrap">
          <span className="text-xs text-zinc-600 font-medium">
            Add stock for <strong>{row.name}</strong>:
          </span>
          <input
            type="number" min="0.01" step="0.01"
            value={qty} onChange={(e) => setQty(e.target.value)}
            placeholder="Qty to add"
            autoFocus
            className="w-28 px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
          <span className="text-xs text-zinc-500">{row.unit}</span>
          {error && <span className="text-xs text-red-600">{error}</span>}
          <Button variant="primary" onClick={handleRestock} disabled={saving} className="!py-1.5 !px-3 text-xs">
            {saving ? "…" : "Confirm"}
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

export default function InventoryPage() {
  const [rows, setRows]         = useState<InventoryRow[]>([]);
  const [records, setRecords]   = useState<InventoryRecord[]>([]);
  const [foodItems, setFoodItems] = useState<FoodItemRef[]>([]);
  const [recipes, setRecipes]   = useState<RecipeRef[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState("");
  const [search, setSearch]     = useState("");
  const [tab, setTab]           = useState<FilterTab>("all");
  const [typeFilter, setTypeFilter] = useState<"all" | "food_item" | "recipe">("all");
  const [editId, setEditId]     = useState<string | null>(null);   // `${itemType}_${itemId}`
  const [restockId, setRestockId] = useState<string | null>(null);
  const [deleteRowKey, setDeleteRowKey] = useState<string | null>(null);
  const [deleting, setDeleting] = useState(false);

  function rowKey(r: InventoryRow) { return `${r.itemType}_${r.itemId}`; }

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [invRes, foodRes, recipeRes] = await Promise.all([
        fetch("/api/fss/inventory").then((r) => r.json()),
        fetch("/api/rnd/food-items").then((r) => r.json()),
        fetch("/api/rnd/recipes").then((r) => r.json()),
      ]);
      const inv: InventoryRecord[] = invRes.data ?? [];
      const fi: FoodItemRef[]      = foodRes.data ?? [];
      const rc: RecipeRef[]        = recipeRes.data ?? [];
      setRecords(inv);
      setFoodItems(fi);
      setRecipes(rc);
      setRows(buildInventoryRows(fi, rc, inv));
    } catch {
      setError("Failed to load inventory.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  function rebuildRows(newRecords: InventoryRecord[]) {
    setRecords(newRecords);
    setRows(buildInventoryRows(foodItems, recipes, newRecords));
  }

  function handleSaved(record: InventoryRecord) {
    const updated = records.some((r) => r.id === record.id)
      ? records.map((r) => (r.id === record.id ? record : r))
      : [...records, record];
    rebuildRows(updated);
    setEditId(null);
  }

  function handleRestocked(record: InventoryRecord) {
    rebuildRows(records.map((r) => (r.id === record.id ? record : r)));
    setRestockId(null);
  }

  async function handleDelete(row: InventoryRow) {
    if (!row.inventoryId) { setDeleteRowKey(null); return; }
    setDeleting(true);
    try {
      await deleteInventory(row.inventoryId);
      rebuildRows(records.filter((r) => r.id !== row.inventoryId));
      setDeleteRowKey(null);
    } catch {
      // keep confirm open
    } finally {
      setDeleting(false);
    }
  }

  // Stats
  const tracked   = rows.filter((r) => r.status !== "untracked");
  const lowCount  = rows.filter((r) => r.status === "low").length;
  const expCount  = rows.filter((r) => r.status === "expiring").length;
  const untCount  = rows.filter((r) => r.status === "untracked").length;

  const filtered = rows.filter((r) => {
    const matchSearch = r.name.toLowerCase().includes(search.toLowerCase()) ||
                        r.category.toLowerCase().includes(search.toLowerCase());
    const matchTab    = tab === "all" || r.status === tab;
    const matchType   = typeFilter === "all" || r.itemType === typeFilter;
    return matchSearch && matchTab && matchType;
  });

  const STATUS_TABS: { key: FilterTab; label: string }[] = [
    { key: "all",       label: "All" },
    { key: "low",       label: `Low Stock${lowCount ? ` (${lowCount})` : ""}` },
    { key: "expiring",  label: `Expiring${expCount ? ` (${expCount})` : ""}` },
    { key: "untracked", label: `Untracked${untCount ? ` (${untCount})` : ""}` },
  ];

  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-400">Food Service</span>
        <span className="text-zinc-300">/</span>
        <span className="font-bold">Inventory Logs</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Salad className="h-5 w-5 text-emerald-600" />
            Kitchen &amp; Food Service Inventory
          </h2>
          <p className="text-xs text-zinc-500 mt-1 select-none">
            Stock tracking for all ingredients and recipes from the food &amp; recipe library.
            Edit any row to set quantities, thresholds, and expiry dates.
          </p>
        </div>
        <button
          onClick={load}
          className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700 transition-colors shrink-0 mt-1"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} />
          Refresh
        </button>
      </div>

      {/* Stat chips */}
      <div className="flex flex-wrap gap-3">
        {[
          {
            label: "Total Items",
            value: rows.length,
            cls: "bg-zinc-50 border-zinc-200 text-zinc-700",
          },
          {
            label: "Tracked",
            value: tracked.length,
            cls: "bg-emerald-50 border-emerald-200 text-emerald-700",
          },
          {
            label: "Low Stock",
            value: lowCount,
            cls: lowCount > 0 ? "bg-red-50 border-red-200 text-red-700" : "bg-zinc-50 border-zinc-200 text-zinc-400",
          },
          {
            label: "Expiring Soon",
            value: expCount,
            cls: expCount > 0 ? "bg-amber-50 border-amber-200 text-amber-700" : "bg-zinc-50 border-zinc-200 text-zinc-400",
          },
          {
            label: "Untracked",
            value: untCount,
            cls: untCount > 0 ? "bg-zinc-100 border-zinc-300 text-zinc-600" : "bg-zinc-50 border-zinc-200 text-zinc-400",
          },
        ].map(({ label, value, cls }) => (
          <div key={label} className={`px-4 py-2.5 rounded-xl border text-xs font-semibold flex items-center gap-2 ${cls}`}>
            <span className="text-lg font-extrabold">{value}</span>
            <span className="opacity-70">{label}</span>
          </div>
        ))}
      </div>

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-3 items-start sm:items-center flex-wrap">
        <div className="relative flex-1 max-w-sm">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
          <input
            type="text" value={search} onChange={(e) => setSearch(e.target.value)}
            placeholder="Search by name or category…"
            className="w-full pl-9 pr-3 py-2 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
          />
        </div>

        {/* Type toggle */}
        <div className="flex gap-1 bg-zinc-100 rounded-lg p-1">
          {(["all", "food_item", "recipe"] as const).map((t) => (
            <button
              key={t}
              onClick={() => setTypeFilter(t)}
              className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors ${
                typeFilter === t ? "bg-white text-zinc-900 shadow-sm" : "text-zinc-500 hover:text-zinc-700"
              }`}
            >
              {t === "all" ? "All Types" : t === "food_item" ? "Ingredients" : "Recipes"}
            </button>
          ))}
        </div>

        {/* Status tabs */}
        <div className="flex gap-1 bg-zinc-100 rounded-lg p-1">
          {STATUS_TABS.map(({ key, label }) => (
            <button
              key={key}
              onClick={() => setTab(key)}
              className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors ${
                tab === key ? "bg-white text-zinc-900 shadow-sm" : "text-zinc-500 hover:text-zinc-700"
              }`}
            >
              {label}
            </button>
          ))}
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-hidden">
        {loading ? (
          <div className="py-16 text-center text-xs text-zinc-400">Loading inventory…</div>
        ) : error ? (
          <div className="py-16 text-center text-xs text-red-500">{error}</div>
        ) : filtered.length === 0 ? (
          <div className="py-16 text-center">
            <Salad className="h-8 w-8 text-zinc-300 mx-auto mb-3" />
            <p className="text-xs text-zinc-400 font-medium">No items match your filter.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs">
              <thead className="bg-zinc-50 border-b border-zinc-100">
                <tr>
                  {["Type", "Name", "Category", "Qty in Stock", "Unit", "Min Threshold", "Expiry", "Status", "Actions"].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {filtered.map((row) => {
                  const key              = rowKey(row);
                  const isEditing        = editId === key;
                  const isRestocking     = restockId === key;
                  const isConfirmDelete  = deleteRowKey === key;
                  const hasRecord        = row.inventoryId !== null;

                  return (
                    <React.Fragment key={key}>
                      <tr className={`hover:bg-zinc-50 transition-colors ${isEditing ? "bg-emerald-50/20" : ""}`}>
                        <td className="px-4 py-3">
                          <TypeBadge type={row.itemType} />
                        </td>
                        <td className="px-4 py-3 font-semibold text-zinc-800 whitespace-nowrap max-w-[180px] truncate">
                          {row.name}
                        </td>
                        <td className="px-4 py-3 text-zinc-500 whitespace-nowrap">{row.category || "—"}</td>
                        <td className="px-4 py-3 font-mono text-zinc-700">
                          {hasRecord ? parseFloat(row.quantity_in_stock).toFixed(2) : "—"}
                        </td>
                        <td className="px-4 py-3 text-zinc-500">{row.unit || "—"}</td>
                        <td className="px-4 py-3 text-zinc-500">
                          {row.minimum_stock_threshold
                            ? parseFloat(row.minimum_stock_threshold).toFixed(2)
                            : "—"}
                        </td>
                        <td className="px-4 py-3 text-zinc-500 whitespace-nowrap">
                          {formatDate(row.expiry_date)}
                        </td>
                        <td className="px-4 py-3">
                          <StatusBadge status={row.status} />
                        </td>
                        <td className="px-4 py-3">
                          {isConfirmDelete ? (
                            <div className="flex items-center gap-2">
                              <span className="text-red-600 text-[10px] font-semibold">Clear stock?</span>
                              <button
                                onClick={() => handleDelete(row)}
                                disabled={deleting}
                                className="text-[10px] font-bold text-red-600 hover:underline disabled:opacity-50"
                              >
                                {deleting ? "…" : "Yes"}
                              </button>
                              <button
                                onClick={() => setDeleteRowKey(null)}
                                className="text-[10px] font-bold text-zinc-500 hover:underline"
                              >
                                No
                              </button>
                            </div>
                          ) : (
                            <div className="flex items-center gap-1">
                              {/* Restock — only if tracked */}
                              {hasRecord && (
                                <button
                                  onClick={() => {
                                    setRestockId(isRestocking ? null : key);
                                    setEditId(null);
                                  }}
                                  title="Restock"
                                  className={`p-1.5 rounded-lg transition-colors ${
                                    isRestocking
                                      ? "bg-sky-100 text-sky-700"
                                      : "hover:bg-zinc-100 text-zinc-500"
                                  }`}
                                >
                                  <ChevronDown className={`h-3.5 w-3.5 transition-transform ${isRestocking ? "rotate-180" : ""}`} />
                                </button>
                              )}
                              {/* Edit / Set stock */}
                              <button
                                onClick={() => {
                                  setEditId(isEditing ? null : key);
                                  setRestockId(null);
                                }}
                                title={hasRecord ? "Edit stock" : "Set stock"}
                                className={`p-1.5 rounded-lg transition-colors ${
                                  isEditing
                                    ? "bg-emerald-100 text-emerald-700"
                                    : "hover:bg-zinc-100 text-zinc-500"
                                }`}
                              >
                                <Pencil className="h-3.5 w-3.5" />
                              </button>
                              {/* Clear stock — only if tracked */}
                              {hasRecord && (
                                <button
                                  onClick={() => { setDeleteRowKey(key); setEditId(null); setRestockId(null); }}
                                  title="Clear stock record"
                                  className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 transition-colors"
                                >
                                  <Trash2 className="h-3.5 w-3.5" />
                                </button>
                              )}
                            </div>
                          )}
                        </td>
                      </tr>

                      {isEditing && (
                        <EditRow
                          row={row}
                          onSaved={handleSaved}
                          onClose={() => setEditId(null)}
                        />
                      )}
                      {isRestocking && hasRecord && (
                        <RestockRow
                          row={row}
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
    </div>
  );
}
