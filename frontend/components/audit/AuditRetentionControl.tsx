"use client";

import { useEffect, useRef, useState, type KeyboardEvent } from "react";
import { AlertTriangle, Clock3, ShieldCheck } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import type { AuditRetentionState } from "@/types/audit";

const categories: Array<{ key: keyof AuditRetentionState["periods"]; label: string }> = [
  { key: "security", label: "Security" },
  { key: "clinical", label: "Clinical" },
  { key: "operations", label: "Operations" },
  { key: "legacy", label: "Legacy" },
];

export function AuditRetentionControl({
  retention,
  onUpdate,
}: {
  retention: AuditRetentionState;
  onUpdate: (enabled: boolean) => Promise<AuditRetentionState>;
}) {
  const [current, setCurrent] = useState(retention);
  const [confirming, setConfirming] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const enableButtonRef = useRef<HTMLButtonElement>(null);
  const cancelButtonRef = useRef<HTMLButtonElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const restoreFocus = useRef(false);

  useEffect(() => setCurrent(retention), [retention]);

  useEffect(() => {
    if (confirming) {
      restoreFocus.current = true;
      cancelButtonRef.current?.focus();
    } else if (restoreFocus.current) {
      restoreFocus.current = false;
      enableButtonRef.current?.focus();
    }
  }, [confirming]);

  function handleDialogKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    if (event.key === "Escape" && !saving) {
      event.preventDefault();
      setConfirming(false);
      return;
    }

    if (event.key !== "Tab") return;
    const buttons = Array.from(
      dialogRef.current?.querySelectorAll<HTMLButtonElement>("button:not(:disabled)") ?? [],
    );
    if (buttons.length === 0) return;

    const first = buttons[0];
    const last = buttons[buttons.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  async function update(enabled: boolean) {
    setSaving(true);
    setError(null);
    try {
      setCurrent(await onUpdate(enabled));
      setConfirming(false);
    } catch {
      setError("Scheduled deletion could not be updated. Try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card padded className="relative">
      <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div className="max-w-2xl">
          <p className="flex items-center gap-2 text-sm font-bold text-warm-900">
            <Clock3 className="h-4 w-4 text-brand-green-600" />
            Scheduled audit deletion
          </p>
          <p className="mt-1 text-sm leading-relaxed text-warm-600">
            Retention periods are fixed deployment settings. This control only enables or disables the daily schedule.
          </p>
        </div>
        <div className="flex flex-col items-start gap-2 lg:items-end">
          <span className={`rounded-full px-3 py-1 text-xs font-bold ${current.enabled
            ? "bg-brand-green-50 text-brand-green-700"
            : "bg-warm-100 text-warm-700"}`}>
            Scheduled deletion is {current.enabled ? "ON" : "OFF"}
          </span>
          <Button
            ref={enableButtonRef}
            variant={current.enabled ? "secondary" : "primary"}
            size="sm"
            loading={saving}
            onClick={() => current.enabled ? void update(false) : setConfirming(true)}
          >
            {current.enabled ? "Disable scheduled deletion" : "Enable scheduled deletion"}
          </Button>
        </div>
      </div>

      <dl className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {categories.map(({ key, label }) => (
          <div key={key} className="rounded-xl border border-warm-200 bg-warm-50 px-4 py-3">
            <dt className="text-xs font-semibold uppercase tracking-wide text-warm-500">{label}</dt>
            <dd className="mt-1 text-sm font-bold text-warm-900">{current.periods[key].toLocaleString()} days</dd>
          </div>
        ))}
      </dl>

      {error && <p role="alert" className="mt-3 text-sm font-semibold text-red-700">{error}</p>}

      {confirming && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4" role="presentation">
          <div
            ref={dialogRef}
            role="dialog"
            aria-modal="true"
            aria-labelledby="retention-confirm-title"
            aria-describedby="retention-confirm-description"
            onKeyDown={handleDialogKeyDown}
            className="w-full max-w-lg rounded-2xl border border-warm-200 bg-white p-6 shadow-xl"
          >
            <div className="flex items-start gap-3">
              <AlertTriangle className="mt-0.5 h-6 w-6 shrink-0 text-amber-600" />
              <div>
                <h2 id="retention-confirm-title" className="text-lg font-extrabold text-warm-900">
                  Enable permanent scheduled deletion?
                </h2>
                <div id="retention-confirm-description" className="mt-3 space-y-2 text-sm leading-relaxed text-warm-700">
                  <p>Deletion is scheduled daily.</p>
                  <p>Rows older than the configured periods for each category are permanently deleted and unrecoverable.</p>
                  <p className="font-semibold">Enable this only after privacy/compliance owner approval.</p>
                </div>
              </div>
            </div>
            <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <Button ref={cancelButtonRef} variant="secondary" disabled={saving} onClick={() => setConfirming(false)}>Cancel</Button>
              <Button loading={saving} onClick={() => void update(true)}>
                <ShieldCheck className="h-4 w-4" />
                Confirm and enable
              </Button>
            </div>
          </div>
        </div>
      )}
    </Card>
  );
}
