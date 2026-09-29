"use client";

import { useState } from "react";

import { Button } from "@/components/ui/Button";
import type { MonitoringEntry } from "@/services/monitoringService";

import MonitoringVisitDetails from "./MonitoringVisitDetails";

interface EncounterLogProps {
  entries: MonitoringEntry[];
  onLogNew: () => void;
  onDelete: (id: string) => void;
}

export default function EncounterLog({ entries, onLogNew, onDelete }: EncounterLogProps) {
  const [expandedId, setExpandedId] = useState<string | null>(null);

  return (
    <div className="overflow-hidden rounded-2xl border border-warm-200 bg-white shadow-sm">
      <div className="flex flex-col gap-3 border-b border-warm-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <h3 className="text-sm font-extrabold uppercase tracking-wider text-warm-700">Visit History</h3>
        <Button variant="primary" onClick={onLogNew} className="!w-auto">Log New Visit</Button>
      </div>

      {entries.length === 0 ? (
        <p className="p-10 text-center text-sm font-semibold text-warm-500">No monitoring visits logged yet.</p>
      ) : (
        <div className="divide-y divide-warm-100">
          {entries.map((entry) => {
            const isExpanded = expandedId === entry.id;
            return (
              <article key={entry.id}>
                <button
                  type="button"
                  aria-expanded={isExpanded}
                  onClick={() => setExpandedId(isExpanded ? null : entry.id)}
                  className="w-full px-4 py-4 text-left transition-colors hover:bg-warm-50 sm:px-5"
                >
                  <MonitoringVisitDetails entry={entry} mode="summary" />
                  <span className="mt-3 block text-xs font-bold text-warm-500">{isExpanded ? "Hide details" : "Show details"}</span>
                </button>

                {isExpanded && (
                  <div className="space-y-4 border-t border-warm-100 px-4 py-4 sm:px-5">
                    <MonitoringVisitDetails entry={entry} mode="full" />
                    <div className="border-t border-warm-100 pt-4">
                      <Button variant="danger" onClick={() => onDelete(entry.id)} className="!w-auto">Delete Entry</Button>
                    </div>
                  </div>
                )}
              </article>
            );
          })}
        </div>
      )}
    </div>
  );
}
