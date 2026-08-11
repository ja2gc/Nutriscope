"use client";

import { useCallback, useEffect, useState } from "react";
import { ArrowLeft, Camera, FileImage, RefreshCw, ShoppingBag } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import {
  deleteAttachment,
  getPurchaseOrder,
  listPurchaseOrders,
  type POAttachment,
  type POVendorGroup,
  type PurchaseOrder,
  updateVendorGroup,
  uploadVendorGroupAttachments,
} from "@/services/procurementService";

const money = (value: string | null | undefined) =>
  new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" }).format(Number(value ?? 0));

function AttachmentList({ attachments, locked, onDelete }: {
  attachments: POAttachment[];
  locked: boolean;
  onDelete: (id: string) => Promise<void>;
}) {
  if (!attachments.length) return <p className="text-xs text-warm-400">No images uploaded.</p>;
  return (
    <div className="grid grid-cols-2 gap-2">
      {attachments.map((attachment) => (
        <div key={attachment.id} className="rounded-xl border border-warm-200 bg-white p-2">
          <a href={attachment.url} target="_blank" rel="noreferrer" className="flex items-center gap-2 text-sm font-bold text-emerald-700">
            <FileImage className="h-4 w-4 shrink-0" />
            <span className="truncate">{attachment.caption || attachment.type}</span>
          </a>
          {!locked && (
            <button type="button" onClick={() => void onDelete(attachment.id)} className="mt-2 text-xs font-bold text-red-600">
              Remove
            </button>
          )}
        </div>
      ))}
    </div>
  );
}

