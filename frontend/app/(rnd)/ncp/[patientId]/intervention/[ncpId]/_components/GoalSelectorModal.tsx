"use client";

import { useState } from "react";
import { X, CheckCircle2 } from "lucide-react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Button } from "@/components/ui/Button";

// GOALS data lives in a pure, testable module. Re-exported here so existing
// imports (`import GoalSelectorModal, { GOALS } from ".../GoalSelectorModal"`) keep working.
export { GOALS } from "./goals";
export type { GoalOption } from "./goals";
import { GOALS } from "./goals";

interface Props {
  onConfirm: (goalType: string, stage: string | null) => void;
  onClose: () => void;
  initialGoal?: string | null;
  initialStage?: string | null;
}

export default function GoalSelectorModal({ onConfirm, onClose, initialGoal, initialStage }: Props) {
  const [selected, setSelected] = useState<string>(initialGoal ?? "");
  const [stage, setStage]       = useState<string>(initialStage ?? "");

  const goal = GOALS.find((g) => g.value === selected);

  const handleConfirm = () => {
    if (!selected) return;
    onConfirm(selected, goal?.stages ? stage || null : null);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 flex flex-col max-h-[85vh]">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-warm-100">
          <h2 className="text-sm font-extrabold text-warm-900 uppercase tracking-wider">Set Intervention Goal</h2>
          <button onClick={onClose} className="text-warm-400 hover:text-warm-700 cursor-pointer transition-colors">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="overflow-y-auto flex-1 p-6 space-y-4">
          <p className="text-[10px] font-bold text-warm-400 uppercase tracking-widest">Select a goal</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
            {GOALS.map((g) => {
              const isSelected = selected === g.value;
              return (
                <button
                  key={g.value}
                  onClick={() => { setSelected(g.value); setStage(""); }}
                  className={`text-left p-3.5 rounded-xl border transition-all cursor-pointer ${
                    isSelected
                      ? "border-emerald-600 bg-emerald-50 ring-2 ring-emerald-500/20"
                      : "border-warm-200 hover:border-emerald-300 bg-white"
                  }`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <span className={`text-xs font-bold ${isSelected ? "text-emerald-800" : "text-warm-800"}`}>
                      {g.label}
                    </span>
                    {isSelected && <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 flex-shrink-0 mt-0.5" />}
                  </div>
                  <p className="text-[10px] text-warm-400 mt-0.5 leading-relaxed">{g.description}</p>
                </button>
              );
            })}
          </div>

          {/* Stage selector — progressive disclosure */}
          {goal?.stages && (
            <div className="pt-2 transition-all duration-150">
              <p className="text-[10px] font-bold text-warm-400 uppercase tracking-widest mb-2">
                Disease Stage / Severity
              </p>
              <Select value={stage} onValueChange={setStage}>
                <SelectTrigger className="w-full text-sm border-warm-200 focus:ring-emerald-500/20">
                  <SelectValue placeholder="Select stage…" />
                </SelectTrigger>
                <SelectContent>
                  {goal.stages.map((s) => (
                    <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-2 px-6 py-4 border-t border-warm-100">
          <button onClick={onClose}
            className="px-4 py-2 text-xs font-bold text-warm-500 hover:text-warm-700 cursor-pointer transition-colors">
            Cancel
          </button>
          <Button
            variant="primary"
            onClick={handleConfirm}
            disabled={!selected || (!!goal?.stages && !stage)}
            className="w-auto px-5 py-2 text-xs"
          >
            Apply Goal
          </Button>
        </div>
      </div>
    </div>
  );
}
