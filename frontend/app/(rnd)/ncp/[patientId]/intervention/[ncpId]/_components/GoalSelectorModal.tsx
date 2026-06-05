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

export interface GoalOption {
  value: string;
  label: string;
  description: string;
  stages?: { value: string; label: string }[];
}

export const GOALS: GoalOption[] = [
  {
    value: "renal_diet",
    label: "Renal Diet",
    description: "CKD — restricts protein, sodium, potassium, phosphorus",
    stages: [
      { value: "stage_1", label: "Stage 1 (GFR ≥90)" },
      { value: "stage_2", label: "Stage 2 (GFR 60–89)" },
      { value: "stage_3", label: "Stage 3 (GFR 30–59)" },
      { value: "stage_4", label: "Stage 4 (GFR 15–29)" },
      { value: "stage_5_predialysis", label: "Stage 5 Pre-dialysis" },
      { value: "hemodialysis", label: "Hemodialysis" },
      { value: "peritoneal", label: "Peritoneal Dialysis" },
    ],
  },
  {
    value: "diabetic_control",
    label: "Diabetic Control",
    description: "DM — carbohydrate distribution, glycemic management",
  },
  {
    value: "cardiac_diet",
    label: "Cardiac Diet",
    description: "HTN / cardiac — sodium, fat, cholesterol restriction",
    stages: [
      { value: "mild", label: "Mild" },
      { value: "moderate", label: "Moderate" },
      { value: "severe", label: "Severe" },
    ],
  },
  {
    value: "weight_loss",
    label: "Weight Loss",
    description: "Caloric deficit, protein-sparing approach",
    stages: [
      { value: "overweight", label: "Overweight (BMI 25–29.9)" },
      { value: "class_1", label: "Obese Class I (BMI 30–34.9)" },
      { value: "class_2", label: "Obese Class II (BMI 35–39.9)" },
      { value: "class_3", label: "Obese Class III (BMI ≥40)" },
    ],
  },
  {
    value: "weight_gain",
    label: "Weight Gain",
    description: "Caloric surplus; refeeding protocol for severe cases",
    stages: [
      { value: "mild", label: "Mild (85–90% IBW)" },
      { value: "moderate", label: "Moderate (70–84% IBW)" },
      { value: "severe", label: "Severe (<70% IBW) — Refeeding protocol" },
    ],
  },
  {
    value: "high_protein",
    label: "High Protein",
    description: "Post-surgery, burns, pressure injuries, low albumin",
    stages: [
      { value: "mild_stress", label: "Mild Stress (1.0–1.2 g/kg)" },
      { value: "moderate_stress", label: "Moderate Stress (1.2–1.5 g/kg)" },
      { value: "severe_stress", label: "Severe Stress (1.5–2.0 g/kg)" },
      { value: "burns", label: "Burns >20% BSA (1.5–2.0 g/kg)" },
    ],
  },
  {
    value: "fluid_restriction",
    label: "Fluid Restriction",
    description: "CKD dialysis, heart failure, SIADH",
    stages: [
      { value: "ckd_predialysis", label: "CKD Pre-dialysis" },
      { value: "ckd_hemodialysis", label: "CKD Hemodialysis" },
      { value: "ckd_peritoneal", label: "CKD Peritoneal" },
      { value: "heart_failure_mild", label: "Heart Failure — Mild (≤2000 mL)" },
      { value: "heart_failure_severe", label: "Heart Failure — Severe (≤1500 mL)" },
      { value: "siadh", label: "SIADH (500–1000 mL)" },
    ],
  },
  {
    value: "liver_disease",
    label: "Liver Disease",
    description: "Cirrhosis stages, hepatic encephalopathy",
    stages: [
      { value: "compensated", label: "Compensated (no ascites)" },
      { value: "decompensated", label: "Decompensated (ascites)" },
      { value: "encephalopathy_grade_1_2", label: "Encephalopathy Grade I–II" },
      { value: "encephalopathy_grade_3_4", label: "Encephalopathy Grade III–IV" },
    ],
  },
  {
    value: "malnutrition",
    label: "Malnutrition",
    description: "High-calorie high-protein; refeeding for severe cases",
    stages: [
      { value: "moderate", label: "Moderate (risk score 2–3)" },
      { value: "severe", label: "Severe (risk score >3) — Refeeding protocol" },
    ],
  },
  {
    value: "custom",
    label: "Custom Plan",
    description: "Manual nutrient targets set by RND",
  },
];

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
        <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
          <h2 className="text-sm font-extrabold text-zinc-900 uppercase tracking-wider">Set Intervention Goal</h2>
          <button onClick={onClose} className="text-zinc-400 hover:text-zinc-700 cursor-pointer transition-colors">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="overflow-y-auto flex-1 p-6 space-y-4">
          <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">Select a goal</p>
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
                      : "border-zinc-200 hover:border-emerald-300 bg-white"
                  }`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <span className={`text-xs font-bold ${isSelected ? "text-emerald-800" : "text-zinc-800"}`}>
                      {g.label}
                    </span>
                    {isSelected && <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 flex-shrink-0 mt-0.5" />}
                  </div>
                  <p className="text-[10px] text-zinc-400 mt-0.5 leading-relaxed">{g.description}</p>
                </button>
              );
            })}
          </div>

          {/* Stage selector — progressive disclosure */}
          {goal?.stages && (
            <div className="pt-2 transition-all duration-150">
              <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mb-2">
                Disease Stage / Severity
              </p>
              <Select value={stage} onValueChange={setStage}>
                <SelectTrigger className="w-full text-sm border-zinc-200 focus:ring-emerald-500/20">
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
        <div className="flex items-center justify-end gap-2 px-6 py-4 border-t border-zinc-100">
          <button onClick={onClose}
            className="px-4 py-2 text-xs font-bold text-zinc-500 hover:text-zinc-700 cursor-pointer transition-colors">
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
