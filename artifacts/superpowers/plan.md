# Food Service — Inventory Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build fully functional Inventory management page for the Food Service module (list, add, edit, restock, delete stock entries with color-coded status).

**Architecture:** Backend already complete (InventoryController, model, resource, requests, routes). This plan opens FSS routes to RND role, creates Next.js proxy routes, a service layer, and replaces the inventory page scaffold with a working UI. No backend controller/model changes needed.

**Tech Stack:** Laravel (route middleware tweak only), Next.js 16 App Router proxy routes, React 19, TypeScript, Tailwind CSS v4, Lucide React.

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `backend/routes/api.php` | Modify | Add `RND` to FSS middleware group so web users can hit `/fss/inventory` |
| `frontend/app/api/fss/inventory/route.ts` | Create | Proxy GET (list) + POST (create) → `LARAVEL_API/fss/inventory` |
| `frontend/app/api/fss/inventory/[id]/route.ts` | Create | Proxy GET (show) + PATCH (update) + DELETE → `LARAVEL_API/fss/inventory/{id}` |
| `frontend/app/api/fss/inventory/[id]/restock/route.ts` | Create | Proxy POST → `LARAVEL_API/fss/inventory/{id}/restock` |
| `frontend/services/inventoryService.ts` | Create | TypeScript types, fetch functions, stock-status helper |
| `frontend/app/(rnd)/food-service/inventory/page.tsx` | Modify | Full inventory page: table, modals, restock, delete |

---

## Task 1: Open FSS Routes to RND Role

**Files:**
- Modify: `backend/routes/api.php` line 125

- [ ] **Step 1: Change role middleware**

In `backend/routes/api.php`, change:
```php
Route::middleware(['auth:sanctum', 'role:FSS'])->prefix('fss')->group(function () {
```
to:
```php
Route::middleware(['auth:sanctum', 'role:FSS,RND'])->prefix('fss')->group(function () {
```

- [ ] **Step 2: Verify tests still pass**

```bash
cd backend && php artisan test --filter=InventoryControllerTest
```

If no InventoryControllerTest exists, run:
```bash
cd backend && php artisan test
```
Expected: All existing tests pass (172 passing, 1 pre-existing failure in OcrExtractionServiceTest).

- [ ] **Step 3: Commit**

```bash
git add backend/routes/api.php
git commit -m "feat(fss): open FSS routes to RND role for web access"
```

---

## Task 2: Next.js Proxy — List + Create

**Files:**
- Create: `frontend/app/api/fss/inventory/route.ts`

- [ ] **Step 1: Create proxy route**

```typescript
// frontend/app/api/fss/inventory/route.ts
import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function GET(req: NextRequest) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { searchParams } = new URL(req.url);
  const targetUrl = new URL(`${LARAVEL_API}/fss/inventory`);
  searchParams.forEach((value, key) => targetUrl.searchParams.append(key, value));

  const laravelRes = await fetch(targetUrl.toString(), {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });

  if (!laravelRes.ok) {
    const data = await laravelRes.json().catch(() => ({}));
    return NextResponse.json(
      { message: data.message ?? "Failed to fetch inventory." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(await laravelRes.json(), { status: 200 });
}

export async function POST(req: NextRequest) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const body = await req.json();
  const laravelRes = await fetch(`${LARAVEL_API}/fss/inventory`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(body),
  });

  const data = await laravelRes.json();
  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Failed to create inventory entry.", errors: data.errors },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 201 });
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/api/fss/inventory/route.ts
git commit -m "feat(fss): add Next.js proxy for inventory list + create"
```

---

## Task 3: Next.js Proxy — Show + Update + Delete

**Files:**
- Create: `frontend/app/api/fss/inventory/[id]/route.ts`

- [ ] **Step 1: Create proxy route**

