"use client";

import { forwardRef } from "react";
import { Upload } from "lucide-react";

type ImageFilePickerProps = {
  id: string;
  name: string;
  label: string;
  current?: string | null;
  disabled?: boolean;
};

export const ImageFilePicker = forwardRef<HTMLInputElement, ImageFilePickerProps>(function ImageFilePicker(
  { id, name, label, current, disabled = false },
  ref,
) {
  return (
    <div className="space-y-2">
      <span className="block text-xs font-extrabold uppercase tracking-wider text-warm-500">{label}</span>
      <input ref={ref} id={id} type="file" name={name} accept="image/*" disabled={disabled} className="sr-only" />
      <label
        htmlFor={disabled ? undefined : id}
        aria-disabled={disabled}
        className={`inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-bold transition-colors ${
          disabled
            ? "cursor-not-allowed border-warm-200 bg-warm-100 text-warm-400"
            : "cursor-pointer border-forest-900 bg-forest-900 text-white hover:bg-forest-800"
        }`}
      >
        <Upload className="h-4 w-4" /> Choose image
      </label>
      <p className="text-xs text-warm-500">{current ? "Current image saved" : "No image saved"}</p>
    </div>
  );
});
