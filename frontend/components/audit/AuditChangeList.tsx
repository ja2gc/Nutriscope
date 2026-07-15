import type { AuditChangeDto } from "@/types/audit";
import { AuditValue } from "./AuditValue";

export function AuditChangeList({
  changes,
  clinical,
}: {
  changes: AuditChangeDto[];
  clinical: boolean;
}) {
  if (changes.length === 0) {
    return <p className="text-sm text-warm-500">No field changes recorded.</p>;
  }

  return (
    <ul className="space-y-2">
      {changes.map((change) => {
        const hidden = clinical || change.redacted;
        return (
          <li key={change.field} className="rounded-xl border border-warm-200 bg-warm-50 p-3">
            <p className="text-sm font-semibold text-warm-800 break-words">{change.label}</p>
            {hidden ? (
              <p className="mt-1 text-sm text-warm-600">Value hidden; field changed</p>
            ) : (
              <dl className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div>
                  <dt className="text-xs font-bold uppercase tracking-wider text-warm-500">Before</dt>
                  <dd aria-label={`${change.label} before value`} className="mt-0.5 text-sm text-warm-700 break-words">
                    <AuditValue value={change.before} />
                  </dd>
                </div>
                <div>
                  <dt className="text-xs font-bold uppercase tracking-wider text-warm-500">After</dt>
                  <dd aria-label={`${change.label} after value`} className="mt-0.5 text-sm text-warm-700 break-words">
                    <AuditValue value={change.after} />
                  </dd>
                </div>
              </dl>
            )}
          </li>
        );
      })}
    </ul>
  );
}