```typescript
// frontend/app/api/fss/inventory/[id]/route.ts
import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

async function getToken() {
  const cookieStore = await cookies();
  return cookieStore.get("nutriscope_token")?.value;
}

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const token = await getToken();
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const laravelRes = await fetch(`${LARAVEL_API}/fss/inventory/${id}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });

  if (!laravelRes.ok) {
    const data = await laravelRes.json().catch(() => ({}));
    return NextResponse.json({ message: data.message ?? "Not found." }, { status: laravelRes.status });
  }

  return NextResponse.json(await laravelRes.json(), { status: 200 });
}

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const token = await getToken();
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const body = await req.json();
  const laravelRes = await fetch(`${LARAVEL_API}/fss/inventory/${id}`, {
    method: "PATCH",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(body),
  });

  const data = await laravelRes.json();
  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Failed to update.", errors: data.errors },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 200 });
}

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const token = await getToken();
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const laravelRes = await fetch(`${LARAVEL_API}/fss/inventory/${id}`, {
    method: "DELETE",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });

  if (laravelRes.status === 204) {
    return new NextResponse(null, { status: 204 });
  }

  const data = await laravelRes.json().catch(() => ({}));
  return NextResponse.json({ message: data.message ?? "Failed to delete." }, { status: laravelRes.status });
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/api/fss/inventory/[id]/route.ts
git commit -m "feat(fss): add Next.js proxy for inventory show + update + delete"
```

---

## Task 4: Next.js Proxy — Restock

**Files:**
- Create: `frontend/app/api/fss/inventory/[id]/restock/route.ts`

- [ ] **Step 1: Create proxy route**

```typescript
// frontend/app/api/fss/inventory/[id]/restock/route.ts
import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function POST(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const body = await req.json();
  const laravelRes = await fetch(`${LARAVEL_API}/fss/inventory/${id}/restock`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(body),
  });

  const data = await laravelRes.json();
  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Restock failed.", errors: data.errors },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 200 });
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/api/fss/inventory/[id]/restock/route.ts
git commit -m "feat(fss): add Next.js proxy for inventory restock"
```

---

## Task 5: Service Layer

**Files:**
- Create: `frontend/services/inventoryService.ts`

- [ ] **Step 1: Create service**

```typescript
// frontend/services/inventoryService.ts

export interface FoodItemRef {
  id: number;
  name: string;
}

