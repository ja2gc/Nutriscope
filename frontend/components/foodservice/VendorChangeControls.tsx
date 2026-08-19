"use client";

import { useState } from "react";
import { Button } from "@/components/ui/Button";
import type { POVendorGroup, PurchaseOrder } from "@/services/procurementService";
import { updateVendorGroup } from "@/services/procurementService";

type VendorOption = { id: string | number; name: string };

export function VendorChangeControls({
  group,
  vendors,
  itemId,
  disabled = false,
  onChanged,
}: {
  group: POVendorGroup;
  vendors: VendorOption[];
  itemId?: number;
  disabled?: boolean;
  onChanged: (purchaseOrder: PurchaseOrder) => void;
}) {
  const [open, setOpen] = useState(false);
  const [vendorId, setVendorId] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const label = itemId === undefined ? "Change vendor for all" : "Change vendor";
  const choices = vendors.filter((vendor) => String(vendor.id) !== String(group.supplier?.id));

  async function confirmChange() {
    if (!vendorId) return;
    const vendor = choices.find((choice) => String(choice.id) === vendorId);
    if (!vendor || !window.confirm(`${label} to ${vendor.name}?`)) return;
    setBusy(true);
    setError("");
    try {
      onChanged(await updateVendorGroup(group.id, {
        supplier_id: vendorId,
        ...(itemId === undefined ? {} : { item_id: itemId }),
      }));
      setOpen(false);
      setVendorId("");
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "Could not change vendor.");
    } finally {
      setBusy(false);
    }
  }

  if (!open) {
    return (
      <div>
        <Button
          type="button"
          size="sm"
          variant="ghost"
          onClick={() => setOpen(true)}
          disabled={disabled || !group.can_change_vendor}
          title={group.vendor_change_blocker ?? undefined}
        >
          {label}
        </Button>
        {!group.can_change_vendor && group.vendor_change_blocker && itemId === undefined && (
          <p className="mt-1 text-xs text-warm-500">{group.vendor_change_blocker}</p>
        )}
      </div>
    );
  }

  return (
    <div className="min-w-52 space-y-2">
      <label className="block text-xs font-bold text-warm-600">
        Replacement vendor
        <select
          value={vendorId}
          onChange={(event) => setVendorId(event.target.value)}
          disabled={busy}
          className="mt-1 w-full rounded-lg border border-warm-200 bg-white px-2 py-2 text-sm"
        >
          <option value="">Select vendor</option>
          {choices.map((vendor) => <option key={String(vendor.id)} value={String(vendor.id)}>{vendor.name}</option>)}
        </select>
      </label>
      <div className="flex gap-2">
        <Button type="button" size="sm" onClick={() => void confirmChange()} disabled={!vendorId} loading={busy}>Confirm</Button>
        <Button type="button" size="sm" variant="ghost" onClick={() => { setOpen(false); setError(""); }} disabled={busy}>Cancel</Button>
      </div>
      {error && <p role="alert" className="text-xs font-semibold text-red-700">{error}</p>}
    </div>
  );
}
