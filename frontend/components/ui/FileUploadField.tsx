"use client";

import { useId, useImperativeHandle, useRef, useState, type ReactNode, type Ref } from "react";
import { Upload } from "lucide-react";
import { Button } from "./Button";
import { validateUploadFiles } from "@/lib/uploadValidation";

type FileUploadFieldProps = {
  id?: string;
  name?: string;
  inputRef?: Ref<HTMLInputElement>;
  label: string;
  allowedTypes: readonly string[];
  maxBytes: number;
  maxFiles?: number;
  currentCount?: number;
  multiple?: boolean;
  disabled?: boolean;
  busy?: boolean;
  error?: string | null;
  buttonLabel?: string;
  onFilesSelected: (files: File[]) => void | Promise<void>;
  children?: ReactNode;
};

export function FileUploadField({
  id, name, inputRef, label, allowedTypes, maxBytes, maxFiles, currentCount = 0,
  multiple = true, disabled = false, busy = false, error, buttonLabel = "Add files",
  onFilesSelected, children,
}: FileUploadFieldProps) {
  const generatedId = useId();
  const inputId = id ?? `upload-${generatedId}`;
  const input = useRef<HTMLInputElement>(null);
  useImperativeHandle(inputRef, () => input.current!, []);
  const pending = useRef(false);
  const [reading, setReading] = useState(false);
  const [dragging, setDragging] = useState(false);
  const [selectionError, setSelectionError] = useState<string | null>(null);
  const blocked = disabled || busy || reading;
  const message = selectionError ?? error;
  const formats = allowedTypes.map(type => ({
    "image/jpeg": "JPEG", "image/png": "PNG", "image/webp": "WebP", "application/pdf": "PDF",
  }[type] ?? type)).join(", ");
  const maxSize = maxBytes % (1024 * 1024) === 0 ? `${maxBytes / (1024 * 1024)} MiB` : `${Math.round(maxBytes / 1024)} KiB`;
  const noun = allowedTypes.every(type => type.startsWith("image/")) ? "image" : "file";

  async function selectFiles(files: File[]) {
    if (!files.length || blocked || pending.current) return;
    const validation = validateUploadFiles(files, {
      allowedTypes, maxBytes, maxFiles: multiple ? maxFiles : 1,
    }, multiple ? currentCount : 0);
    if (!validation.valid) { setSelectionError(validation.error); return; }
    pending.current = true;
    setReading(true);
    setSelectionError(null);
    try {
      await onFilesSelected(files);
    } catch (caught) {
      setSelectionError(caught instanceof Error ? caught.message : "Could not add the selected files. Try again.");
    } finally {
      pending.current = false;
      setReading(false);
    }
  }

  return <section aria-label={label} aria-busy={busy || reading} className="min-w-0 space-y-3">
    <div className="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
      <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
        <label htmlFor={inputId} className="break-words text-xs font-semibold uppercase tracking-wider text-warm-500">{label}</label>
        <span className="text-xs text-warm-500">{currentCount}{maxFiles !== undefined && multiple ? ` / ${maxFiles}` : ""} {noun}{currentCount === 1 ? "" : "s"}</span>
      </div>
      <Button type="button" size="sm" disabled={blocked} onClick={() => input.current?.click()}
        aria-controls={inputId} className="min-h-11 w-full shrink-0 sm:w-auto">
        <Upload aria-hidden="true" className="h-4 w-4 shrink-0" />{buttonLabel}
      </Button>
    </div>
    <input ref={input} id={inputId} name={name} type="file" accept={allowedTypes.join(",")} multiple={multiple}
      disabled={blocked} tabIndex={-1} aria-describedby={`${inputId}-limits`} aria-invalid={Boolean(message)}
      onChange={event => {
        const files = Array.from(event.currentTarget.files ?? []);
        event.currentTarget.value = "";
        void selectFiles(files);
      }} className="sr-only" />
    <p id={`${inputId}-limits`} className="break-words text-xs text-warm-500">
      {formats} · {maxSize} maximum per file{maxFiles !== undefined && multiple ? ` · ${Math.max(0, maxFiles - currentCount)} remaining` : ""}
    </p>
    <div role="button" tabIndex={blocked ? -1 : 0} aria-disabled={blocked} aria-label={`Drop files for ${label}`}
      aria-describedby={`${inputId}-limits`} onClick={() => { if (!blocked) input.current?.click(); }}
      onKeyDown={event => {
        if (event.key === "Enter" || event.key === " ") { event.preventDefault(); if (!blocked) input.current?.click(); }
      }}
      onDragOver={event => { event.preventDefault(); if (!blocked) setDragging(true); }}
      onDragLeave={event => { if (!(event.relatedTarget instanceof Node) || !event.currentTarget.contains(event.relatedTarget)) setDragging(false); }}
      onDrop={event => { event.preventDefault(); setDragging(false); void selectFiles(Array.from(event.dataTransfer.files)); }}
      className={`min-w-0 rounded-xl border-2 border-dashed px-4 py-5 text-center text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/40 ${
        blocked ? "cursor-not-allowed border-warm-200 bg-warm-50 text-warm-400" : dragging
          ? "cursor-pointer border-brand-green-600 bg-brand-green-50 text-brand-green-700"
          : "cursor-pointer border-warm-300 bg-warm-50/40 text-warm-500 hover:border-brand-green-600"
      }`}>
      {busy || reading ? <span role="status">{busy ? "Uploading..." : "Adding files..."}</span> : "Drag and drop files here, or click to choose."}
    </div>
    {message && <p role="alert" className="break-words rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{message}</p>}
    {children}
  </section>;
}
