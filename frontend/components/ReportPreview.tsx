"use client";

import React, { useEffect, useRef, useState } from "react";
import type { PDFDocumentProxy, PDFPageProxy, RenderTask } from "pdfjs-dist";
import { AlertTriangle, Download, Loader2, X } from "lucide-react";

function PdfPage({ page }: { page: PDFPageProxy }) {
  const containerRef = useRef<HTMLDivElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const renderTaskRef = useRef<RenderTask | null>(null);

  useEffect(() => {
    const container = containerRef.current;
    const canvas = canvasRef.current;
    if (!container || !canvas) return;

    let disposed = false;
    let resizeTimer: ReturnType<typeof setTimeout> | null = null;

    const render = async () => {
      const context = canvas.getContext("2d");
      if (!context || disposed) return;

      renderTaskRef.current?.cancel();
      const baseViewport = page.getViewport({ scale: 1 });
      const cssWidth = Math.max(1, Math.min(container.clientWidth, baseViewport.width * 1.5));
      const outputScale = Math.min(window.devicePixelRatio || 1, 2);
      const viewport = page.getViewport({ scale: (cssWidth / baseViewport.width) * outputScale });

      canvas.width = Math.floor(viewport.width);
      canvas.height = Math.floor(viewport.height);
      canvas.style.width = `${Math.floor(viewport.width / outputScale)}px`;
      canvas.style.height = `${Math.floor(viewport.height / outputScale)}px`;

      const task = page.render({ canvas, canvasContext: context, viewport });
      renderTaskRef.current = task;
      try {
        await task.promise;
      } catch (cause) {
        if (!(cause instanceof Error) || cause.name !== "RenderingCancelledException") throw cause;
      }
    };

    const scheduleRender = () => {
      if (resizeTimer) clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => { void render(); }, 80);
    };
    const observer = new ResizeObserver(scheduleRender);
    observer.observe(container);
    void render();

    return () => {
      disposed = true;
      if (resizeTimer) clearTimeout(resizeTimer);
      observer.disconnect();
      renderTaskRef.current?.cancel();
    };
  }, [page]);

  return (
    <div ref={containerRef} className="w-full" data-pdf-page={page.pageNumber}>
      <canvas
        ref={canvasRef}
        role="img"
        aria-label={`PDF page ${page.pageNumber}`}
        className="mx-auto block max-w-full bg-white shadow-md"
      />
    </div>
  );
}

/** Shared, app-rendered PDF preview. Download remains available as a fallback. */
export function ReportPreview({
  title,
  src,
  downloadUrl,
  onClose,
}: {
  title: string;
  src: string;
  downloadUrl: string;
  onClose: () => void;
}) {
  const [pages, setPages] = useState<PDFPageProxy[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => { if (event.key === "Escape") onClose(); };
    document.addEventListener("keydown", onKey);
    closeRef.current?.focus();
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = previousOverflow;
    };
  }, [onClose]);

  useEffect(() => {
    let disposed = false;
    let documentProxy: PDFDocumentProxy | null = null;

    setLoading(true);
    setError(null);
    setPages([]);

    void import("pdfjs-dist/webpack.mjs")
      .then(async (pdfjs) => {
        const loadingTask = pdfjs.getDocument({ url: src, withCredentials: true });
        const loadedDocument = await loadingTask.promise;
        documentProxy = loadedDocument;
        const loadedPages = await Promise.all(
          Array.from({ length: loadedDocument.numPages }, (_, index) => loadedDocument.getPage(index + 1)),
        );
        if (!disposed) setPages(loadedPages);
      })
      .catch((cause: unknown) => {
        if (!disposed) setError(cause instanceof Error ? cause.message : "The report preview could not be rendered.");
      })
      .finally(() => { if (!disposed) setLoading(false); });

    return () => {
      disposed = true;
      void documentProxy?.destroy();
    };
  }, [src]);

  return (
    <div
      className="fixed inset-0 z-[1000] flex items-center justify-center bg-black/50 p-0 sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-label={`${title} preview`}
      onClick={onClose}
    >
      <div
        className="flex h-full w-full max-w-5xl flex-col overflow-hidden bg-white shadow-2xl sm:h-[88vh] sm:rounded-2xl"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex shrink-0 items-center justify-between gap-2 border-b border-warm-100 px-3 py-2 sm:px-5 sm:py-3">
          <div className="min-w-0">
            <h2 className="truncate text-sm font-bold text-warm-800 sm:text-base">{title}</h2>
            {pages.length > 0 && <p className="text-xs text-warm-400">{pages.length} page{pages.length === 1 ? "" : "s"}</p>}
          </div>
          <div className="flex shrink-0 items-center gap-1 sm:gap-2">
            <a
              href={downloadUrl}
              download
              aria-label={`Download ${title}`}
              className="flex h-11 items-center gap-1.5 rounded-lg border border-warm-200 bg-white px-3 text-sm font-semibold text-warm-700 transition-colors hover:bg-warm-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30"
            >
              <Download className="h-4 w-4" /> <span className="hidden sm:inline">Download</span>
            </a>
            <button
              ref={closeRef}
              onClick={onClose}
              aria-label="Close preview"
              className="flex h-11 w-11 cursor-pointer items-center justify-center rounded-lg text-warm-500 hover:bg-warm-100 hover:text-warm-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="relative flex-1 overflow-y-auto bg-warm-100 p-3 sm:p-5">
          {loading && (
            <div className="absolute inset-0 flex items-center justify-center gap-2 text-sm text-warm-500" role="status">
              <Loader2 className="h-4 w-4 animate-spin" /> Rendering report…
            </div>
          )}
          {error && (
            <div className="mx-auto mt-10 max-w-md rounded-xl border border-red-200 bg-white p-5 text-center" role="alert">
              <AlertTriangle className="mx-auto h-6 w-6 text-red-500" />
              <p className="mt-2 text-sm font-bold text-warm-800">Preview unavailable</p>
              <p className="mt-1 text-sm text-warm-500">{error}</p>
              <p className="mt-2 text-xs text-warm-400">You can still download the report using the button above.</p>
            </div>
          )}
          {!loading && !error && (
            <div className="mx-auto flex w-full max-w-[1200px] flex-col gap-4">
              {pages.map((page) => <PdfPage key={page.pageNumber} page={page} />)}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