export interface InventoryItem {
  id: number;
  food_item_id: number;
  food_item: FoodItemRef;
  quantity_in_stock: string; // decimal string from Laravel
  unit: string;
  expiry_date: string | null; // "YYYY-MM-DD"
  usage_rate: string | null;
  minimum_stock_threshold: string | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface CreateInventoryPayload {
  food_item_id: number;
  quantity_in_stock: number;
  unit: string;
  expiry_date?: string | null;
  usage_rate?: number | null;
  minimum_stock_threshold?: number | null;
  notes?: string | null;
}

export interface UpdateInventoryPayload {
  quantity_in_stock?: number;
  unit?: string;
  expiry_date?: string | null;
  usage_rate?: number | null;
  minimum_stock_threshold?: number | null;
  notes?: string | null;
}

export type StockStatus = "low" | "expiring" | "ok";

export function getStockStatus(item: InventoryItem): StockStatus {
  const qty = parseFloat(item.quantity_in_stock);
  const threshold = item.minimum_stock_threshold
    ? parseFloat(item.minimum_stock_threshold)
    : null;

  if (threshold !== null && qty < threshold) return "low";

  if (item.expiry_date) {
    const expiry = new Date(item.expiry_date);
    const now = new Date();
    const diffDays = (expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);
    if (diffDays <= 7) return "expiring";
  }

  return "ok";
}

export async function listInventory(): Promise<InventoryItem[]> {
  const res = await fetch("/api/fss/inventory");
  if (!res.ok) throw new Error("Failed to fetch inventory.");
  const json = await res.json();
  return json.data;
}

export async function createInventory(
  payload: CreateInventoryPayload
): Promise<InventoryItem> {
  const res = await fetch("/api/fss/inventory", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const json = await res.json();
  if (!res.ok) throw new Error(json.message ?? "Failed to create.");
  return json.data;
}

export async function updateInventory(
  id: number,
  payload: UpdateInventoryPayload
): Promise<InventoryItem> {
  const res = await fetch(`/api/fss/inventory/${id}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const json = await res.json();
  if (!res.ok) throw new Error(json.message ?? "Failed to update.");
  return json.data;
}

export async function deleteInventory(id: number): Promise<void> {
  const res = await fetch(`/api/fss/inventory/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) {
    const json = await res.json().catch(() => ({}));
    throw new Error(json.message ?? "Failed to delete.");
  }
}

export async function restockInventory(
  id: number,
  quantity: number
): Promise<InventoryItem> {
  const res = await fetch(`/api/fss/inventory/${id}/restock`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ quantity }),
  });
  const json = await res.json();
  if (!res.ok) throw new Error(json.message ?? "Restock failed.");
  return json.data;
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/services/inventoryService.ts
git commit -m "feat(fss): add inventory service layer with types and stock-status helper"
```

---

## Task 6: Inventory Page UI

**Files:**
- Modify: `frontend/app/(rnd)/food-service/inventory/page.tsx`

**Dependency note:** Food item select uses existing `/api/rnd/food-items` GET. The InventoryResource returns `food_item.id + food_item.name`. The `inventory` table has a unique constraint on `food_item_id` — one entry per food item.

**UI structure:**
- Breadcrumb → Header row (title + "Add Stock Entry" button)
- Stat chips: Total Items / Low Stock count / Expiring Soon count
- Search input (filter by food item name, client-side) + Status tabs (All / Low Stock / Expiring)
- Table: Food Item | Qty | Unit | Min Threshold | Expiry Date | Status | Actions
- Status badge colors: low=red, expiring=amber, ok=emerald
- Row actions: Restock (inline expand) | Edit (modal) | Delete (inline confirm)
- Add/Edit modal fields: food item select (disabled on edit) | qty | unit | min threshold | usage rate | expiry | notes

- [ ] **Step 1: Replace inventory page**

```tsx
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
  low: { label: "Low Stock", cls: "bg-red-50 text-red-700 border border-red-200" },
  expiring: { label: "Expiring Soon", cls: "bg-amber-50 text-amber-700 border border-amber-200" },
  ok: { label: "OK", cls: "bg-emerald-50 text-emerald-700 border border-emerald-200" },
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
  const [qty, setQty] = useState(item ? parseFloat(item.quantity_in_stock).toString() : "");
  const [unit, setUnit] = useState(item?.unit ?? "");
  const [threshold, setThreshold] = useState(item?.minimum_stock_threshold ?? "");
  const [usageRate, setUsageRate] = useState(item?.usage_rate ?? "");
  const [expiry, setExpiry] = useState(item?.expiry_date ?? "");
  const [notes, setNotes] = useState(item?.notes ?? "");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  // Auto-fill unit from selected food item
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
          food_item_id: foodItemId as number,
          quantity_in_stock: parseFloat(qty),
          unit,
          expiry_date: expiry || null,
          usage_rate: usageRate ? parseFloat(usageRate as string) : null,
          minimum_stock_threshold: threshold ? parseFloat(threshold as string) : null,
          notes: notes || null,
        };
        result = await createInventory(payload);
      } else {
        const payload: UpdateInventoryPayload = {
          quantity_in_stock: parseFloat(qty),
          unit,
          expiry_date: expiry || null,
          usage_rate: usageRate ? parseFloat(usageRate as string) : null,
          minimum_stock_threshold: threshold ? parseFloat(threshold as string) : null,
          notes: notes || null,
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

          {/* Food Item */}
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

          {/* Qty + Unit row */}
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

          {/* Threshold + Usage Rate row */}
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

          {/* Expiry Date */}
          <div>
            <label className="block text-xs font-semibold text-zinc-700 mb-1.5">Expiry Date</label>
            <input
              type="date"
              value={expiry}
              onChange={(e) => setExpiry(e.target.value)}
              className="w-full px-3 py-2 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
            />
          </div>

          {/* Notes */}
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
  const [qty, setQty] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

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
        <div className="flex items-center gap-3">
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
  const [items, setItems] = useState<InventoryItem[]>([]);
  const [foodItems, setFoodItems] = useState<FoodItemOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [tab, setTab] = useState<FilterTab>("all");
  const [modal, setModal] = useState<null | { mode: "add" } | { mode: "edit"; item: InventoryItem }>(null);
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

  // Derived stats
  const lowCount = items.filter((i) => getStockStatus(i) === "low").length;
  const expiringCount = items.filter((i) => getStockStatus(i) === "expiring").length;

  const filtered = items.filter((item) => {
    const matchSearch = item.food_item.name.toLowerCase().includes(search.toLowerCase());
    const matchTab = tab === "all" || getStockStatus(item) === tab;
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
      // keep modal open on error
    } finally {
      setDeleting(false);
    }
  }

  const TABS: { key: FilterTab; label: string }[] = [
    { key: "all", label: "All" },
    { key: "low", label: `Low Stock${lowCount ? ` (${lowCount})` : ""}` },
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
        <span className="text-zinc-650 font-bold">Inventory Logs</span>
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
          { label: "Total Items", value: items.length, cls: "bg-zinc-50 border-zinc-200 text-zinc-700" },
          { label: "Low Stock", value: lowCount, cls: lowCount > 0 ? "bg-red-50 border-red-200 text-red-700" : "bg-zinc-50 border-zinc-200 text-zinc-400" },
          { label: "Expiring Soon", value: expiringCount, cls: expiringCount > 0 ? "bg-amber-50 border-amber-200 text-amber-700" : "bg-zinc-50 border-zinc-200 text-zinc-400" },
        ].map(({ label, value, cls }) => (
          <div key={label} className={`px-4 py-2.5 rounded-xl border text-xs font-semibold flex items-center gap-2 ${cls}`}>
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
          <table className="w-full text-xs">
            <thead className="bg-zinc-50 border-b border-zinc-100">
              <tr>
                {["Food Item", "Qty in Stock", "Unit", "Min Threshold", "Expiry Date", "Status", "Actions"].map(
                  (h) => (
                    <th
                      key={h}
                      className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider"
                    >
                      {h}
                    </th>
                  )
                )}
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100">
              {filtered.map((item) => {
                const status = getStockStatus(item);
                const isRestocking = restockId === item.id;
                const isDeleting = deleteId === item.id;

                return (
                  <React.Fragment key={item.id}>
                    <tr className={`hover:bg-zinc-50 transition-colors ${isRestocking ? "bg-emerald-50/40" : ""}`}>
                      <td className="px-4 py-3 font-semibold text-zinc-800">{item.food_item.name}</td>
                      <td className="px-4 py-3 font-mono text-zinc-700">
                        {parseFloat(item.quantity_in_stock).toFixed(2)}
                      </td>
                      <td className="px-4 py-3 text-zinc-500">{item.unit}</td>
                      <td className="px-4 py-3 text-zinc-500">
                        {item.minimum_stock_threshold
                          ? parseFloat(item.minimum_stock_threshold).toFixed(2)
                          : "—"}
                      </td>
                      <td className="px-4 py-3 text-zinc-500">{formatDate(item.expiry_date)}</td>
                      <td className="px-4 py-3">
                        <StatusBadge status={status} />
                      </td>
                      <td className="px-4 py-3">
                        {isDeleting ? (
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
                              onClick={() =>
                                setRestockId(isRestocking ? null : item.id)
                              }
                              title="Restock"
                              className={`p-1.5 rounded-lg transition-colors ${
                                isRestocking
                                  ? "bg-emerald-100 text-emerald-700"
                                  : "hover:bg-zinc-100 text-zinc-500"
                              }`}
                            >
                              <ChevronDown className={`h-3.5 w-3.5 transition-transform ${isRestocking ? "rotate-180" : ""}`} />
                            </button>
                            <button
                              onClick={() => setModal({ mode: "edit", item })}
                              title="Edit"
                              className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 transition-colors"
                            >
                              <Pencil className="h-3.5 w-3.5" />
                            </button>
                            <button
                              onClick={() => setDeleteId(item.id)}
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
```

- [ ] **Step 2: TypeScript check**

```bash
cd frontend && npx tsc --noEmit
```
Expected: 0 errors.

- [ ] **Step 3: Commit**

```bash
git add frontend/app/\(rnd\)/food-service/inventory/page.tsx
git commit -m "feat(fss): implement inventory management page"
```

---

## Self-Review

**Spec coverage:**
- ✓ Color-coded status (red/yellow/green) → StatusBadge
- ✓ View stock — table with all fields
- ✓ Update stock — Edit modal
- ✓ Restock (quick qty add) — RestockRow inline expand
- ✓ Delete — inline confirm
- ✓ Add new entry — modal with food item select
- ✓ Search + filter tabs
- ✓ Backend RND access — Task 1
- ✓ All proxy routes — Tasks 2/3/4
- ✓ Service layer with types — Task 5

**Placeholder scan:** None found. All code blocks complete.

**Type consistency:**
- `InventoryItem` used across service + page — consistent
- `getStockStatus` returns `StockStatus` union — consistent with `FilterTab` usage
- `CreateInventoryPayload` / `UpdateInventoryPayload` match backend request rules
- `food_item_id` unique constraint → select disabled on edit ✓
