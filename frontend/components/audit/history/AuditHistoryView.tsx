"use client";

import Link from "next/link";
import { useState } from "react";
import { Eye, History } from "lucide-react";
import { AuditTimestamp } from "@/components/audit/AuditTimestamp";
import { AuditValue } from "@/components/audit/AuditValue";
import { Badge } from "@/components/ui/Badge";
import { Card } from "@/components/ui/Card";
import type {
  AuditHistoryChange,
  AuditHistoryDto,
  AuditHistorySnapshotDto,
} from "@/types/auditHistory";

const changeTone: Record<AuditHistoryChange, "emerald" | "amber" | "red"> = {
  added: "emerald",
  changed: "amber",
  removed: "red",
};

function changeLabel(change: AuditHistoryChange) {
  return change.charAt(0).toUpperCase() + change.slice(1);
}

function Snapshot({ snapshot }: { snapshot: AuditHistorySnapshotDto }) {
  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-xl font-extrabold text-warm-900 break-words">{snapshot.title}</h2>
        <p className="mt-1 text-xs text-warm-500 break-all">Reference {snapshot.reference}</p>
      </div>

      {snapshot.fields.length > 0 && (
        <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {snapshot.fields.map((field) => (
            <div key={field.key} className="rounded-xl border border-warm-200 bg-warm-50 p-4">
              <dt className="flex flex-wrap items-center justify-between gap-2 text-xs font-bold uppercase tracking-wider text-warm-500">
                <span>{field.label}</span>
                {field.change && <Badge tone={changeTone[field.change]}>{changeLabel(field.change)}</Badge>}
              </dt>
              <dd className="mt-2 text-sm text-warm-800 break-words"><AuditValue value={field.value} /></dd>
            </div>
          ))}
        </dl>
      )}

      {snapshot.tables.map((table) => (
        <section key={table.key} aria-labelledby={`history-table-${table.key}`}>
          <h3 id={`history-table-${table.key}`} className="text-sm font-extrabold text-warm-900">{table.label}</h3>
          <div className="mt-2 overflow-x-auto rounded-xl border border-warm-200">
            <table className="min-w-full divide-y divide-warm-200 text-left text-sm">
              <thead className="bg-warm-50">
                <tr>
                  {Object.entries(table.columns).map(([key, label]) => (
                    <th key={key} scope="col" className="px-4 py-3 text-xs font-bold uppercase tracking-wider text-warm-500">{label}</th>
                  ))}
                  <th scope="col" className="px-4 py-3 text-xs font-bold uppercase tracking-wider text-warm-500">Change</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-warm-100 bg-white">
                {table.rows.map((row) => (
                  <tr key={row.key}>
                    {Object.keys(table.columns).map((key) => (
                      <td key={key} className="px-4 py-3 text-warm-700 break-words">
                        {row.values[key] ? <AuditValue value={row.values[key]} /> : "Not recorded"}
                      </td>
                    ))}
                    <td className="px-4 py-3">{row.change ? <Badge tone={changeTone[row.change]}>{changeLabel(row.change)}</Badge> : "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ))}
    </div>
  );
}

export function AuditHistoryView({ history }: { history: AuditHistoryDto }) {
  const initialSide = history.after ? "after" : "before";
  const [side, setSide] = useState<"before" | "after">(initialSide);
  const selected = side === "before" ? history.before : history.after;
  const hasToggle = history.before !== null && history.after !== null;

  return (
    <div className="space-y-5">
      <Card padded className="border-brand-green-200 bg-brand-green-50/40">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <div className="flex items-center gap-2 text-brand-green-700">
              <History className="h-5 w-5" aria-hidden="true" />
              <p className="text-xs font-bold uppercase tracking-wider">Read-only historical version</p>
            </div>
            <p className="mt-2 text-sm text-warm-700">This is the record as captured when the audited event occurred.</p>
            <p className="mt-2 text-xs text-warm-500"><AuditTimestamp value={history.version.occurred_at} /></p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Badge tone="zinc">Schema {history.version.schema_version}</Badge>
            <Badge tone="violet">{history.event.action_label}</Badge>
          </div>
        </div>
      </Card>

      <Card padded>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-warm-500">Event</p>
            <p className="mt-1 text-sm font-semibold text-warm-800 break-words">{history.event.summary}</p>
          </div>
          <div>
            <p className="text-xs font-bold uppercase tracking-wider text-warm-500">Actor</p>
            <p className="mt-1 text-sm font-semibold text-warm-800 break-words">{history.event.actor?.name || "System actor"}</p>
          </div>
        </div>
        {history.event.reason && <p className="mt-4 rounded-xl bg-warm-50 p-3 text-sm text-warm-700"><strong>Reason:</strong> {history.event.reason}</p>}
      </Card>

      <Card padded>
        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-2">
            <Eye className="h-5 w-5 text-warm-500" aria-hidden="true" />
            <h2 className="text-sm font-extrabold text-warm-900">Captured record</h2>
          </div>
          {hasToggle && (
            <div className="inline-flex rounded-xl border border-warm-200 bg-warm-50 p-1" aria-label="Historical version side">
              {(["before", "after"] as const).map((value) => (
                <button
                  key={value}
                  type="button"
                  aria-pressed={side === value}
                  onClick={() => setSide(value)}
                  className={`min-h-11 rounded-lg px-4 text-sm font-bold capitalize transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30 ${side === value ? "bg-white text-brand-green-700 shadow-sm" : "text-warm-600 hover:text-warm-900"}`}
                >
                  {value === "before" ? "Before" : "After"}
                </button>
              ))}
            </div>
          )}
        </div>
        {selected ? <Snapshot snapshot={selected} /> : <p className="text-sm text-warm-500">No captured version is available.</p>}
      </Card>

      {history.event.current_record_url && (
        <Link href={history.event.current_record_url} className="inline-flex min-h-11 items-center font-bold text-brand-green-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30">
          View current record
        </Link>
      )}
    </div>
  );
}
