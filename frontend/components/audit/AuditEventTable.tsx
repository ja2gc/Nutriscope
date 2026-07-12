"use client";

import type { AuditEventDto } from "@/types/audit";
import { Badge, type BadgeTone } from "@/components/ui/Badge";
import { AuditTimestamp } from "./AuditTimestamp";

const outcomeTones: Record<AuditEventDto["outcome"], BadgeTone> = {
  success: "emerald",
  failure: "red",
  blocked: "amber",
};

function severityTone(severity: AuditEventDto["severity"]): BadgeTone {
  if (severity === "critical") return "red";
  if (severity === "warning") return "amber";
  return "zinc";
}

function subjectContext(event: AuditEventDto) {
  return [event.subject?.label, event.context?.label].filter(Boolean).join(" · ") || "No subject or context";
}

export function AuditEventTable({ events, onSelect }: { events: AuditEventDto[]; onSelect: (event: AuditEventDto) => void }) {
  return (
    <>
      <div className="hidden md:block overflow-x-auto">
        <table className="w-full min-w-[1120px] text-left">
          <thead className="border-b border-warm-200 bg-warm-50">
            <tr>
              {(["Time", "Action", "Actor", "Subject / context", "Outcome", "Severity", "Summary"] as const).map((label) => (
                <th key={label} className="px-4 py-3 text-xs font-bold uppercase tracking-wider text-warm-500">{label}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-warm-100">
            {events.map((event) => {
              return (
                <tr
                  key={event.id}
                  onClick={() => onSelect(event)}
                  className="cursor-pointer transition-colors hover:bg-warm-50"
                >
                  <td className="px-4 py-3 align-top whitespace-nowrap">
                    <AuditTimestamp value={event.occurred_at} layout="stacked" />
                  </td>
                  <td className="px-4 py-3 align-top"><Badge tone="emerald">{event.action_label}</Badge></td>
                  <td className="max-w-48 px-4 py-3 align-top text-sm font-semibold text-warm-800 break-words">
                    {event.actor?.name || "System"}
                  </td>
                  <td className="max-w-56 px-4 py-3 align-top text-sm text-warm-700 break-words">{subjectContext(event)}</td>
                  <td className="px-4 py-3 align-top"><Badge tone={outcomeTones[event.outcome]}>{event.outcome}</Badge></td>
                  <td className="px-4 py-3 align-top"><Badge tone={severityTone(event.severity)}>{event.severity}</Badge></td>
                  <td className="max-w-72 p-0 align-top text-sm leading-relaxed text-warm-600 break-words">
                    <button
                      type="button"
                      aria-label={`Inspect ${event.action_label} audit event: ${event.summary}`}
                      onClick={(clickEvent) => {
                        clickEvent.stopPropagation();
                        onSelect(event);
                      }}
                      className="min-h-11 w-full cursor-pointer px-4 py-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-green-500/30"
                    >
                      {event.summary}
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <div className="md:hidden divide-y divide-warm-100">
        {events.map((event) => {
          return (
            <button
              key={event.id}
              type="button"
              onClick={() => onSelect(event)}
              className="block min-h-11 w-full cursor-pointer p-4 text-left transition-colors hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-green-500/30"
            >
              <div className="flex flex-wrap items-start justify-between gap-2">
                <Badge tone="emerald">{event.action_label}</Badge>
                <span className="text-xs tabular-nums text-warm-500"><AuditTimestamp value={event.occurred_at} /></span>
              </div>
              <p className="mt-3 text-sm font-semibold text-warm-800 break-words">{event.actor?.name || "System"}</p>
              <p className="mt-1 text-sm text-warm-600 break-words">{subjectContext(event)}</p>
              <p className="mt-2 text-sm leading-relaxed text-warm-700 break-words">{event.summary}</p>
              <div className="mt-3 flex flex-wrap gap-2">
                <Badge tone={outcomeTones[event.outcome]}>{event.outcome}</Badge>
                <Badge tone={severityTone(event.severity)}>{event.severity}</Badge>
              </div>
            </button>
          );
        })}
      </div>
    </>
  );
}
