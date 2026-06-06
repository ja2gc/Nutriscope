"use client";

import { useState } from "react";
import { ChevronDown, ChevronUp, ClipboardList, Plus } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { MonitoringEntry } from "@/services/monitoringService";

interface EncounterLogProps {
  entries: MonitoringEntry[];
  onLogNew: () => void;
  onDelete: (id: number) => void;
}

const COMPLIANCE_BADGE: Record<string, { label: string; cls: string }> = {
  compliant:     { label: 'Compliant',     cls: 'bg-emerald-50 text-emerald-700 border border-emerald-200' },
  partial:       { label: 'Partial',       cls: 'bg-amber-50 text-amber-700 border border-amber-200' },
  non_compliant: { label: 'Non-compliant', cls: 'bg-rose-50 text-rose-700 border border-rose-200' },
};

const DECISION_BADGE: Record<string, { label: string; cls: string }> = {
  continue:    { label: 'Continue',    cls: 'bg-emerald-100 text-emerald-800' },
  modify:      { label: 'Modify',      cls: 'bg-amber-100 text-amber-800' },
  discontinue: { label: 'Discontinue', cls: 'bg-zinc-100 text-zinc-600' },
};

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-PH', {
    month: 'short', day: 'numeric', year: 'numeric',
  });
}

export default function EncounterLog({ entries, onLogNew, onDelete }: EncounterLogProps) {
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const sorted = [...entries].sort(
    (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
  );

  return (
    <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between px-5 py-4 border-b border-zinc-100">
        <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider flex items-center gap-2">
          <ClipboardList className="h-4 w-4 text-emerald-600" />
          Visit History
        </h3>
        <Button variant="primary" onClick={onLogNew} className="!w-auto">
          <Plus className="h-3.5 w-3.5 mr-1" />
          Log New Visit
        </Button>
      </div>

      {sorted.length === 0 ? (
        <div className="p-10 text-center">
          <div className="p-3 bg-zinc-50 border border-dashed border-zinc-300 rounded-xl w-fit mx-auto mb-3">
            <ClipboardList className="h-6 w-6 text-zinc-400" />
          </div>
          <p className="text-xs font-semibold text-zinc-500">No monitoring visits logged yet.</p>
          <p className="text-[10px] text-zinc-400 mt-1">Log the first visit to start tracking progress.</p>
        </div>
      ) : (
        <>
          {/* Scrollable table area */}
          <div className="overflow-x-auto">
            <div className="min-w-[480px]">
              {/* Column headers */}
              <div className="grid grid-cols-[1fr_80px_120px_100px_32px] gap-2 px-5 py-2 bg-zinc-50 border-b border-zinc-100">
                {['Date', 'Weight', 'Compliance', 'Decision', ''].map((h, i) => (
                  <span key={i} className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest">{h}</span>
                ))}
              </div>

              <div className="divide-y divide-zinc-100">
                {sorted.map((entry) => {
                  const compliance = entry.goal_achievement?.compliance ?? null;
                  const decision = entry.goal_achievement?.continuation_decision ?? null;
                  const isExpanded = expandedId === entry.id;

                  return (
                    <div key={entry.id}>
                      <button
                        onClick={() => setExpandedId(isExpanded ? null : entry.id)}
                        className="w-full grid grid-cols-[1fr_80px_120px_100px_32px] gap-2 items-center px-5 py-3 hover:bg-zinc-50 transition-colors text-left"
                      >
                        {/* Date */}
                        <span className="text-xs font-semibold text-zinc-800 truncate">
                          {formatDate(entry.created_at)}
                        </span>

                        {/* Weight */}
                        <span className="text-xs text-zinc-500">
                          {entry.weight
                            ? <><span className="font-mono font-bold text-zinc-900">{entry.weight}</span> kg</>
                            : <span className="text-zinc-300">—</span>
                          }
                        </span>

                        {/* Compliance badge */}
                        <span>
                          {compliance && COMPLIANCE_BADGE[compliance] ? (
                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold ${COMPLIANCE_BADGE[compliance].cls}`}>
                              {COMPLIANCE_BADGE[compliance].label}
                            </span>
                          ) : (
                            <span className="text-zinc-300 text-[10px]">—</span>
                          )}
                        </span>

                        {/* Decision chip */}
                        <span>
                          {decision && DECISION_BADGE[decision] ? (
                            <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold ${DECISION_BADGE[decision].cls}`}>
                              {DECISION_BADGE[decision].label}
                            </span>
                          ) : (
                            <span className="text-zinc-300 text-[10px]">—</span>
                          )}
                        </span>

                        {/* Expand icon */}
                        <span className="text-zinc-400 flex justify-end">
                          {isExpanded
                            ? <ChevronUp className="h-3.5 w-3.5" />
                            : <ChevronDown className="h-3.5 w-3.5" />
                          }
                        </span>
                      </button>

                      {/* Expanded detail */}
                      {isExpanded && (
                        <div className="px-5 pb-4 pt-3 bg-zinc-50 border-t border-zinc-100 space-y-3">
                          {/* Lab values */}
                          {entry.lab_values && Object.keys(entry.lab_values).length > 0 && (
                            <div>
                              <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-2">Lab Values</p>
                              <div className="flex flex-wrap gap-2">
                                {Object.entries(entry.lab_values)
                                  .filter(([, v]) => v !== null && v !== undefined)
                                  .map(([key, val]) => (
                                    <div key={key} className="bg-white border border-zinc-200 rounded-lg px-3 py-2 min-w-[72px]">
                                      <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest">{key}</p>
                                      <p className="text-xs font-mono font-bold text-zinc-900 mt-0.5">{String(val)}</p>
                                    </div>
                                  ))}
                              </div>
                            </div>
                          )}

                          {/* GI tolerance */}
                          {entry.goal_achievement?.gi_tolerance && (
                            <div>
                              <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-1">GI Tolerance</p>
                              <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                                entry.goal_achievement.gi_tolerance === 'tolerating'
                                  ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                  : 'bg-rose-50 text-rose-700 border border-rose-200'
                              }`}>
                                {entry.goal_achievement.gi_tolerance === 'tolerating' ? 'Tolerating' : 'Not Tolerating'}
                              </span>
                            </div>
                          )}

                          {/* Clinical notes */}
                          {entry.clinical_summary && (
                            <div>
                              <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-1">Clinical Notes</p>
                              <p className="text-xs text-zinc-700 leading-relaxed">{entry.clinical_summary}</p>
                            </div>
                          )}

                          {/* Next date */}
                          {entry.next_monitoring_date && (
                            <p className="text-[10px] text-zinc-400">
                              Next follow-up:{' '}
                              <span className="font-semibold text-zinc-600">
                                {formatDate(entry.next_monitoring_date)}
                              </span>
                            </p>
                          )}

                          {/* Delete */}
                          <div className="pt-1">
                            <Button
                              variant="danger"
                              onClick={() => onDelete(entry.id)}
                              className="!w-auto"
                            >
                              Delete Entry
                            </Button>
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
