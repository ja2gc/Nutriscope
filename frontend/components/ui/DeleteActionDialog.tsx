"use client";

import { useEffect, useId, useRef } from "react";
import { AlertTriangle, Trash2, X } from "lucide-react";
import { Button } from "./Button";

export function DeleteActionDialog({
  title,
  message,
  related,
  nextStep,
  blocked = false,
  busy = false,
  error,
  confirmLabel = "Delete",
  onClose,
  onConfirm,
}: {
  title: string;
  message: string;
  related?: string | null;
  nextStep?: string | null;
  blocked?: boolean;
  busy?: boolean;
  error?: string | null;
  confirmLabel?: string;
  onClose: () => void;
  onConfirm?: () => void;
}) {
  const titleId = useId();
  const panel = useRef<HTMLElement>(null);

  useEffect(() => {
    const trigger = document.activeElement;
    panel.current?.querySelector<HTMLButtonElement>("button")?.focus();
    return () => { if (trigger instanceof HTMLElement && trigger.isConnected) trigger.focus(); };
  }, []);

  return (
    <div role="dialog" aria-modal="true" aria-labelledby={titleId} onKeyDown={(event) => {
      if (event.key === "Escape" && !busy) onClose();
      if (event.key === "Tab") {
        const controls = panel.current?.querySelectorAll<HTMLButtonElement>("button:not(:disabled)");
        if (!controls?.length) return;
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      }
    }} className="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4">
      <section ref={panel} className="w-full max-w-md space-y-4 rounded-2xl bg-white p-5 shadow-xl">
        <div className="flex items-start justify-between gap-3">
          <h3 id={titleId} className="text-lg font-bold text-warm-900">{title}</h3>
          <button type="button" aria-label="Close dialog" disabled={busy} onClick={onClose} className="rounded-lg p-2 text-warm-500 hover:bg-warm-50">
            <X className="h-4 w-4" />
          </button>
        </div>
        <div className={`rounded-xl border p-4 ${blocked ? "border-amber-300 bg-amber-50 text-amber-950" : "border-warm-200 bg-warm-50 text-warm-800"}`}>
          <p className="flex items-start gap-2 text-sm font-semibold">
            {blocked && <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />}
            {message}
          </p>
          {related && <p className="mt-2 text-sm">{related}</p>}
          {nextStep && <p className="mt-2 text-sm">{nextStep}</p>}
        </div>
        {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" disabled={busy} onClick={onClose}>{blocked ? "Close" : "Cancel"}</Button>
          {!blocked && onConfirm && <Button type="button" variant="danger" loading={busy} onClick={onConfirm}>
            <Trash2 className="mr-1.5 h-4 w-4" />{confirmLabel}
          </Button>}
        </div>
      </section>
    </div>
  );
}
