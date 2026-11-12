"use client";

import { useEffect, useState } from "react";

export function useMenuSnackVisibility(storageKey: string) {
  const [visible, setVisible] = useState(true);
  useEffect(() => {
    try { setVisible(localStorage.getItem(storageKey) !== "false"); } catch { /* Keep slots visible if storage is unavailable. */ }
  }, [storageKey]);
  function changeVisibility(next: boolean) {
    setVisible(next);
    try { localStorage.setItem(storageKey, String(next)); } catch { /* Visibility still works for this session. */ }
  }
  return [visible, changeVisibility] as const;
}

export function MenuSnackVisibility({ visible, onChange, disabled = false }: { visible: boolean; onChange: (visible: boolean) => void; disabled?: boolean }) {
  return <label className="flex min-h-11 items-center gap-2 text-sm font-semibold text-warm-700">
    <input type="checkbox" aria-label="Show snack slots" checked={visible} disabled={disabled} onChange={(event) => onChange(event.target.checked)} className="h-4 w-4 accent-emerald-600" />
    Show snack slots
  </label>;
}
