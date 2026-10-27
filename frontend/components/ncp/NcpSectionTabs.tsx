import React, { useRef } from "react";

export interface NcpSectionTab<K extends string = string> {
  key: K;
  label: string;
  icon?: React.ReactNode;
  marker?: boolean;
}

export function NcpSectionTabs<K extends string>({
  items,
  value,
  onChange,
  ariaLabel,
  idPrefix,
  panelId,
  className = "",
}: {
  items: NcpSectionTab<K>[];
  value: K;
  onChange: (key: K) => void;
  ariaLabel: string;
  idPrefix: string;
  panelId?: string;
  className?: string;
}) {
  const refs = useRef<(HTMLButtonElement | null)[]>([]);

  function onKeyDown(event: React.KeyboardEvent<HTMLButtonElement>, index: number) {
    if (event.key !== "ArrowRight" && event.key !== "ArrowLeft") return;
    event.preventDefault();
    const nextIndex = event.key === "ArrowRight"
      ? (index + 1) % items.length
      : (index - 1 + items.length) % items.length;
    onChange(items[nextIndex].key);
    refs.current[nextIndex]?.focus();
  }

  return (
    <div
      role="tablist"
      aria-label={ariaLabel}
      className={`flex min-w-0 max-w-full flex-nowrap overflow-x-auto overflow-y-hidden overscroll-x-contain border-b border-warm-200 bg-warm-50/50 ${className}`}
    >
      {items.map((item, index) => {
        const selected = item.key === value;
        return (
          <button
            key={item.key}
            ref={(element) => { refs.current[index] = element; }}
            type="button"
            role="tab"
            id={`${idPrefix}-tab-${item.key}`}
            aria-selected={selected}
            aria-controls={panelId ?? `${idPrefix}-panel-${item.key}`}
            tabIndex={selected ? 0 : -1}
            onClick={() => onChange(item.key)}
            onKeyDown={(event) => onKeyDown(event, index)}
            className={`relative flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-4 py-3 text-xs font-bold uppercase tracking-wider transition-all cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500 ${selected
              ? "text-emerald-700 border-emerald-600 bg-white"
              : "text-warm-500 border-transparent hover:text-warm-700 hover:bg-white/50"
            }`}
          >
            {item.icon}
            {item.label}
            {item.marker && <span aria-label="Unsaved changes" className="absolute right-1 top-1 h-1.5 w-1.5 rounded-full bg-amber-400" />}
          </button>
        );
      })}
    </div>
  );
}
