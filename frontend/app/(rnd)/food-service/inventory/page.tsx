"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import {
  Salad, Search, RefreshCw, Pencil, Trash2, X, Check,
  AlertTriangle, Package, Boxes, BookOpen, ChevronLeft, ChevronRight, Sparkles,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  InventoryRow,
  StockStatus,
  RowHighlight,
  PaginationMeta,
  InventoryStats,
  ListInventoryRowsParams,
  listInventoryRows,
  upsertInventory,
  deleteInventory,
  patchFsItemCategory,
  patchRecipeCategory,
} from "@/services/inventoryService";

// ─── Constants ────────────────────────────────────────────────────────────────

type ActiveTab    = "ingredient" | "supply" | "recipe";
type StatusFilter = "all" | StockStatus;

const TAB_META: Record<ActiveTab, { noun: string; nounPlural: string }> = {
  ingredient: { noun: "ingredient", nounPlural: "ingredients" },
  supply:     { noun: "supply",     nounPlural: "supplies" },
  recipe:     { noun: "recipe",     nounPlural: "recipes" },
};

const PER_PAGE = 25;

const UNIT_OPTIONS = ["pc", "pack", "bundle", "serving", "g", "kg", "mL", "L"] as const;

const HIGHLIGHT_ROW: Record<RowHighlight, string> = {
  green: "",
  red:   "bg-red-50",
};

// ─── Small helpers ────────────────────────────────────────────────────────────

function StockDot({ status }: { status: StockStatus }) {
  const isOk = status === "ok";
  return (
    <div className="flex items-center gap-1.5">
      <span className={`h-2 w-2 rounded-full shrink-0 ${isOk ? "bg-emerald-500" : "bg-red-500"}`} />
      <span className={`text-[11px] font-semibold ${isOk ? "text-emerald-700" : "text-red-600"}`}>
        {isOk ? "In stock" : "Out"}
      </span>
    </div>
  );
}

