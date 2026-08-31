"use client";

import { LoaderCircle, Search, X } from "lucide-react";
import { useId } from "react";

import { cn } from "@/lib/utils";

type SearchInputProps = {
  label: string;
  value: string;
  onChange: (value: string) => void;
  loading?: boolean;
  className?: string;
  autoFocus?: boolean;
  id?: string;
};

export default function SearchInput({
  label,
  value,
  onChange,
  loading = false,
  className,
  autoFocus = false,
  id: providedId,
}: SearchInputProps) {
  const generatedId = useId();
  const id = providedId ?? generatedId;

  return (
    <div className={cn("relative w-full", className)}>
      <label htmlFor={id} className="sr-only">{label}</label>
      <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-warm-400" />
      <input
        id={id}
        type="search"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        autoFocus={autoFocus}
        className="min-h-11 w-full rounded-lg border border-warm-200 bg-white py-2 pl-9 pr-11 text-base text-warm-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
      />
      {loading ? (
        <span role="status" aria-label="Searching" className="absolute right-3 top-1/2 -translate-y-1/2 text-emerald-600">
          <LoaderCircle aria-hidden="true" className="h-4 w-4 animate-spin" />
        </span>
      ) : value ? (
        <button
          type="button"
          aria-label="Clear search"
          onClick={() => onChange("")}
          className="absolute right-0 top-0 flex h-11 w-11 items-center justify-center rounded-r-lg text-warm-500 hover:text-warm-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500"
        >
          <X aria-hidden="true" className="h-4 w-4" />
        </button>
      ) : null}
    </div>
  );
}
