"use client";

import { useEffect, useRef } from "react";
import { AlertTriangle } from "lucide-react";
import { Button } from "@/components/ui/Button";

export function BackupActionDialog({ open, title, description, confirmLabel, loading, onClose, onConfirm }: { open: boolean; title: string; description: string; confirmLabel: string; loading: boolean; onClose: () => void; onConfirm: () => void }) {
  const cancelRef = useRef<HTMLButtonElement>(null);
  const returnFocusRef = useRef<HTMLElement | null>(null);
  useEffect(() => {
    if (!open) return;
    returnFocusRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    cancelRef.current?.focus();
    return () => returnFocusRef.current?.focus();
  }, [open]);
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onMouseDown={(event) => { if (event.target === event.currentTarget && !loading) onClose(); }}>
      <div role="dialog" aria-modal="true" aria-labelledby="backup-dialog-title" aria-describedby="backup-dialog-description" onKeyDown={(event) => { if (event.key === "Escape" && !loading) onClose(); }} className="w-full max-w-md rounded-2xl border border-warm-200 bg-white p-6 shadow-xl">
        <div className="flex items-start gap-3"><AlertTriangle className="mt-0.5 h-6 w-6 shrink-0 text-brand-orange-600" /><div><h2 id="backup-dialog-title" className="text-lg font-extrabold text-warm-900">{title}</h2><p id="backup-dialog-description" className="mt-2 text-sm leading-relaxed text-warm-600">{description}</p></div></div>
        <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><Button ref={cancelRef} variant="secondary" className="min-h-11" disabled={loading} onClick={onClose}>Cancel</Button><Button variant="danger" className="min-h-11" loading={loading} onClick={onConfirm}>{confirmLabel}</Button></div>
      </div>
    </div>
  );
}
