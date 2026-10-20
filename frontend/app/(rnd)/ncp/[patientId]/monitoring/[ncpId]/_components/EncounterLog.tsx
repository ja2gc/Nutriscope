"use client";

import { Eye, Pencil } from "lucide-react";
import { Button } from "@/components/ui/Button";
import type { MonitoringEntry } from "@/services/monitoringService";
import { VISIT_TYPES } from "./LogVisitForm";

interface EncounterLogProps {
  entries: MonitoringEntry[];
  onLogNew: () => void;
  onView: (entry: MonitoringEntry) => void;
}

export default function EncounterLog({ entries, onLogNew, onView }: EncounterLogProps) {
  return (
    <div className="overflow-hidden rounded-2xl border border-warm-200 bg-white shadow-sm">
      <div className="flex flex-col gap-3 border-b border-warm-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <h3 className="text-sm font-extrabold uppercase tracking-wider text-warm-700">Visit History</h3>
        <Button variant="primary" onClick={onLogNew} className="!w-auto"><Pencil className="h-4 w-4" aria-hidden="true" />Log New Visit</Button>
      </div>

      {entries.length === 0 ? (
        <p className="p-10 text-center text-sm font-semibold text-warm-500">No monitoring visits logged yet.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[480px] text-left text-sm">
            <thead className="border-b border-warm-200 bg-warm-50 text-xs font-bold uppercase tracking-wider text-warm-500">
              <tr>{["Date", "Visit type", "Decision", "Actions"].map((label) => <th key={label} scope="col" className="px-5 py-3 last:text-right">{label}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-warm-100">
              {entries.map((entry) => {
                const date = new Date(`${(entry.observed_at ?? entry.created_at).slice(0, 10)}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
                const visitType = VISIT_TYPES.find((type) => type.value === entry.visit_type)?.label ?? "Follow-up";
                const decision = entry.goal_achievement?.continuation_decision ?? "Not recorded";
                return (
                  <tr key={entry.id} onClick={() => onView(entry)} className="cursor-pointer transition-colors hover:bg-warm-50">
                    <td className="whitespace-nowrap px-5 py-3 font-semibold text-warm-800">{date}</td>
                    <td className="px-5 py-3 text-warm-600">{visitType}</td>
                    <td className="px-5 py-3 text-warm-600 first-letter:uppercase">{decision}</td>
                    <td className="px-5 py-3 text-right"><Button variant="icon" className="min-h-11 min-w-11" aria-label={`View visit ${date}`} title="View visit" onClick={(event) => { event.stopPropagation(); onView(entry); }}><Eye className="h-4 w-4" aria-hidden="true" /></Button></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