function formatPrice(p: string | null, prefix = "₱") {
  if (!p || parseFloat(p) === 0) return "—";
  return `${prefix}${parseFloat(p).toFixed(2)}`;
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

const DEFAULT_STATS: InventoryStats = { total: 0, in_stock: 0, no_stock: 0 };

type EditValues = { qty: string; unit: string; cost: string; category: string };

export default function InventoryPage() {
  const [rows, setRows]   = useState<InventoryRow[]>([]);
  const [meta, setMeta]   = useState<PaginationMeta | null>(null);
  const [stats, setStats] = useState<InventoryStats>(DEFAULT_STATS);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState("");

  const [activeTab, setActiveTab]       = useState<ActiveTab>("ingredient");
  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
  const [search, setSearch]             = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [page, setPage] = useState(1);

  const [editId, setEditId]             = useState<string | null>(null);
  const [editValues, setEditValues]     = useState<EditValues>({ qty: "", unit: "", cost: "", category: "" });
  const [editSaving, setEditSaving]     = useState(false);
  const [editError, setEditError]       = useState("");
  const [deleteRowKey, setDeleteRowKey] = useState<string | null>(null);
  const [deleting, setDeleting]         = useState(false);

  function rowKey(r: InventoryRow) { return `${r.itemType}_${r.itemId}`; }

  useEffect(() => {
    const t = setTimeout(() => { setDebouncedSearch(search); setPage(1); }, 300);
    return () => clearTimeout(t);
  }, [search]);

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

  function startEdit(row: InventoryRow) {
    setEditId(rowKey(row));
    setEditValues({
      qty:       row.inventoryId ? parseFloat(row.quantity_in_stock).toString() : "",
      unit:      row.unit || (row.itemType === "recipe" ? "serving" : row.base_unit ?? ""),
      cost:      row.unit_price ?? "",
      category:  row.category ?? "",
    });
    setEditError("");
    setDeleteRowKey(null);
  }

  async function handleSave(row: InventoryRow) {
    const qtyNum = parseFloat(editValues.qty);
    if (!editValues.qty || isNaN(qtyNum) || qtyNum < 0) {
      setEditError("Enter a valid qty."); return;
    }
    if (!editValues.unit) { setEditError("Unit is required."); return; }
    const costNum      = editValues.cost      ? parseFloat(editValues.cost)      : null;
    if (costNum !== null && (isNaN(costNum) || costNum < 0)) {
      setEditError("Enter a valid cost."); return;
    }
    setEditSaving(true); setEditError("");
    try {
      const categoryVal = editValues.category.trim() || null;
      await Promise.all([
        upsertInventory(row.inventoryId, {
          item_type:               row.itemType,
          fs_item_id:              row.itemType !== "recipe" ? row.itemId : null,
          recipe_id:               row.itemType === "recipe" ? row.itemId : null,
          quantity_in_stock:       qtyNum,
          unit:                    editValues.unit,
          unit_price:              costNum,
        }),
        row.itemType === "recipe"
          ? patchRecipeCategory(row.itemId, categoryVal)
          : patchFsItemCategory(row.itemId, categoryVal),
      ]);
      setEditId(null);
      await load();
    } catch (err: unknown) {
      setEditError(err instanceof Error ? err.message : "Save failed.");
    } finally {
      setEditSaving(false);
    }
  }

  async function handleDelete(row: InventoryRow) {
    if (!row.inventoryId) { setDeleteRowKey(null); return; }
    setDeleting(true);
    try {
      await deleteInventory(row.inventoryId);
      setDeleteRowKey(null);
      await load();
    } catch { } finally { setDeleting(false); }
  }

  const statusTabs: { key: StatusFilter; label: string }[] = [
    { key: "all",      label: "All" },
    { key: "ok",       label: `In Stock${stats.in_stock ? ` (${stats.in_stock})` : ""}` },
    { key: "no_stock", label: `Out${stats.no_stock ? ` (${stats.no_stock})` : ""}` },
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
            Track stock for ingredients, supplies, and prepared recipes. Recipe costs auto-calculate from catalog prices.
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
          { label: "Total Items", value: stats.total,    cls: "bg-zinc-50 border-zinc-200 text-zinc-700" },
          { label: "In Stock",    value: stats.in_stock, cls: "bg-emerald-50 border-emerald-200 text-emerald-700" },
          { label: "Out",         value: stats.no_stock, cls: stats.no_stock > 0 ? "bg-red-50 border-red-200 text-red-700" : "bg-zinc-50 border-zinc-200 text-zinc-400" },
        ].map(({ label, value, cls }) => (
          <div key={label} className={`px-4 py-2.5 rounded-xl border text-xs font-semibold flex items-center gap-2 ${cls}`}>
            <span className="text-lg font-extrabold">{value}</span>
            <span className="opacity-70">{label}</span>
          </div>
        ))}
      </div>

      {/* Primary tabs */}
      <div className="flex border-b border-zinc-200">
        {([
          { key: "ingredient", label: "Ingredients", Icon: Package,  active: "border-emerald-600 text-emerald-700" },
          { key: "supply",     label: "Supplies",    Icon: Boxes,    active: "border-sky-600 text-sky-700" },
          { key: "recipe",     label: "Recipes",     Icon: BookOpen, active: "border-violet-600 text-violet-700" },
        ] as const).map(({ key, label, Icon, active }) => (
          <button
            key={key}
            onClick={() => setActiveTab(key)}
            className={`flex items-center gap-2 px-5 py-3 text-sm font-semibold border-b-2 transition-colors cursor-pointer ${
              activeTab === key ? active : "border-transparent text-zinc-500 hover:text-zinc-800"
            }`}
          >
            <Icon className="h-4 w-4" />
            {label}
            {key === "recipe" && activeTab === "recipe" && (
              <span className="ml-1 text-[10px] text-violet-500 font-normal flex items-center gap-0.5">
                <Sparkles className="h-2.5 w-2.5" /> cost auto-calculated
              </span>
            )}
          </button>
        ))}
      </div>

      {/* Sub-filters */}
      <div className="flex flex-col sm:flex-row gap-3 items-start sm:items-center flex-wrap">
        <div className="relative flex-1 max-w-sm">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
          <input type="text" value={search} onChange={e => setSearch(e.target.value)}
            placeholder={`Search ${TAB_META[activeTab].nounPlural}…`}
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
            {activeTab === "ingredient"
              ? <Package className="h-8 w-8 text-zinc-300 mx-auto mb-3" />
              : activeTab === "supply"
                ? <Boxes className="h-8 w-8 text-zinc-300 mx-auto mb-3" />
                : <BookOpen className="h-8 w-8 text-zinc-300 mx-auto mb-3" />}
            <p className="text-xs text-zinc-400 font-medium">No {TAB_META[activeTab].nounPlural} match your filter.</p>
          </div>
        ) : (
          <>
            <table className="w-full text-xs">
              <thead className="bg-zinc-50 border-b border-zinc-100">
                <tr>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                    {activeTab === "recipe" ? "Recipe" : activeTab === "supply" ? "Supply" : "Ingredient"}
                  </th>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Qty</th>
                  <th className="hidden sm:table-cell px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Unit</th>
                  <th className="hidden sm:table-cell px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">
                    {activeTab !== "recipe"
                      ? "Cost / Unit"
                      : <span className="flex items-center gap-1"><Sparkles className="h-2.5 w-2.5 text-violet-500" /> Cost / Serving</span>}
                  </th>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Stock</th>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {rows.map((row) => {
                  const key             = rowKey(row);
                  const isEditing       = editId === key;
                  const isConfirmDelete = deleteRowKey === key;
                  const hasRecord       = row.inventoryId !== null;
                  const rowBg           = isEditing ? "bg-emerald-50/30" : HIGHLIGHT_ROW[row.highlight];

                  return (
                    <tr key={key} className={`transition-colors hover:brightness-95 ${rowBg}`}>

                      {/* Name + category */}
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2 min-w-0">
                          <span className="font-semibold text-zinc-800 truncate shrink-0">{row.name}</span>
                          {isEditing ? (
                            <input
                              type="text"
                              value={editValues.category}
                              onChange={e => setEditValues(v => ({ ...v, category: e.target.value }))}
                              placeholder="Category…"
                              className="min-w-0 w-28 px-2 py-0.5 text-[10px] border border-emerald-300 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500 text-zinc-600"
                            />
                          ) : (
                            row.category && (
                              <span className="hidden lg:inline text-[10px] text-zinc-400 shrink-0">{row.category}</span>
                            )
                          )}
                        </div>
                      </td>

                      {/* Qty */}
                      <td className="px-4 py-3 whitespace-nowrap">
                        {isEditing ? (
                          <input
                            type="number" min="0" step="0.01"
                            value={editValues.qty}
                            onChange={e => setEditValues(v => ({ ...v, qty: e.target.value }))}
                            autoFocus
                            className="w-20 px-2 py-1 text-xs border border-emerald-300 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500 font-mono"
                          />
                        ) : (
                          <span className="font-mono text-zinc-700">
                            {hasRecord ? parseFloat(row.quantity_in_stock).toFixed(2) : "—"}
                          </span>
                        )}
                      </td>

                      {/* Unit */}
                      <td className="hidden sm:table-cell px-4 py-3 whitespace-nowrap">
                        {isEditing ? (
                          <select
                            value={editValues.unit}
                            onChange={e => setEditValues(v => ({ ...v, unit: e.target.value }))}
                            className="px-2 py-1 text-xs border border-emerald-300 rounded-md bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                          >
                            <option value="" disabled>Unit…</option>
                            {UNIT_OPTIONS.map(u => <option key={u} value={u}>{u}</option>)}
                          </select>
                        ) : (
                          <span className="text-zinc-500">{row.unit || "—"}</span>
                        )}
                      </td>

                      {/* Cost */}
                      <td className="hidden sm:table-cell px-4 py-3 whitespace-nowrap font-mono">
                        {isEditing && activeTab !== "recipe" ? (
                          <div className="flex items-center gap-1">
                            <span className="text-zinc-400 text-[10px]">₱</span>
                            <input
                              type="number" min="0" step="0.01"
                              value={editValues.cost}
                              onChange={e => setEditValues(v => ({ ...v, cost: e.target.value }))}
                              placeholder="0.00"
                              className="w-20 px-2 py-1 text-xs border border-emerald-300 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            />
                            {editValues.unit && (
                              <span className="text-zinc-400 text-[10px]">/ {editValues.unit}</span>
                            )}
                          </div>
                        ) : activeTab !== "recipe" ? (
                          <span className="text-zinc-600">
                            {formatPrice(row.unit_price)}
                            {row.unit && row.unit_price && parseFloat(row.unit_price) > 0 && (
                              <span className="text-zinc-400">{` / ${row.unit}`}</span>
                            )}
                          </span>
                        ) : row.recipe_cost ? (
                          <span className="text-violet-700 flex items-center gap-1">
                            <Sparkles className="h-3 w-3" />
                            ₱{parseFloat(row.recipe_cost).toFixed(2)}
                          </span>
                        ) : (
                          <span className="text-zinc-400 text-[10px]">no ingredients</span>
                        )}
                      </td>

                      {/* Stock indicator — binary in/out dot */}
                      <td className="px-4 py-3">
                        <StockDot status={row.status} />
                      </td>

                      {/* Actions */}
                      <td className="px-4 py-3">
                        {isEditing ? (
                          <div className="flex items-center gap-2 flex-wrap">
                            <button
                              onClick={() => handleSave(row)}
                              disabled={editSaving}
                              className="flex items-center gap-1 px-2.5 py-1 text-[10px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-md disabled:opacity-50 transition-colors"
                            >
                              <Check className="h-3 w-3" />
                              {editSaving ? "…" : "Save"}
                            </button>
                            <button
                              onClick={() => { setEditId(null); setEditError(""); }}
                              className="p-1 text-zinc-400 hover:text-zinc-600"
                            >
                              <X className="h-3.5 w-3.5" />
                            </button>
                            {editError && (
                              <span className="text-[10px] text-red-600 flex items-center gap-0.5">
                                <AlertTriangle className="h-3 w-3" /> {editError}
                              </span>
                            )}
                          </div>
                        ) : isConfirmDelete ? (
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
                            <button
                              onClick={() => startEdit(row)}
                              title={hasRecord ? "Edit stock" : "Set stock"}
                              className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 transition-colors"
                            >
                              <Pencil className="h-3.5 w-3.5" />
                            </button>
                            <button
                              onClick={() => { setDeleteRowKey(key); setEditId(null); }}
                              title="Delete"
                              className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 transition-colors"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </button>
                          </div>
                        )}
                      </td>
                    </tr>
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
