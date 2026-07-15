"use client";

import { useEffect, useId, useRef, useState } from "react";
import { Search, X } from "lucide-react";
import { listAuditActors, type AuditActorOption } from "@/services/auditActorService";

const inputClass = "h-11 w-full rounded-lg border border-warm-200 bg-white pl-10 pr-10 text-base text-warm-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30";

export function AuditActorFilter({
  value,
  onChange,
}: {
  value?: string;
  onChange: (value: string | undefined) => void;
}) {
  const listId = useId();
  const root = useRef<HTMLDivElement>(null);
  const [query, setQuery] = useState("");
  const [selected, setSelected] = useState<AuditActorOption | null>(null);
  const [options, setOptions] = useState<AuditActorOption[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);

  useEffect(() => {
    if (!value) {
      setSelected(null);
      setQuery("");
      return;
    }
    const controller = new AbortController();
    void listAuditActors({ selected_id: value, per_page: 1 }, controller.signal)
      .then((result) => {
        const actor = result.data[0] ?? null;
        setSelected(actor);
        setQuery(actor?.name ?? "Selected actor");
      })
      .catch(() => undefined);
    return () => controller.abort();
  }, [value]);

  useEffect(() => {
    function close(event: PointerEvent) {
      if (!root.current?.contains(event.target as Node)) setOpen(false);
    }
    document.addEventListener("pointerdown", close);
    return () => document.removeEventListener("pointerdown", close);
  }, []);

  useEffect(() => {
    if (!open) return;
    const controller = new AbortController();
    const timer = window.setTimeout(() => {
      setLoading(true);
      setError(false);
      void listAuditActors({ search: query.trim() || undefined, page: 1, per_page: 20 }, controller.signal)
        .then((result) => {
          setOptions(result.data);
          setPage(result.meta.current_page);
          setLastPage(result.meta.last_page);
        })
        .catch(() => {
          if (!controller.signal.aborted) setError(true);
        })
        .finally(() => {
          if (!controller.signal.aborted) setLoading(false);
        });
    }, 200);

    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [open, query, reloadKey]);

  async function loadMore() {
    if (loading || page >= lastPage) return;
    setLoading(true);
    setError(false);
    try {
      const result = await listAuditActors({ search: query.trim() || undefined, page: page + 1, per_page: 20 });
      setOptions((current) => [...current, ...result.data.filter((actor) => !current.some((item) => item.id === actor.id))]);
      setPage(result.meta.current_page);
      setLastPage(result.meta.last_page);
    } catch {
      setError(true);
    } finally {
      setLoading(false);
    }
  }

  function choose(actor: AuditActorOption) {
    setSelected(actor);
    setQuery(actor.name);
    setOpen(false);
    onChange(actor.id);
  }

  function clear() {
    setSelected(null);
    setQuery("");
    setOpen(false);
    onChange(undefined);
  }

  return (
    <div ref={root} className="relative min-w-0">
      <label htmlFor={`${listId}-input`} className="mb-1 block text-xs font-bold uppercase tracking-wider text-warm-500">
        Actor
      </label>
      <div className="relative">
        <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-warm-400" />
        <input
          id={`${listId}-input`}
          role="combobox"
          aria-autocomplete="list"
          aria-controls={listId}
          aria-expanded={open}
          aria-label="Search actors by name"
          className={inputClass}
          placeholder="All actors"
          value={query}
          onFocus={() => setOpen(true)}
          onChange={(event) => {
            setQuery(event.target.value);
            setOpen(true);
            if (selected) {
              setSelected(null);
              onChange(undefined);
            }
          }}
          onKeyDown={(event) => {
            if (event.key === "Escape") setOpen(false);
            if (event.key === "Enter" && open && options[0]) {
              event.preventDefault();
              choose(options[0]);
            }
          }}
        />
        {value && (
          <button
            type="button"
            aria-label="Clear actor filter"
            onClick={clear}
            className="absolute right-0 top-0 flex h-11 w-11 items-center justify-center rounded-lg text-warm-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30"
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>

      {open && (
        <div id={listId} role="listbox" className="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-warm-200 bg-white p-1 shadow-lg">
          {options.map((actor) => (
            <button
              key={actor.id}
              type="button"
              role="option"
              aria-selected={actor.id === value}
              onClick={() => choose(actor)}
              className="flex min-h-11 w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm text-warm-800 hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30"
            >
              <span className="truncate font-semibold">{actor.name}</span>
              <span className="shrink-0 text-xs text-warm-500">{actor.role}</span>
            </button>
          ))}
          {!loading && !error && options.length === 0 && (
            <p className="px-3 py-3 text-sm text-warm-500">No actors found.</p>
          )}
          {error && (
            <button type="button" onClick={() => setReloadKey((current) => current + 1)} className="min-h-11 w-full rounded-md px-3 text-left text-sm font-semibold text-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/30">
              Unable to load actors. Retry
            </button>
          )}
          {loading && <p role="status" className="px-3 py-3 text-sm text-warm-500">Loading actors</p>}
          {!loading && page < lastPage && (
            <button type="button" onClick={() => void loadMore()} className="min-h-11 w-full rounded-md px-3 text-sm font-semibold text-brand-green-700 hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30">
              Load more actors
            </button>
          )}
        </div>
      )}
    </div>
  );
}
