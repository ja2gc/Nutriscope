"use client";

import { useEffect } from "react";

export function LegacyAppCleanup() {
  useEffect(() => {
    if (!("serviceWorker" in navigator)) return;
    void navigator.serviceWorker.getRegistrations().then((registrations) => Promise.all(registrations
      .filter((registration) => registration.scope.includes("/fss/"))
      .map((registration) => registration.unregister())));
    if ("caches" in window) void caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith("nutriscope-fss-")).map((key) => caches.delete(key))));
  }, []);
  return null;
}
