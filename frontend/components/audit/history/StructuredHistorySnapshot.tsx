import { AuditValue } from "@/components/audit/AuditValue";
import { Badge } from "@/components/ui/Badge";
import type { AuditHistoryChange, AuditHistorySnapshotDto } from "@/types/auditHistory";

const changeTone: Record<AuditHistoryChange, "emerald" | "amber" | "red"> = {
  added: "emerald",
  changed: "amber",
  removed: "red",
};

function changeLabel(change: AuditHistoryChange) {
  return change.charAt(0).toUpperCase() + change.slice(1);
}

export function StructuredHistorySnapshot({ snapshot }: { snapshot: AuditHistorySnapshotDto }) {
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
