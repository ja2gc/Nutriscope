"use client";

import React, { useState, useEffect, useCallback, useRef } from "react";
import Link from "next/link";
import {
  Salad, Search, RefreshCw, Pencil, Trash2, X, Check,
  AlertTriangle, Package, Boxes,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Pagination } from "@/components/ui/Pagination";
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
  patchFsItem,
} from "@/services/inventoryService";

// ─── Constants ────────────────────────────────────────────────────────────────

type ActiveTab    = "ingredient" | "supply";
type StatusFilter = "all" | StockStatus;

const TAB_META: Record<ActiveTab, { noun: string; nounPlural: string }> = {
  ingredient: { noun: "ingredient", nounPlural: "ingredients" },
  supply:     { noun: "supply",     nounPlural: "supplies" },
};

const PER_PAGE = 15;

const UNIT_OPTIONS = ["pc", "pack", "bundle", "serving", "g", "kg", "mL", "L", "tray", "jar"] as const;

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

const inputCls = "w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all";

function Label({ children }: { children: React.ReactNode }) {
  return (
    <label className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">
      {children}
    </label>
  );
}

// ─── Edit Item Modal ──────────────────────────────────────────────────────────

interface EditModalProps {
  row: InventoryRow | null;
  onClose: () => void;
  onSaved: () => void;
}

