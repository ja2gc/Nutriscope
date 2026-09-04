"use client";

import React, { useRef } from "react";
import { tabTheme } from "./theme";

export interface TabItem<K extends string = string> {
  key: K;
  label: string;
  icon?: React.ReactNode;
}

/**
 * Underline tab bar (border-b-2 emerald), the shared pattern from Budget/Inventory.
 * Real tablist semantics + arrow-key navigation (a11y: keyboard-nav, aria, focus).
 */
export function Tabs<K extends string>({
  items,
  value,
  onChange,
  className = "",
  ariaLabel,
}: {
  items: TabItem<K>[];
  value: K;
  onChange: (k: K) => void;
  className?: string;
  ariaLabel?: string;
}) {
  const refs = useRef<(HTMLButtonElement | null)[]>([]);

  function onKeyDown(e: React.KeyboardEvent, i: number) {
    if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") return;
    e.preventDefault();
    const next = e.key === "ArrowRight" ? (i + 1) % items.length : (i - 1 + items.length) % items.length;
    onChange(items[next].key);
    refs.current[next]?.focus();
  }

  return (
    <div role="tablist" aria-label={ariaLabel} className={`flex border-b border-warm-200 ${className}`}>
      {items.map((t, i) => {
        const active = t.key === value;
        return (
          <button
            key={t.key}
            ref={(el) => { refs.current[i] = el; }}
            role="tab"
            aria-selected={active}
            tabIndex={active ? 0 : -1}
            onClick={() => onChange(t.key)}
            onKeyDown={(e) => onKeyDown(e, i)}
            className={`flex items-center gap-2 px-5 py-3 text-base font-semibold border-b-2 -mb-px cursor-pointer transition-colors rounded-t-md ${tabTheme.focus} ${
              active
                ? tabTheme.active
                : tabTheme.inactive
            }`}
          >
            {t.icon}
            {t.label}
          </button>
        );
      })}
    </div>
  );
}
