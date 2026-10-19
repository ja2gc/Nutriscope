"use client";

import { useCallback, useEffect, useRef, useState } from 'react';
import { UnsavedChangesDialog } from '@/components/ui/UnsavedChangesDialog';
export type EditorLeaveHandle = { requestLeave: (next: () => void) => void };

export function useEditorChanges(snapshot: string, ready: boolean, onSave: () => Promise<boolean>) {
  const [baseline, setBaseline] = useState<string | null>(ready ? snapshot : null);
  useEffect(() => {
    if (!ready && baseline !== null) setBaseline(null);
    else if (ready && baseline === null) setBaseline(snapshot);
  }, [ready, baseline, snapshot]);
  const dirty = ready && baseline !== null && snapshot !== baseline;
  const guard = useUnsavedChanges({ dirty, onSave });
  return { ...guard, dirty, markSaved: (next = snapshot) => setBaseline(next) };
}

export function useUnsavedChanges({ dirty, onSave, title }: { dirty: boolean; onSave: () => Promise<boolean>; title?: string }) {
  const [pending, setPending] = useState<(() => void) | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const allowed = useRef(false);
  const allowNavigation = useCallback(() => { allowed.current = true; }, []);
  const requestLeave = useCallback((next: () => void) => {
    if (dirty && !allowed.current) setPending(() => next);
    else next();
  }, [dirty]);
  useEffect(() => {
    if (!dirty) { allowed.current = false; return; }
    const unload = (event: BeforeUnloadEvent) => {
      if (!allowed.current) { event.preventDefault(); event.returnValue = ''; }
    };
    const click = (event: MouseEvent) => {
      if (allowed.current || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      const anchor = event.target instanceof Element ? event.target.closest<HTMLAnchorElement>('a[href]') : null;
      if (!anchor || (anchor.target && anchor.target !== '_self') || anchor.hasAttribute('download')) return;
      const destination = new URL(anchor.href, window.location.href);
      if (!['http:', 'https:'].includes(destination.protocol) || destination.href === window.location.href || (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash)) return;
      event.preventDefault(); event.stopPropagation();
      requestLeave(() => { if (anchor.isConnected) anchor.click(); else window.location.assign(destination.href); });
    };
    window.addEventListener('beforeunload', unload);
    window.addEventListener('click', click, true);
    return () => { window.removeEventListener('beforeunload', unload); window.removeEventListener('click', click, true); };
  }, [dirty, requestLeave]);
  const leave = () => { allowed.current = true; setPending(null); pending?.(); };
  return { requestLeave, allowNavigation, dialog: pending ? <UnsavedChangesDialog title={title} busy={busy} error={error} onStay={() => { setPending(null); setError(null); }} onDiscard={leave} onSave={async () => {
    setBusy(true); setError(null);
    try {
      if (await onSave()) leave();
      else setError('Could not save. Close this dialog to review the fields and try again.');
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not save. Try again.'); }
    finally { setBusy(false); }
  }} /> : null };
}
