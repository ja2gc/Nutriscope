"use client";

import { RefreshCw } from "lucide-react";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState } from "react";

export function PwaRegistration() {
  const pathname = usePathname();
  const pwaSurface = pathname.startsWith("/fss") || pathname === "/mobile-app";
  const [waitingWorker, setWaitingWorker] = useState<ServiceWorker | null>(null);
  const reloadForUpdate = useRef(false);

  useEffect(() => {
    if (!pwaSurface || process.env.NODE_ENV !== "production" || !("serviceWorker" in navigator)) return;

    let cancelled = false;
    let registration: ServiceWorkerRegistration | undefined;

    const offerWaitingUpdate = () => {
      if (!cancelled && registration?.waiting) setWaitingWorker(registration.waiting);
    };
    const watchInstallingWorker = () => {
      const installing = registration?.installing;
      if (!installing) return;
      installing.addEventListener("statechange", offerWaitingUpdate);
    };
    const controllerChanged = () => {
      if (!reloadForUpdate.current) return;
      reloadForUpdate.current = false;
      window.location.reload();
    };

    navigator.serviceWorker.addEventListener("controllerchange", controllerChanged);
    void (async () => {
      const registrations = await navigator.serviceWorker.getRegistrations();
      await Promise.all(registrations.filter((item) => {
        const worker = item.active ?? item.waiting ?? item.installing;
        return !item.scope.endsWith("/fss/") && worker?.scriptURL.endsWith("/sw.js");
      }).map((item) => item.unregister()));
      registration = await navigator.serviceWorker.register("/sw.js", { scope: "/fss/" });
      if (cancelled) return;
      offerWaitingUpdate();
      registration.addEventListener("updatefound", watchInstallingWorker);
      void registration.update();
    })();

    return () => {
      cancelled = true;
      registration?.removeEventListener("updatefound", watchInstallingWorker);
      navigator.serviceWorker.removeEventListener("controllerchange", controllerChanged);
    };
  }, [pwaSurface]);

  function applyUpdate() {
    if (!waitingWorker) return;
    reloadForUpdate.current = true;
    waitingWorker.postMessage({ type: "SKIP_WAITING" });
  }

  if (!pwaSurface || !waitingWorker) return null;

  return (
    <aside role="status" aria-live="polite" className="fixed inset-x-4 top-4 z-[100] mx-auto flex max-w-md items-center gap-3 rounded-2xl border border-emerald-200 bg-white p-3 shadow-lg" style={{ marginTop: "env(safe-area-inset-top)" }}>
      <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><RefreshCw className="h-5 w-5" aria-hidden="true" /></span>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-extrabold text-warm-900">Update available</p>
        <p className="text-xs leading-5 text-warm-500">Restart when you are ready.</p>
      </div>
      <button type="button" onClick={applyUpdate} className="min-h-11 cursor-pointer rounded-xl bg-emerald-700 px-3 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">Update and restart</button>
    </aside>
  );
}
