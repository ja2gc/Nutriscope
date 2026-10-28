"use client";

import type { AuditEventDto } from "@/types/audit";
import { Badge } from "@/components/ui/Badge";
import { AuditTimestamp } from "./AuditTimestamp";

function subjectLabel(event: AuditEventDto) {
  return event.patient?.code || event.ncp_reference || event.subject?.label || "No subject recorded";
}

export function AuditEventTable({ events, onSelect }: { events: AuditEventDto[]; onSelect: (event: AuditEventDto) => void }) {
  return (
    <>
      <div className="hidden md:block overflow-x-auto">
        <table className="w-full min-w-[900px] text-left">
          <thead className="border-b border-warm-200 bg-warm-50">
            <tr>
              {(["Time", "Action", "Actor", "Subject", "Summary"] as const).map((label) => (
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
                  className="h-[68px] cursor-pointer transition-colors hover:bg-warm-50"
                >
                  <td className="px-4 py-3 align-middle whitespace-nowrap">
                    <AuditTimestamp value={event.occurred_at} layout="stacked" />
                  </td>
                  <td className="px-4 py-3 align-middle"><Badge tone="emerald">{event.action_label}</Badge></td>
                  <td className="max-w-48 truncate px-4 py-3 align-middle text-sm font-semibold text-warm-800">
                    {event.actor?.name || "System"}
                  </td>
                  <td className="max-w-56 truncate px-4 py-3 align-middle text-sm text-warm-700">{subjectLabel(event)}</td>
                  <td className="max-w-72 p-0 align-middle text-sm text-warm-600">
                    <button
                      type="button"
                      aria-label={`Inspect ${event.action_label} audit event: ${event.summary}`}
                      onClick={(clickEvent) => {
                        clickEvent.stopPropagation();
                        onSelect(event);
                      }}
                      className="w-full truncate px-4 py-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-green-500/30"
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
              className="flex h-40 w-full cursor-pointer flex-col justify-between overflow-hidden p-4 text-left transition-colors hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-green-500/30"
            >
              <div className="flex flex-wrap items-start justify-between gap-2">
                <Badge tone="emerald">{event.action_label}</Badge>
                <span className="text-xs tabular-nums text-warm-500"><AuditTimestamp value={event.occurred_at} /></span>
              </div>
              <p className="line-clamp-1 text-sm font-semibold text-warm-800">{event.actor?.name || "System"}</p>
              <p className="line-clamp-1 text-sm text-warm-600">{subjectLabel(event)}</p>
              <p className="line-clamp-2 text-sm leading-relaxed text-warm-700">{event.summary}</p>
            </button>
          );
        })}
      </div>
    </>
  );
}