function EditItemModal({ row, onClose, onSaved }: EditModalProps) {
  const [category,          setCategory]          = useState("");
  const [purchasePrice,     setPurchasePrice]     = useState("");
  const [purchaseUnit,      setPurchaseUnit]      = useState("");
  const [unitsPerPurchase,  setUnitsPerPurchase]  = useState("");
  const [baseUnit,          setBaseUnit]          = useState("");
  const [qty,               setQty]               = useState("");
  const [stockUnit,         setStockUnit]         = useState("");
  const [saving,            setSaving]            = useState(false);
  const [error,             setError]             = useState("");

  useEffect(() => {
    if (!row) return;
    setCategory(row.category ?? "");
    setPurchasePrice(row.unit_price ?? "");
    setPurchaseUnit(row.purchase_unit ?? "");
    setUnitsPerPurchase(row.units_per_purchase != null ? String(row.units_per_purchase) : "");
    setBaseUnit(row.base_unit ?? "");
    setQty(row.inventoryId != null ? parseFloat(row.quantity_in_stock).toString() : "");
    setStockUnit(row.unit || row.base_unit || "");
    setError("");
  }, [row]);

  // Live derived ₱/base-unit
  const derivedUnitCost = (() => {
    const pp  = parseFloat(purchasePrice);
    const upp = parseFloat(unitsPerPurchase);
    if (!isNaN(pp) && !isNaN(upp) && upp > 0) return pp / upp;
    return null;
  })();

  async function handleSave() {
    if (!row) return;
    const qtyNum = parseFloat(qty);
    if (qty !== "" && (isNaN(qtyNum) || qtyNum < 0)) { setError("Enter a valid quantity."); return; }
    if (!stockUnit) { setError("Stock unit is required."); return; }

    setSaving(true); setError("");
    try {
      // Catalog fields (fs_item)
      await patchFsItem(row.itemId, {
        category:           category.trim() || null,
        purchase_price:     purchasePrice     !== "" ? parseFloat(purchasePrice)    : undefined,
        purchase_unit:      purchaseUnit.trim()  || undefined,
        base_unit:          baseUnit.trim()      || undefined,
        units_per_purchase: unitsPerPurchase !== "" ? parseFloat(unitsPerPurchase) : undefined,
      });

      // Stock record
      if (qty !== "") {
        await upsertInventory(row.inventoryId, {
          item_type:         row.itemType,
          fs_item_id:        row.itemId,
          recipe_id:         null,
          quantity_in_stock: qtyNum,
          unit:              stockUnit,
          unit_price:        purchasePrice !== "" ? parseFloat(purchasePrice) : null,
        });
      }

      onSaved();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Save failed.");
    } finally {
      setSaving(false);
    }
  }

  if (!row) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div className="w-full max-w-lg bg-white rounded-2xl shadow-xl border border-zinc-200 overflow-hidden">
        {/* Header */}
        <div className="px-6 py-5 border-b border-zinc-100 flex items-start justify-between gap-4">
          <div>
            <p className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider mb-0.5 capitalize">
              {row.itemType}
            </p>
            <h3 className="text-base font-extrabold text-zinc-900">{row.name}</h3>
          </div>
          <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-400 shrink-0 transition-colors">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">
          {/* Catalog section */}
          <div>
            <h4 className="text-[10px] font-extrabold text-zinc-700 uppercase tracking-wider mb-3">
              Catalog
            </h4>
            <div className="grid grid-cols-2 gap-3">
              <div className="col-span-2">
                <Label>Category</Label>
                <input
                  type="text" value={category}
                  onChange={e => setCategory(e.target.value)}
                  placeholder="e.g. protein, carbs…"
                  className={inputCls}
                />
              </div>
              <div>
                <Label>Purchase price (₱)</Label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-sm">₱</span>
                  <input
                    type="number" min="0" step="0.01" value={purchasePrice}
                    onChange={e => setPurchasePrice(e.target.value)}
                    placeholder="0.00"
                    className={`${inputCls} pl-6`}
                  />
                </div>
              </div>
              <div>
                <Label>Purchase unit</Label>
                <select value={purchaseUnit} onChange={e => setPurchaseUnit(e.target.value)} className={`${inputCls} bg-white`}>
                  <option value="">Select…</option>
                  {UNIT_OPTIONS.map(u => <option key={u} value={u}>{u}</option>)}
                </select>
              </div>
              <div>
                <Label>Units per purchase</Label>
                <input
                  type="number" min="0" step="0.001" value={unitsPerPurchase}
                  onChange={e => setUnitsPerPurchase(e.target.value)}
                  placeholder="e.g. 1000"
                  className={inputCls}
                />
              </div>
              <div>
                <Label>Base unit</Label>
                <select value={baseUnit} onChange={e => setBaseUnit(e.target.value)} className={`${inputCls} bg-white`}>
                  <option value="">Select…</option>
                  {UNIT_OPTIONS.map(u => <option key={u} value={u}>{u}</option>)}
                </select>
              </div>
            </div>

            {/* Live unit cost readout */}
            {derivedUnitCost !== null && baseUnit && (
              <div className="mt-3 px-3 py-2 bg-emerald-50 border border-emerald-100 rounded-xl">
                <span className="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider">
                  Derived cost:
                </span>{" "}
                <span className="text-sm font-extrabold text-emerald-800">
                  ₱{derivedUnitCost.toFixed(4)} / {baseUnit}
                </span>
                <span className="text-[10px] text-emerald-600 ml-2">
                  (₱{parseFloat(purchasePrice || "0").toFixed(2)} ÷ {unitsPerPurchase} {baseUnit} per {purchaseUnit})
                </span>
              </div>
            )}
          </div>

          {/* Stock section */}
          <div>
            <h4 className="text-[10px] font-extrabold text-zinc-700 uppercase tracking-wider mb-3">
              Stock
            </h4>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label>Quantity in stock</Label>
                <input
                  type="number" min="0" step="0.01" value={qty}
                  onChange={e => setQty(e.target.value)}
                  placeholder="0"
                  className={inputCls}
                />
              </div>
              <div>
                <Label>Stock unit</Label>
                <select value={stockUnit} onChange={e => setStockUnit(e.target.value)} className={`${inputCls} bg-white`}>
                  <option value="">Select…</option>
                  {UNIT_OPTIONS.map(u => <option key={u} value={u}>{u}</option>)}
                </select>
              </div>
            </div>
          </div>

          {/* Error */}
          {error && (
            <div className="flex items-center gap-2 text-xs text-red-700 bg-red-50 border border-red-100 rounded-xl px-3 py-2.5">
              <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
              {error}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-zinc-100 flex gap-3 justify-end">
          <Button variant="secondary" onClick={onClose} className="!py-2 !px-5 text-xs">
            Cancel
          </Button>
          <Button variant="primary" onClick={handleSave} loading={saving} className="!py-2 !px-5 text-xs">
            {saving ? "Saving…" : "Save Changes"}
          </Button>
        </div>
      </div>
    </div>
  );
}

// ─── Main page ────────────────────────────────────────────────────────────────

const DEFAULT_STATS: InventoryStats = { total: 0, in_stock: 0, no_stock: 0 };

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

  const [editRow,      setEditRow]      = useState<InventoryRow | null>(null);
  const [deleteRowKey, setDeleteRowKey] = useState<string | null>(null);
  const [deleting,     setDeleting]     = useState(false);

  const debRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  function rowKey(r: InventoryRow) { return `${r.itemType}_${r.itemId}`; }

  useEffect(() => {
    if (debRef.current) clearTimeout(debRef.current);
    debRef.current = setTimeout(() => { setDebouncedSearch(search); setPage(1); }, 300);
    return () => { if (debRef.current) clearTimeout(debRef.current); };
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
            Track stock for ingredients and supplies. Costs auto-calculate from catalog prices.
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
              : <Boxes className="h-8 w-8 text-zinc-300 mx-auto mb-3" />}
            <p className="text-xs text-zinc-400 font-medium">No {TAB_META[activeTab].nounPlural} match your filter.</p>
          </div>
        ) : (
          <>
            <table className="w-full text-xs">
              <thead className="bg-zinc-50 border-b border-zinc-100">
                <tr>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                    {activeTab === "supply" ? "Supply" : "Ingredient"}
                  </th>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Category</th>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Qty</th>
                  <th className="hidden sm:table-cell px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">₱ / base</th>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Stock</th>
                  <th className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {rows.map((row) => {
                  const key             = rowKey(row);
                  const isConfirmDelete = deleteRowKey === key;
                  const hasRecord       = row.inventoryId !== null;
                  const rowBg           = HIGHLIGHT_ROW[row.highlight];

                  return (
                    <tr key={key} className={`transition-colors hover:brightness-95 ${rowBg}`}>
                      {/* Name */}
                      <td className="px-4 py-3 font-semibold text-zinc-800 truncate max-w-[160px]">{row.name}</td>

                      {/* Category */}
                      <td className="px-4 py-3 text-zinc-500">{row.category || "—"}</td>

                      {/* Qty */}
                      <td className="px-4 py-3 whitespace-nowrap font-mono text-zinc-700">
                        {hasRecord ? `${parseFloat(row.quantity_in_stock).toFixed(2)} ${row.unit}` : "—"}
                      </td>

                      {/* Unit cost */}
                      <td className="hidden sm:table-cell px-4 py-3 whitespace-nowrap font-mono text-zinc-600">
                        {row.unit_cost && parseFloat(row.unit_cost) > 0
                          ? `₱${parseFloat(row.unit_cost).toFixed(4)} / ${row.base_unit ?? ""}`
                          : formatPrice(row.unit_price)}
                      </td>

                      {/* Stock indicator */}
                      <td className="px-4 py-3">
                        <StockDot status={row.status} />
                      </td>

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
                            <button
                              onClick={() => { setEditRow(row); setDeleteRowKey(null); }}
                              title={hasRecord ? "Edit item" : "Set item"}
                              className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 transition-colors"
                            >
                              <Pencil className="h-3.5 w-3.5" />
                            </button>
                            <button
                              onClick={() => { setDeleteRowKey(key); }}
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
            {meta && <Pagination meta={meta} page={page} onPageChange={setPage} />}
          </>
        )}
      </div>

      {/* Edit modal */}
      {editRow && (
        <EditItemModal
          row={editRow}
          onClose={() => setEditRow(null)}
          onSaved={() => { setEditRow(null); load(); }}
        />
      )}
    </div>
  );
}
