"use client";

import { useEffect, useRef, useState, type ReactNode } from 'react';
import { X } from 'lucide-react';
import SearchInput from '@/components/ui/SearchInput';
import { Pagination, type PaginationMeta } from '@/components/ui/Pagination';

export function MenuItemPicker({ title = 'Add Menu Item', itemLabel = 'Menu Item', query, onQueryChange, loading, error, items, onSelect, onClose, busyId, meta, page = 1, onPageChange, filters }: {
  title?: string;
  itemLabel?: 'Menu Item' | 'Ingredient' | 'Supply';
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
  filters?: ReactNode;
}) {
  const pluralLabel = itemLabel === 'Ingredient' ? 'Ingredients' : itemLabel === 'Supply' ? 'Supplies' : 'Menu Items';
  const panel = useRef<HTMLDivElement>(null);
  const requestLoadingSeen = useRef(false);
  const [previousItems, setPreviousItems] = useState(items);
  const [requestPending, setRequestPending] = useState(false);
  const isLoading = loading || requestPending;
  useEffect(() => {
    if (loading) requestLoadingSeen.current = true;
    else if (requestLoadingSeen.current) {
      requestLoadingSeen.current = false;
      setRequestPending(false);
    }
  }, [loading]);
  useEffect(() => { if (!isLoading && !error) setPreviousItems(items); }, [items, isLoading, error]);
  useEffect(() => {
    const trigger = document.activeElement;
    panel.current?.querySelector<HTMLInputElement>('input')?.focus();
    return () => { if (trigger instanceof HTMLElement && trigger.isConnected) trigger.focus(); };
  }, []);
  return <div role="dialog" aria-modal="true" aria-label={`Add ${itemLabel}`} className="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm [@media(max-height:420px)]:p-2" onMouseDown={(event) => { if (event.target === event.currentTarget && !busyId) onClose(); }} onKeyDown={(event) => {
    if (event.key === 'Escape' && !busyId) onClose();
    if (event.key === 'Tab') {
      const controls = panel.current?.querySelectorAll<HTMLElement>('button:not(:disabled), input, select');
      if (!controls?.length) return;
      const first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  }}>
    <div ref={panel} className="flex max-h-[calc(100dvh-2rem)] min-h-0 w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl [@media(max-height:420px)]:h-[calc(100dvh-1rem)] [@media(max-height:420px)]:max-h-[calc(100dvh-1rem)]">
      <div className="flex items-center justify-between gap-3 border-b border-warm-100 p-4 [@media(max-height:420px)]:p-3"><h3 className="font-bold text-warm-900">{title}</h3><button type="button" disabled={Boolean(busyId)} aria-label={`Close ${itemLabel} picker`} onClick={onClose} className="rounded-lg p-2 text-warm-500 hover:bg-warm-50 focus-visible:ring-2 focus-visible:ring-emerald-500"><X className="h-4 w-4" /></button></div>
      <div className="shrink-0 space-y-3 p-4 [@media(max-height:420px)]:space-y-2 [@media(max-height:420px)]:p-3">
        {filters && <div data-picker-filters onClickCapture={(event) => {
          const button = event.target instanceof Element ? event.target.closest('button[aria-pressed]') : null;
          if (button && button.getAttribute('aria-pressed') !== 'true') setRequestPending(true);
        }} className="max-w-full [@media(max-height:420px)]:overflow-x-auto [@media(max-height:420px)]:[&_.flex.flex-wrap]:flex-nowrap [@media(max-height:420px)]:[&_button]:whitespace-nowrap">{filters}</div>}
        <SearchInput label={`Search ${pluralLabel}`} value={query} onChange={(value) => { setRequestPending(true); onQueryChange(value); }} loading={isLoading} />
      </div>
      <div data-picker-results aria-busy={isLoading} className="min-h-0 flex-1 space-y-2 overflow-y-auto px-4 pb-3">
        {!isLoading && error && <p role="alert" className="text-sm text-red-700">{error}</p>}
        {isLoading && <p role="status" className="text-sm text-warm-500">Searching…</p>}
        {!isLoading && !error && items.length === 0 && <p className="text-sm text-warm-500">No {pluralLabel} found.</p>}
        {(isLoading ? previousItems : items).map((item) => <button key={item.id} type="button" aria-label={`Add ${item.name}`} disabled={Boolean(busyId) || isLoading || Boolean(error)} onClick={() => onSelect(item.id)} className="block min-h-11 w-full rounded-lg border border-warm-200 px-3 py-2 text-left text-sm hover:bg-emerald-50 focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:opacity-50"><span className="block break-words font-semibold">{item.name}</span>{item.detail && <span className="text-xs text-warm-500">{item.detail}</span>}</button>)}
      </div>
      <div data-picker-pagination className="shrink-0 [@media(max-height:420px)]:[&>div]:px-3 [@media(max-height:420px)]:[&>div]:py-2">
        {onPageChange && <Pagination meta={meta ?? null} page={page} onPageChange={(nextPage) => { setRequestPending(true); onPageChange(nextPage); }} />}
      </div>
    </div>
  </div>;
}
