"use client";

import { useEffect, useRef } from "react";
import { createPortal } from "react-dom";
import { ChevronLeft, ChevronRight, Download, Trash2, X } from "lucide-react";
import type { UploadImage } from "./ImageUploadGallery";

export function ImageViewer({ images, activeIndex, onIndexChange, onClose, onRemove, busy = false, error, download = false, removeLabel = "Remove image" }: {
  images: UploadImage[];
  activeIndex: number;
  onIndexChange: (index: number) => void;
  onClose: () => void;
  onRemove?: (image: UploadImage) => void | Promise<void>;
  busy?: boolean;
  error?: string | null;
  download?: boolean;
  removeLabel?: string;
}) {
  const panel = useRef<HTMLDivElement>(null);
  const index = Math.min(activeIndex, Math.max(0, images.length - 1));
  const image = images[index];
  useEffect(() => {
    const trigger = document.activeElement;
    const fallback = trigger instanceof HTMLElement ? trigger.closest("section")?.querySelector<HTMLButtonElement>("button") : null;
    const overflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    panel.current?.querySelector<HTMLButtonElement>("button")?.focus();
    return () => {
      document.body.style.overflow = overflow;
      if (trigger instanceof HTMLElement && trigger.isConnected) trigger.focus();
      else fallback?.focus();
    };
  }, []);
  if (!image) return null;
  const move = (offset: number) => { if (!busy) onIndexChange((index + offset + images.length) % images.length); };
  return createPortal(<div ref={panel} role="dialog" aria-modal="true" aria-label="Image preview"
    onClick={event => { event.stopPropagation(); if (event.target === event.currentTarget) onClose(); }}
    onKeyDown={event => {
      event.stopPropagation();
      if (event.key === "Escape") { event.preventDefault(); onClose(); }
      if (images.length > 1 && (event.key === "ArrowLeft" || event.key === "ArrowRight")) {
        event.preventDefault(); move(event.key === "ArrowLeft" ? -1 : 1);
      }
      if (event.key === "Tab") {
        const controls = panel.current?.querySelectorAll<HTMLElement>('button:not(:disabled),a[href]');
        if (!controls?.length) return;
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      }
    }} className="fixed inset-0 z-[90] grid min-w-0 grid-rows-[auto_minmax(0,1fr)_auto] gap-2 bg-black/90 p-3 sm:p-4">
    <div className="flex min-w-0 items-center justify-between gap-2 text-white">
      <span aria-live="polite" className="min-w-0 text-sm">{images.length > 1 ? `${index + 1} / ${images.length}` : ""}</span>
      <button type="button" aria-label="Close image preview" onClick={onClose}
        className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white hover:bg-white/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">
        <X aria-hidden="true" className="h-5 w-5" />
      </button>
    </div>
    <div className="relative flex min-h-0 min-w-0 items-center justify-center">
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={image.src} alt={image.name} className="block h-full max-h-full w-full object-contain" />
    {images.length > 1 && <>
      <button type="button" aria-label="Previous image" disabled={busy} onClick={() => move(-1)}
        className="absolute left-1 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/80 focus-visible:ring-2 focus-visible:ring-white disabled:opacity-50">
        <ChevronLeft aria-hidden="true" className="h-6 w-6" />
      </button>
      <button type="button" aria-label="Next image" disabled={busy} onClick={() => move(1)}
        className="absolute right-1 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/80 focus-visible:ring-2 focus-visible:ring-white disabled:opacity-50">
        <ChevronRight aria-hidden="true" className="h-6 w-6" />
      </button>
    </>}
    </div>
    <div className="flex max-h-[30dvh] min-w-0 flex-wrap items-center justify-center gap-2 overflow-y-auto text-white">
      {error && <p role="alert" className="w-full break-words text-center text-sm text-red-200">{error}</p>}
      {download && <a href={image.src} download={image.name} className="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm hover:bg-white/15"><Download aria-hidden="true" className="h-4 w-4 shrink-0" />Download</a>}
      {onRemove && <button type="button" aria-label="Remove image" disabled={busy} onClick={() => { void onRemove(image); }}
        className="inline-flex min-h-11 min-w-0 items-center justify-center gap-2 rounded-lg px-3 text-sm hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-white disabled:opacity-50">
        <Trash2 aria-hidden="true" className="h-4 w-4 shrink-0" /><span className="min-w-0 break-words">{busy ? "Removing..." : removeLabel}</span>
      </button>}
    </div>
  </div>, document.body);
}