function VendorCard({ group, purchaseOrderId, locked, onChanged }: {
  group: POVendorGroup;
  purchaseOrderId: string;
  locked: boolean;
  onChanged: (purchaseOrder: PurchaseOrder) => void;
}) {
  const [orNumber, setOrNumber] = useState(group.or_number ?? "");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  async function saveOrNumber() {
    setBusy(true); setError("");
    try { onChanged(await updateVendorGroup(group.id, { or_number: orNumber.trim() || null })); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Could not save OR number."); }
    finally { setBusy(false); }
  }

  async function upload(type: "receipt" | "proof", files: FileList | null) {
    if (!files?.length) return;
    setBusy(true); setError("");
    try {
      await uploadVendorGroupAttachments(group.id, Array.from(files), type);
      onChanged(await getPurchaseOrder(purchaseOrderId));
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "Image upload failed.");
    } finally { setBusy(false); }
  }

  async function remove(id: string) {
    if (!window.confirm("Remove this image?")) return;
    setBusy(true); setError("");
    try { await deleteAttachment(id); onChanged(await getPurchaseOrder(purchaseOrderId)); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Could not remove image."); setBusy(false); }
  }

  const attachments = group.attachments ?? [];
  return (
    <section className="space-y-4 rounded-2xl border border-warm-200 bg-warm-50 p-4">
      <div className="flex items-start justify-between gap-3">
        <div><h3 className="font-extrabold text-warm-900">{group.supplier?.name ?? "Unassigned vendor"}</h3><p className="text-xs font-semibold text-warm-500">{group.status}</p></div>
        <strong className="text-sm text-emerald-700">{money(group.total_amount)}</strong>
      </div>
      <div>
        <label className="mb-1 block text-xs font-bold text-warm-600" htmlFor={`or-${group.id}`}>OR number</label>
        <div className="flex gap-2">
          <input id={`or-${group.id}`} value={orNumber} onChange={(event) => setOrNumber(event.target.value)} disabled={locked || busy}
            className="min-w-0 flex-1 rounded-xl border border-warm-200 bg-white px-3 py-2 text-base" placeholder="Enter official receipt number" />
          <Button size="sm" onClick={saveOrNumber} disabled={locked} loading={busy}>Save</Button>
        </div>
      </div>
      {!locked && (
        <div className="grid grid-cols-2 gap-2">
          {(["receipt", "proof"] as const).map((type) => (
            <label key={type} className="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-bold capitalize text-emerald-800">
              <Camera className="h-4 w-4" /> {type}
              <input className="sr-only" type="file" accept="image/*" capture="environment" multiple disabled={busy} onChange={(event) => void upload(type, event.target.files)} />
            </label>
          ))}
        </div>
      )}
      <AttachmentList attachments={attachments} locked={locked} onDelete={remove} />
      {error && <p role="alert" className="text-sm font-semibold text-red-700">{error}</p>}
    </section>
  );
}

export function FssPurchaseOrders() {
  const [orders, setOrders] = useState<PurchaseOrder[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<PurchaseOrder | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setLoading(true); setError("");
    try { const result = await listPurchaseOrders(page); setOrders(result.data); setMeta(result.meta); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Could not load purchase orders."); }
    finally { setLoading(false); }
  }, [page]);
  useEffect(() => { void load(); }, [load]);

  async function open(order: PurchaseOrder) {
    setLoading(true); setError("");
    try { setSelected(await getPurchaseOrder(order.id)); }
    catch (reason) { setError(reason instanceof Error ? reason.message : "Could not load purchase order."); }
    finally { setLoading(false); }
  }

  if (selected) {
    const locked = selected.lifecycle_status !== "open_execution";
    return (
      <div className="space-y-4">
        <button onClick={() => setSelected(null)} className="flex min-h-11 items-center gap-2 text-sm font-bold text-emerald-800"><ArrowLeft className="h-4 w-4" /> Purchase orders</button>
        <div className="rounded-2xl border border-warm-200 bg-white p-4 shadow-sm">
          <div className="flex items-start justify-between gap-3"><div><h1 className="text-xl font-extrabold text-warm-900">{selected.po_number}</h1><p className="text-sm text-warm-500">{selected.lifecycle_status.replaceAll("_", " ")}</p></div><strong className="text-emerald-700">{money(selected.total_amount)}</strong></div>
          {locked && <p className="mt-3 rounded-xl bg-warm-100 p-3 text-sm font-semibold text-warm-600">Completed orders are locked.</p>}
        </div>
        {(selected.vendor_groups ?? []).map((group) => <VendorCard key={group.id} group={group} purchaseOrderId={selected.id} locked={locked} onChanged={setSelected} />)}
        {!selected.vendor_groups?.length && <p className="rounded-2xl border border-warm-200 bg-white p-5 text-sm text-warm-500">No vendor groups yet.</p>}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3"><div><h1 className="text-xl font-extrabold text-warm-900">Purchase</h1><p className="text-sm text-warm-500">Record OR numbers and receipt images.</p></div><button onClick={() => void load()} aria-label="Refresh purchase orders" className="min-h-11 min-w-11 rounded-xl border border-warm-200 bg-white p-3 text-warm-600"><RefreshCw className="h-4 w-4" /></button></div>
      {error && <p role="alert" className="rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-700">{error}</p>}
      {loading ? <p className="py-10 text-center text-sm text-warm-400">Loading purchase orders…</p> : orders.length ? (
        <div className="overflow-hidden rounded-2xl border border-warm-200 bg-white shadow-sm">
          {orders.map((order) => <button key={order.id} onClick={() => void open(order)} className="flex min-h-16 w-full items-center gap-3 border-b border-warm-100 px-4 py-3 text-left last:border-0"><span className="rounded-xl bg-emerald-50 p-2 text-emerald-700"><ShoppingBag className="h-5 w-5" /></span><span className="min-w-0 flex-1"><strong className="block truncate text-sm text-warm-900">{order.po_number}</strong><span className="text-xs capitalize text-warm-500">{order.lifecycle_status.replaceAll("_", " ")}</span></span><strong className="text-sm text-emerald-700">{money(order.total_amount)}</strong></button>)}
          <Pagination meta={meta} page={page} onPageChange={setPage} />
        </div>
      ) : <p className="rounded-2xl border border-warm-200 bg-white p-8 text-center text-sm text-warm-500">No purchase orders assigned yet.</p>}
    </div>
  );
}
