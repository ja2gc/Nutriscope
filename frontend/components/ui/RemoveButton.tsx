import { type ButtonHTMLAttributes } from "react";
import { X } from "lucide-react";

export function RemoveButton({ label, className = "", ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { label: string }) {
  return <button type="button" aria-label={label} title="Remove" {...props}
    className={`flex h-6 w-6 shrink-0 items-center justify-center rounded p-0 text-warm-500 hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 disabled:opacity-50 ${className}`}>
    <X aria-hidden="true" className="h-3.5 w-3.5" />
  </button>;
}
