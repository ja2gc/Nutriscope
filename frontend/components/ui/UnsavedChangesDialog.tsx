"use client";

import { useEffect, useId, useRef } from 'react';
import { X } from 'lucide-react';
import { Button } from './Button';

export function UnsavedChangesDialog({ title = 'Unsaved changes', busy = false, error, onStay, onDiscard, onSave }: {
  title?: string; busy?: boolean; error?: string | null; onStay: () => void; onDiscard: () => void; onSave: () => void;
}) {
  const titleId = useId(), panel = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const trigger = document.activeElement;
    panel.current?.querySelector<HTMLButtonElement>('button')?.focus();
    return () => { if (trigger instanceof HTMLElement && trigger.isConnected) trigger.focus(); };
  }, []);
  return <div role="dialog" aria-modal="true" aria-label={title} aria-labelledby={titleId} onClick={(event) => event.stopPropagation()} className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onKeyDown={(event) => {
    if (event.key === 'Escape' && !busy) { event.stopPropagation(); onStay(); }
    if (event.key === 'Tab') {
      const controls = panel.current?.querySelectorAll<HTMLButtonElement>('button:not(:disabled)');
      if (!controls?.length) return;
      const first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  }}>
    <div ref={panel} className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
      <div className="flex items-center justify-between gap-3"><h3 id={titleId} className="text-lg font-bold text-warm-900">{title}</h3><button type="button" aria-label="Stay on this page" disabled={busy} onClick={onStay} className="rounded-lg p-2 text-warm-500 hover:bg-warm-50 focus-visible:ring-2 focus-visible:ring-emerald-500"><X className="h-4 w-4" /></button></div>
      <p className="my-4 text-sm text-warm-600">Save your changes before leaving?</p>
      {error && <p role="alert" className="mb-4 text-sm text-red-700">{error}</p>}
      <div className="flex justify-end gap-2"><Button type="button" variant="secondary" disabled={busy} onClick={onDiscard}>Discard</Button><Button type="button" loading={busy} onClick={onSave}>Save</Button></div>
    </div>
  </div>;
}
