"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import {
  Salad, Search, RefreshCw, Pencil, Trash2, ChevronDown, X,
  AlertTriangle, Package, BookOpen, ChevronLeft, ChevronRight, Sparkles,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  InventoryRow,
  InventoryRecord,
  StockStatus,
  RowHighlight,
  PaginationMeta,
  InventoryStats,
  ListInventoryRowsParams,
  listInventoryRows,
  upsertInventory,
  deleteInventory,
  restockInventory,
} from "@/services/inventoryService";

// ─── Constants ────────────────────────────────────────────────────────────────

type ActiveTab    = "food_item" | "recipe";
type StatusFilter = "all" | StockStatus;

const PER_PAGE = 25;

const STATUS_META: Record<StockStatus, { label: string; cls: string }> = {
  low:       { label: "Low Stock",     cls: "bg-red-50 text-red-700 border border-red-200" },
  expiring:  { label: "Expiring Soon", cls: "bg-amber-50 text-amber-700 border border-amber-200" },
  ok:        { label: "OK",            cls: "bg-emerald-50 text-emerald-700 border border-emerald-200" },
  untracked: { label: "Untracked",     cls: "bg-zinc-100 text-zinc-500 border border-zinc-200" },
};

const HIGHLIGHT_ROW: Record<RowHighlight, string> = {
  none:   "",
  yellow: "bg-amber-50",
  red:    "bg-red-50",
};

// ─── Small helpers ────────────────────────────────────────────────────────────

