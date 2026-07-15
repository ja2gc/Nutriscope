"use client";

import { useEffect, useRef } from "react";
import Link from "next/link";
import { X } from "lucide-react";
import type { AuditEntityDto, AuditEventDto } from "@/types/audit";
import { Badge, type BadgeTone } from "@/components/ui/Badge";
import { AuditChangeList } from "./AuditChangeList";
import { AuditTimestamp } from "./AuditTimestamp";
import { AuditValue } from "./AuditValue";

const outcomeTones: Record<AuditEventDto["outcome"], BadgeTone> = {
  success: "emerald",
  failure: "red",
  blocked: "amber",
};

function Entity({ entity, fallback }: { entity: AuditEntityDto | null; fallback: string }) {
  if (!entity) return <p className="text-sm text-warm-500">{fallback}</p>;
  return (
    <div className="min-w-0">
      <p className="text-sm font-semibold text-warm-800 break-words">{entity.label}</p>
      <p className="mt-1 text-xs text-warm-500 break-all">{entity.type}{entity.id ? ` · ${entity.id}` : ""}</p>
    </div>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="border-t border-warm-100 pt-4 first:border-0 first:pt-0">
      <h3 className="text-xs font-bold uppercase tracking-wider text-warm-500">{title}</h3>
      <div className="mt-2">{children}</div>
    </section>
  );
}

export function AuditEventDrawer({ event, onClose }: { event: AuditEventDto; onClose: () => void }) {
  const closeButton = useRef<HTMLButtonElement>(null);
  const drawer = useRef<HTMLElement>(null);

  useEffect(() => {
    const previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    closeButton.current?.focus();
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") onClose();
      if (event.key === "Tab" && drawer.current) {
        const focusable = Array.from(
          drawer.current.querySelectorAll<HTMLElement>("button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex='-1'])"),
        );
        const first = focusable[0];
        const last = focusable.at(-1);
        if (!first || !last) return;
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    }
    document.addEventListener("keydown", onKeyDown);
    return () => {
      document.removeEventListener("keydown", onKeyDown);
      previousFocus?.focus();
    };
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-50">
      <button
        type="button"
        aria-label="Close event details"
        className="absolute inset-0 h-full w-full cursor-default bg-warm-900/50"
        onClick={onClose}
      />
      <aside
        ref={drawer}
        role="dialog"
        aria-modal="true"
        aria-labelledby="audit-drawer-title"
        aria-describedby="audit-drawer-description"
        className="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-white shadow-xl"
      >
        <header className="flex items-start justify-between gap-4 border-b border-warm-200 p-5">
          <div className="min-w-0">
            <p className="text-xs font-bold uppercase tracking-wider text-brand-green-700">Audit event</p>
            <h2 id="audit-drawer-title" className="mt-1 text-xl font-extrabold text-warm-900 break-words">
              {event.action_label}
            </h2>
            <p id="audit-drawer-description" className="mt-1 text-xs font-semibold text-warm-500">Read-only audit record</p>
            <p className="mt-1 text-xs text-warm-500 break-all">Reference {event.id}</p>
          </div>
          <button
            ref={closeButton}
            type="button"
            onClick={onClose}
            aria-label="Close event details"
            className="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-warm-500 transition-colors hover:bg-warm-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30"
          >
            <X className="h-5 w-5" />
          </button>
        </header>

        <div className="flex-1 space-y-4 overflow-y-auto p-5">
          <Section title="Event summary">
            <p className="text-sm leading-relaxed text-warm-700 break-words">{event.summary}</p>
            <dl className="mt-3">
              <dt className="text-xs font-bold uppercase tracking-wider text-warm-500">Record type</dt>
              <dd className="mt-0.5 text-sm text-warm-700 break-words">{event.record_type}</dd>
            </dl>
            {event.reason && (
              <div className="mt-3 rounded-xl border border-warm-200 bg-warm-50 p-3">
                <p className="text-xs font-bold uppercase tracking-wider text-warm-500">Reason</p>
                <p className="mt-1 text-sm text-warm-700 break-words">{event.reason}</p>
              </div>
            )}
            <p className="mt-2 text-xs text-warm-500">
              <AuditTimestamp value={event.occurred_at} />
            </p>
          </Section>

          <Section title="Actor">
            {event.actor ? (
              <div>
                <p className="text-sm font-semibold text-warm-800 break-words">{event.actor.name}</p>
                <p className="mt-1 text-xs text-warm-500">
                  {event.actor.role || event.actor.kind}{event.actor.id ? ` · ${event.actor.id}` : ""}
                </p>
              </div>
            ) : (
              <p className="text-sm text-warm-500">System actor</p>
            )}
          </Section>

          <Section title="Record context">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              {event.patient && (
                <div className="rounded-xl border border-warm-200 p-3">
                  <p className="mb-2 text-xs font-bold uppercase tracking-wider text-warm-500">Patient</p>
                  <p className="text-sm font-semibold text-warm-800 break-words">{event.patient.display_name}</p>
                  {event.ncp_reference && <p className="mt-1 text-xs text-warm-500 break-all">{event.ncp_reference}</p>}
                </div>
              )}
              <div className="rounded-xl border border-warm-200 p-3">
                <p className="mb-2 text-xs font-bold uppercase tracking-wider text-warm-500">Subject</p>
                <Entity entity={event.subject} fallback="No subject recorded" />
              </div>
              <div className="rounded-xl border border-warm-200 p-3">
                <p className="mb-2 text-xs font-bold uppercase tracking-wider text-warm-500">Context</p>
                <Entity entity={event.context} fallback="No context recorded" />
              </div>
            </div>
          </Section>

          <Section title="Result">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone={outcomeTones[event.outcome]}>{event.outcome}</Badge>
              <Badge tone={event.severity === "critical" ? "red" : event.severity === "warning" ? "amber" : "zinc"}>
                {event.severity}
              </Badge>
            </div>
          </Section>

          <Section title="Recorded values">
            {event.details.length === 0 ? (
              <p className="text-sm text-warm-500">No recorded values available.</p>
            ) : (
              <dl className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                {event.details.map((detail) => (
                  <div key={detail.key} className="rounded-xl border border-warm-200 bg-warm-50 p-3">
                    <dt className="text-xs font-bold uppercase tracking-wider text-warm-500 break-words">{detail.label}</dt>
                    <dd className="mt-1 text-sm text-warm-700 break-words"><AuditValue value={detail.typed_value} /></dd>
                  </div>
                ))}
              </dl>
            )}
          </Section>

          <Section title="Field changes">
            <AuditChangeList changes={event.changes} clinical={event.category === "clinical"} />
          </Section>

          {event.history && (
            <Link
              href={`/admin/audit-logs/${event.id}/history`}
              className="inline-flex min-h-11 items-center font-bold text-brand-green-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30"
            >
              {event.history.label}
            </Link>
          )}
        </div>
      </aside>
    </div>
  );
}
