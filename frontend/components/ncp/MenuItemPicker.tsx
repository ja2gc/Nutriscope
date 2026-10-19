"use client";

import { useEffect, useRef } from 'react';
import { X } from 'lucide-react';
import SearchInput from '@/components/ui/SearchInput';
import { Pagination, type PaginationMeta } from '@/components/ui/Pagination';

export function MenuItemPicker({ title = 'Add Menu Item', itemLabel = 'Menu Item', query, onQueryChange, loading, error, items, onSelect, onClose, busyId, meta, page = 1, onPageChange }: {
  title?: string;
  itemLabel?: 'Menu Item' | 'Ingredient';
  query: string;
  onQueryChange: (query: string) => void;
  loading: boolean;
  error?: string | null;
  items: { id: string; name: string; detail?: string }[];
  onSelect: (id: string) => void;
  onClose: () => void;
  busyId?: string | null;
  meta?: PaginationMeta | null;
  page?: number;
  onPageChange?: (page: number) => void;
}) {
  const panel = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const trigger = document.activeElement;
    panel.current?.querySelector<HTMLInputElement>('input')?.focus();
    return () => { if (trigger instanceof HTMLElement && trigger.isConnected) trigger.focus(); };
  }, []);
  return <div role="dialog" aria-modal="true" aria-label={`Add ${itemLabel}`} className="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm" onMouseDown={(event) => { if (event.target === event.currentTarget && !busyId) onClose(); }} onKeyDown={(event) => {
    if (event.key === 'Escape' && !busyId) onClose();
    if (event.key === 'Tab') {
      const controls = panel.current?.querySelectorAll<HTMLElement>('button:not(:disabled), input, select');
      if (!controls?.length) return;
      const first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  }}>
    <div ref={panel} className="flex max-h-[80vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
      <div className="flex items-center justify-between gap-3 border-b border-warm-100 p-4"><h3 className="font-bold text-warm-900">{title}</h3><button type="button" disabled={Boolean(busyId)} aria-label={`Close ${itemLabel} picker`} onClick={onClose} className="rounded-lg p-2 text-warm-500 hover:bg-warm-50 focus-visible:ring-2 focus-visible:ring-emerald-500"><X className="h-4 w-4" /></button></div>
      <div className="space-y-3 overflow-y-auto p-4">
        <SearchInput label={`Search ${itemLabel}s`} value={query} onChange={onQueryChange} loading={loading} />
        {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
        {loading && <p role="status" className="text-sm text-warm-500">Searching…</p>}
        {!loading && !error && items.length === 0 && <p className="text-sm text-warm-500">No {itemLabel}s found.</p>}
        {!loading && items.map((item) => <button key={item.id} type="button" aria-label={`Add ${item.name}`} disabled={Boolean(busyId)} onClick={() => onSelect(item.id)} className="block min-h-11 w-full rounded-lg border border-warm-200 px-3 py-2 text-left text-sm hover:bg-emerald-50 focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:opacity-50"><span className="block break-words font-semibold">{item.name}</span>{item.detail && <span className="text-xs text-warm-500">{item.detail}</span>}</button>)}
        {onPageChange && <Pagination meta={meta ?? null} page={page} onPageChange={onPageChange} />}
      </div>
    </div>
  </div>;
}