function StatusBadge({ status }: { status: StockStatus }) {
  const { label, cls } = STATUS_META[status];
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ${cls}`}>
      {label}
    </span>
  );
}

function formatDate(d: string | null) {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
}

function formatPrice(p: string | null, prefix = "₱") {
  if (!p || parseFloat(p) === 0) return "—";
  return `${prefix}${parseFloat(p).toFixed(2)}`;
}

// ─── Edit row ────────────────────────────────────────────────────────────────

function EditRow({ row, colSpan, onSaved, onClose }: {
  row: InventoryRow;
  colSpan: number;
  onSaved: (r: InventoryRecord) => void;
  onClose: () => void;
}) {
  const [qty, setQty]             = useState(row.inventoryId ? parseFloat(row.quantity_in_stock).toString() : "");
  const [unit, setUnit]           = useState(row.unit || (row.itemType === "recipe" ? "servings" : ""));
  const [unitPrice, setUnitPrice] = useState(row.unit_price ? parseFloat(row.unit_price).toString() : "");
  const [threshold, setThreshold] = useState(row.minimum_stock_threshold ?? "");
  const [usageRate, setUsageRate] = useState(row.usage_rate ?? "");
  const [expiry, setExpiry]       = useState(row.expiry_date ?? "");
  const [notes, setNotes]         = useState(row.notes ?? "");
  const [saving, setSaving]       = useState(false);
  const [error, setError]         = useState("");

  async function handleSave() {
    if (!qty || !unit) { setError("Quantity and unit are required."); return; }
    setSaving(true); setError("");
    try {
      const result = await upsertInventory(row.inventoryId, {
        item_type:               row.itemType,
        food_item_id:            row.itemType === "food_item" ? row.itemId : null,
        recipe_id:               row.itemType === "recipe"    ? row.itemId : null,
        quantity_in_stock:       parseFloat(qty),
        unit,
        expiry_date:             expiry || null,
        usage_rate:              usageRate ? parseFloat(usageRate) : null,
        minimum_stock_threshold: threshold ? parseFloat(threshold) : null,
        unit_price:              unitPrice ? parseFloat(unitPrice) : null,
        notes:                   notes || null,
      });
      onSaved(result);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Save failed.");
    } finally {
      setSaving(false);
    }
  }

  const isRecipe = row.itemType === "recipe";

  return (
    <tr className="bg-emerald-50/40 border-t border-emerald-100">
      <td colSpan={colSpan} className="px-4 py-4">
        <div className="space-y-3">
          <div className="flex items-center gap-2 flex-wrap">
            <span className="text-xs font-bold text-zinc-700">
              {row.inventoryId ? "Edit stock" : "Set stock"} for{" "}
              <span className="text-emerald-700">{row.name}</span>
            </span>
            {isRecipe && row.recipe_cost && (
              <span className="flex items-center gap-1 text-[10px] text-violet-600 bg-violet-50 border border-violet-200 rounded-full px-2 py-0.5">
                <Sparkles className="h-2.5 w-2.5" />
                Auto cost: ₱{parseFloat(row.recipe_cost).toFixed(2)}/serving
              </span>
            )}
            {error && (
              <span className="flex items-center gap-1 text-xs text-red-600">
                <AlertTriangle className="h-3 w-3" /> {error}
              </span>
            )}
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Qty *</label>
              <input type="number" min="0" step="0.01" value={qty} onChange={e => setQty(e.target.value)} autoFocus
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Unit *</label>
              <input type="text" value={unit} onChange={e => setUnit(e.target.value)}
                placeholder={isRecipe ? "servings" : "kg, pcs, L…"}
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            {!isRecipe && (
              <div>
                <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Purchase Price (₱)</label>
                <input type="number" min="0" step="0.01" value={unitPrice} onChange={e => setUnitPrice(e.target.value)}
                  placeholder="e.g. 280.00"
                  className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
              </div>
            )}
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Min Threshold</label>
              <input type="number" min="0" step="0.01" value={threshold} onChange={e => setThreshold(e.target.value)}
                placeholder="e.g. 5"
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Usage / day</label>
              <input type="number" min="0" step="0.01" value={usageRate} onChange={e => setUsageRate(e.target.value)}
                placeholder="e.g. 2.5"
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Expiry Date</label>
              <input type="date" value={expiry} onChange={e => setExpiry(e.target.value)}
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Notes</label>
              <input type="text" value={notes} onChange={e => setNotes(e.target.value)} placeholder="Optional…"
                className="w-full px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
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

// ─── Restock row ──────────────────────────────────────────────────────────────

function RestockRow({ row, colSpan, onRestocked, onClose }: {
  row: InventoryRow;
  colSpan: number;
  onRestocked: (r: InventoryRecord) => void;
  onClose: () => void;
}) {
  const [qty, setQty]       = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError]   = useState("");

  async function handleRestock() {
    const amount = parseFloat(qty);
    if (!amount || amount <= 0) { setError("Enter valid qty."); return; }
    if (!row.inventoryId) return;
    setSaving(true); setError("");
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
      <td colSpan={colSpan} className="px-4 py-3">
        <div className="flex items-center gap-3 flex-wrap">
          <span className="text-xs text-zinc-600 font-medium">Add stock for <strong>{row.name}</strong>:</span>
          <input type="number" min="0.01" step="0.01" value={qty} onChange={e => setQty(e.target.value)}
            placeholder="Qty to add" autoFocus
            className="w-28 px-2.5 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          <span className="text-xs text-zinc-500">{row.unit}</span>
          {error && <span className="text-xs text-red-600">{error}</span>}
          <Button variant="primary" onClick={handleRestock} disabled={saving} className="!py-1.5 !px-3 text-xs">
            {saving ? "…" : "Confirm"}
          </Button>
          <button onClick={onClose} className="text-zinc-400 hover:text-zinc-600"><X className="h-3.5 w-3.5" /></button>
        </div>
      </td>
    </tr>
  );
}

// ─── Pagination ───────────────────────────────────────────────────────────────

function Pagination({ meta, onPageChange }: { meta: PaginationMeta; onPageChange: (p: number) => void }) {
  if (meta.last_page <= 1) return null;
  const pages = Array.from({ length: meta.last_page }, (_, i) => i + 1);
  const visible = pages.filter(p => p === 1 || p === meta.last_page || Math.abs(p - meta.current_page) <= 1);

  return (
    <div className="flex items-center justify-between px-4 py-3 border-t border-zinc-100">
      <span className="text-[10px] text-zinc-400">
        {((meta.current_page - 1) * meta.per_page) + 1}–{Math.min(meta.current_page * meta.per_page, meta.total)} of {meta.total}
      </span>
      <div className="flex items-center gap-1">
        <button onClick={() => onPageChange(meta.current_page - 1)} disabled={meta.current_page === 1}
          className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 disabled:opacity-30 disabled:cursor-not-allowed">
          <ChevronLeft className="h-3.5 w-3.5" />
        </button>
        {visible.reduce<(number | "…")[]>((acc, p, i, arr) => {
          if (i > 0 && p - (arr[i - 1] as number) > 1) acc.push("…");
          acc.push(p);
          return acc;
        }, []).map((p, i) =>
          p === "…" ? (
            <span key={`e${i}`} className="px-1.5 text-xs text-zinc-400">…</span>
          ) : (
            <button key={p} onClick={() => onPageChange(p as number)}
              className={`min-w-[28px] h-7 rounded-lg text-xs font-semibold transition-colors ${
                p === meta.current_page ? "bg-emerald-600 text-white" : "hover:bg-zinc-100 text-zinc-600"
              }`}>
              {p}
            </button>
          )
        )}
        <button onClick={() => onPageChange(meta.current_page + 1)} disabled={meta.current_page === meta.last_page}
          className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 disabled:opacity-30 disabled:cursor-not-allowed">
          <ChevronRight className="h-3.5 w-3.5" />
        </button>
      </div>
    </div>
  );
}

// ─── Main page ────────────────────────────────────────────────────────────────

const DEFAULT_STATS: InventoryStats = { total: 0, tracked: 0, low: 0, expiring: 0, untracked: 0 };

export default function InventoryPage() {
  const [rows, setRows]   = useState<InventoryRow[]>([]);
  const [meta, setMeta]   = useState<PaginationMeta | null>(null);
  const [stats, setStats] = useState<InventoryStats>(DEFAULT_STATS);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState("");

  // Primary tab
  const [activeTab, setActiveTab]     = useState<ActiveTab>("food_item");
  // Status sub-filter
  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
  // Search
  const [search, setSearch]             = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  // Page
  const [page, setPage] = useState(1);

  // Row UI
  const [editId, setEditId]             = useState<string | null>(null);
  const [restockId, setRestockId]       = useState<string | null>(null);
  const [deleteRowKey, setDeleteRowKey] = useState<string | null>(null);
  const [deleting, setDeleting]         = useState(false);

  function rowKey(r: InventoryRow) { return `${r.itemType}_${r.itemId}`; }

  // Debounce search, reset page
  useEffect(() => {
    const t = setTimeout(() => { setDebouncedSearch(search); setPage(1); }, 300);
    return () => clearTimeout(t);
  }, [search]);

  // Reset page on tab/status change
  useEffect(() => { setPage(1); }, [activeTab, statusFilter]);

  const load = useCallback(async () => {
    setLoading(true); setError("");
    try {
      const params: ListInventoryRowsParams = {
        page,
        per_page: PER_PAGE,
        type:     activeTab,
        status:   statusFilter === "all" ? undefined : statusFilter,
        search:   debouncedSearch || undefined,
      };
      const result = await listInventoryRows(params);
      setRows(result.data);
      setMeta(result.meta);
      setStats(result.stats);
    } catch {
      setError("Failed to load inventory.");
    } finally {
      setLoading(false);
    }
  }, [page, activeTab, statusFilter, debouncedSearch]);

  useEffect(() => { load(); }, [load]);

  async function handleSaved(_r: InventoryRecord) { setEditId(null); await load(); }
  async function handleRestocked(_r: InventoryRecord) { setRestockId(null); await load(); }
  async function handleDelete(row: InventoryRow) {
    if (!row.inventoryId) { setDeleteRowKey(null); return; }
    setDeleting(true);
    try {
      await deleteInventory(row.inventoryId);
      setDeleteRowKey(null);
      await load();
    } catch { } finally { setDeleting(false); }
  }

  // Per-tab stats
  const isRecipeTab = activeTab === "recipe";
  const colSpan     = isRecipeTab ? 6 : 6; // same count, different columns

  const statusTabs: { key: StatusFilter; label: string }[] = [
    { key: "all",       label: "All" },
    { key: "low",       label: `Low Stock${stats.low ? ` (${stats.low})` : ""}` },
    { key: "expiring",  label: `Expiring${stats.expiring ? ` (${stats.expiring})` : ""}` },
    { key: "untracked", label: `Untracked${stats.untracked ? ` (${stats.untracked})` : ""}` },
  ];

  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span>/</span>
        <span>Food Service</span>
        <span>/</span>
        <span className="font-bold text-zinc-600">Inventory</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Salad className="h-5 w-5 text-emerald-600" />
            Kitchen &amp; Food Service Inventory
          </h2>
          <p className="text-xs text-zinc-500 mt-1">
            Track stock levels for ingredients and prepared recipes. Recipe costs are auto-calculated from the food library.
          </p>
        </div>
        <button onClick={load}
          className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700 transition-colors shrink-0 mt-1">
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} />
          Refresh
        </button>
      </div>

      {/* Stats */}
      <div className="flex flex-wrap gap-3">
        {[
          { label: "Total Items",   value: stats.total,     cls: "bg-zinc-50 border-zinc-200 text-zinc-700" },
          { label: "Tracked",       value: stats.tracked,   cls: "bg-emerald-50 border-emerald-200 text-emerald-700" },
          { label: "Low Stock",     value: stats.low,       cls: stats.low > 0 ? "bg-red-50 border-red-200 text-red-700" : "bg-zinc-50 border-zinc-200 text-zinc-400" },
          { label: "Expiring Soon", value: stats.expiring,  cls: stats.expiring > 0 ? "bg-amber-50 border-amber-200 text-amber-700" : "bg-zinc-50 border-zinc-200 text-zinc-400" },
          { label: "Untracked",     value: stats.untracked, cls: stats.untracked > 0 ? "bg-zinc-100 border-zinc-300 text-zinc-600" : "bg-zinc-50 border-zinc-200 text-zinc-400" },
        ].map(({ label, value, cls }) => (
          <div key={label} className={`px-4 py-2.5 rounded-xl border text-xs font-semibold flex items-center gap-2 ${cls}`}>
            <span className="text-lg font-extrabold">{value}</span>
            <span className="opacity-70">{label}</span>
          </div>
        ))}
      </div>

      {/* Primary tabs: Ingredients | Recipes */}
      <div className="flex border-b border-zinc-200">
        <button
          onClick={() => setActiveTab("food_item")}
          className={`flex items-center gap-2 px-5 py-3 text-sm font-semibold border-b-2 transition-colors ${
            activeTab === "food_item"
              ? "border-emerald-600 text-emerald-700"
              : "border-transparent text-zinc-500 hover:text-zinc-800"
          }`}
        >
          <Package className="h-4 w-4" />
          Ingredients
        </button>
        <button
          onClick={() => setActiveTab("recipe")}
          className={`flex items-center gap-2 px-5 py-3 text-sm font-semibold border-b-2 transition-colors ${
            activeTab === "recipe"
              ? "border-violet-600 text-violet-700"
              : "border-transparent text-zinc-500 hover:text-zinc-800"
          }`}
        >
          <BookOpen className="h-4 w-4" />
          Recipes
          {activeTab === "recipe" && (
            <span className="ml-1 text-[10px] text-violet-500 font-normal flex items-center gap-0.5">
              <Sparkles className="h-2.5 w-2.5" /> cost auto-calculated
            </span>
          )}
        </button>
      </div>

      {/* Sub-filters */}
      <div className="flex flex-col sm:flex-row gap-3 items-start sm:items-center flex-wrap">
        <div className="relative flex-1 max-w-sm">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
          <input type="text" value={search} onChange={e => setSearch(e.target.value)}
            placeholder={`Search ${activeTab === "food_item" ? "ingredients" : "recipes"}…`}
            className="w-full pl-9 pr-3 py-2 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
        </div>
        <div className="flex gap-1 bg-zinc-100 rounded-lg p-1">
          {statusTabs.map(({ key, label }) => (
            <button key={key} onClick={() => setStatusFilter(key)}
              className={`px-3 py-1.5 text-xs font-semibold rounded-md transition-colors ${
                statusFilter === key ? "bg-white text-zinc-900 shadow-sm" : "text-zinc-500 hover:text-zinc-700"
              }`}>
              {label}
            </button>
          ))}
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
        {loading ? (
          <div className="py-16 text-center text-xs text-zinc-400">Loading…</div>
        ) : error ? (
          <div className="py-16 text-center text-xs text-red-500">{error}</div>
        ) : rows.length === 0 ? (
          <div className="py-16 text-center">
            {activeTab === "food_item"
              ? <Package className="h-8 w-8 text-zinc-300 mx-auto mb-3" />
              : <BookOpen className="h-8 w-8 text-zinc-300 mx-auto mb-3" />}
            <p className="text-xs text-zinc-400 font-medium">No {activeTab === "food_item" ? "ingredients" : "recipes"} match your filter.</p>
          </div>
        ) : (
          <>
            <table className="w-full text-xs">
              <thead className="bg-zinc-50 border-b border-zinc-100">
                <tr>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                    {activeTab === "food_item" ? "Ingredient" : "Recipe"}
                  </th>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Qty</th>
                  <th className="hidden sm:table-cell px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Unit</th>
                  {activeTab === "food_item" ? (
                    <th className="hidden sm:table-cell px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Purchase Price</th>
                  ) : (
                    <th className="hidden sm:table-cell px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">
                      <span className="flex items-center gap-1"><Sparkles className="h-2.5 w-2.5 text-violet-500" /> Cost / Serving</span>
                    </th>
                  )}
                  <th className="hidden md:table-cell px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Expiry</th>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {rows.map((row) => {
                  const key             = rowKey(row);
                  const isEditing       = editId === key;
                  const isRestocking    = restockId === key;
                  const isConfirmDelete = deleteRowKey === key;
                  const hasRecord       = row.inventoryId !== null;
                  const rowBg           = isEditing ? "bg-emerald-50/20" : HIGHLIGHT_ROW[row.highlight];

                  return (
                    <React.Fragment key={key}>
                      <tr className={`transition-colors hover:brightness-95 ${rowBg}`}>
                        {/* Name */}
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-2 min-w-0">
                            <span className="font-semibold text-zinc-800 truncate">{row.name}</span>
                            {row.category && (
                              <span className="hidden lg:inline text-[10px] text-zinc-400 shrink-0">{row.category}</span>
                            )}
                          </div>
                        </td>

                        {/* Qty */}
                        <td className="px-4 py-3 font-mono text-zinc-700 whitespace-nowrap">
                          {hasRecord ? parseFloat(row.quantity_in_stock).toFixed(2) : "—"}
                        </td>

                        {/* Unit */}
                        <td className="hidden sm:table-cell px-4 py-3 text-zinc-500 whitespace-nowrap">
                          {row.unit || "—"}
                        </td>

                        {/* Price / Cost per serving */}
                        <td className="hidden sm:table-cell px-4 py-3 whitespace-nowrap font-mono">
                          {activeTab === "food_item" ? (
                            <span className="text-zinc-600">{formatPrice(row.unit_price)}</span>
                          ) : row.recipe_cost ? (
                            <span className="text-violet-700 flex items-center gap-1">
                              <Sparkles className="h-3 w-3" />
                              ₱{parseFloat(row.recipe_cost).toFixed(2)}
                            </span>
                          ) : (
                            <span className="text-zinc-400 text-[10px]">no ingredients</span>
                          )}
                        </td>

                        {/* Expiry */}
                        <td className="hidden md:table-cell px-4 py-3 text-zinc-500 whitespace-nowrap">
                          {formatDate(row.expiry_date)}
                        </td>

                        {/* Status */}
                        <td className="px-4 py-3"><StatusBadge status={row.status} /></td>

                        {/* Actions */}
                        <td className="px-4 py-3">
                          {isConfirmDelete ? (
                            <div className="flex items-center gap-2">
                              <span className="text-red-600 text-[10px] font-semibold">Clear stock?</span>
                              <button onClick={() => handleDelete(row)} disabled={deleting}
                                className="text-[10px] font-bold text-red-600 hover:underline disabled:opacity-50">
                                {deleting ? "…" : "Yes"}
                              </button>
                              <button onClick={() => setDeleteRowKey(null)}
                                className="text-[10px] font-bold text-zinc-500 hover:underline">No</button>
                            </div>
                          ) : (
                            <div className="flex items-center gap-1">
                              {hasRecord && (
                                <button onClick={() => { setRestockId(isRestocking ? null : key); setEditId(null); }}
                                  title="Restock"
                                  className={`p-1.5 rounded-lg transition-colors ${isRestocking ? "bg-sky-100 text-sky-700" : "hover:bg-zinc-100 text-zinc-500"}`}>
                                  <ChevronDown className={`h-3.5 w-3.5 transition-transform ${isRestocking ? "rotate-180" : ""}`} />
                                </button>
                              )}
                              <button onClick={() => { setEditId(isEditing ? null : key); setRestockId(null); }}
                                title={hasRecord ? "Edit stock" : "Set stock"}
                                className={`p-1.5 rounded-lg transition-colors ${isEditing ? "bg-emerald-100 text-emerald-700" : "hover:bg-zinc-100 text-zinc-500"}`}>
                                <Pencil className="h-3.5 w-3.5" />
                              </button>
                              {hasRecord && (
                                <button onClick={() => { setDeleteRowKey(key); setEditId(null); setRestockId(null); }}
                                  title="Clear stock record"
                                  className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 transition-colors">
                                  <Trash2 className="h-3.5 w-3.5" />
                                </button>
                              )}
                            </div>
                          )}
                        </td>
                      </tr>

                      {isEditing && (
                        <EditRow row={row} colSpan={7} onSaved={handleSaved} onClose={() => setEditId(null)} />
                      )}
                      {isRestocking && hasRecord && (
                        <RestockRow row={row} colSpan={7} onRestocked={handleRestocked} onClose={() => setRestockId(null)} />
                      )}
                    </React.Fragment>
                  );
                })}
              </tbody>
            </table>
            {meta && <Pagination meta={meta} onPageChange={setPage} />}
          </>
        )}
      </div>
    </div>
  );
}
