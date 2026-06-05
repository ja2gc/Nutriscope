"use client";

import { ALL_MICROS } from "@/lib/nutritionCalculations";
import MicronutrientToggle from "./MicronutrientToggle";
import { AlertTriangle } from "lucide-react";

interface PrescriptionValues {
  energy_kcal: string;
  protein_g: string;
  carbs_g: string;
  fat_g: string;
  fluid_ml: string;
  micronutrient_limits: Record<string, { max?: number; min?: number; unit: string }>;
  displayed_nutrients: string[];
}

interface Props {
  values: PrescriptionValues;
  onChange: (vals: PrescriptionValues) => void;
  onSave: () => void;
  saving: boolean;
  note?: string;
}

const MACROS = [
  { key: "energy_kcal", label: "Energy",        unit: "kcal" },
  { key: "protein_g",   label: "Protein",        unit: "g"   },
  { key: "carbs_g",     label: "Carbohydrates",  unit: "g"   },
  { key: "fat_g",       label: "Fat",            unit: "g"   },
  { key: "fluid_ml",    label: "Fluid",          unit: "mL"  },
] as const;

export default function NutritionPrescriptionForm({ values, onChange, onSave, saving, note }: Props) {
  const setMacro = (key: string, val: string) => onChange({ ...values, [key]: val });
  const setMicros = (keys: string[]) => onChange({ ...values, displayed_nutrients: keys });
  const setMicroLimit = (key: string, field: "max" | "min", val: string) => {
    const micro = ALL_MICROS.find((m) => m.key === key);
    onChange({
      ...values,
      micronutrient_limits: {
        ...values.micronutrient_limits,
        [key]: { ...values.micronutrient_limits[key], [field]: val ? parseFloat(val) : undefined, unit: micro?.unit ?? "" },
      },
    });
  };

  return (
    <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-5">
      <div className="flex items-center justify-between">
        <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Nutrition Prescription</h3>
        <MicronutrientToggle selected={values.displayed_nutrients} onChange={setMicros} />
      </div>

      {note && (
        <div className="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-xl">
          <AlertTriangle className="h-3.5 w-3.5 text-amber-600 flex-shrink-0 mt-0.5" />
          <p className="text-[10px] text-amber-800 leading-relaxed">{note}</p>
        </div>
      )}

      {/* Macro inputs */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        {MACROS.map(({ key, label, unit }) => (
          <div key={key}>
            <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-1">{label}</label>
            <div className="flex items-center border border-zinc-200 rounded-lg overflow-hidden focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/20">
              <input
                type="number" min="0" step="0.1"
                value={(values as Record<string, string>)[key] ?? ""}
                onChange={(e) => setMacro(key, e.target.value)}
                className="w-full px-2.5 py-2 text-sm font-mono text-zinc-900 bg-transparent focus:outline-none"
              />
              <span className="px-2 text-[9px] text-zinc-400 font-bold bg-zinc-50 border-l border-zinc-200 whitespace-nowrap">{unit}</span>
            </div>
          </div>
        ))}
      </div>

      {/* Micronutrient limit rows */}
      {values.displayed_nutrients.length > 0 && (
        <div className="space-y-2 pt-2 border-t border-zinc-100">
          <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest">Micronutrient Limits</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {values.displayed_nutrients.map((key) => {
              const micro  = ALL_MICROS.find((m) => m.key === key);
              const limits = values.micronutrient_limits[key] ?? {};
              return (
                <div key={key} className="flex items-center gap-2 p-2.5 bg-zinc-50 border border-zinc-200 rounded-xl">
                  <span className="text-[10px] font-semibold text-zinc-700 w-24 flex-shrink-0">{micro?.label ?? key}</span>
                  <div className="flex items-center gap-1 flex-1">
                    <span className="text-[9px] text-zinc-400">max</span>
                    <input type="number" min="0" step="0.1"
                      value={limits.max ?? ""}
                      onChange={(e) => setMicroLimit(key, "max", e.target.value)}
                      className="w-16 px-2 py-1 text-xs font-mono border border-zinc-200 rounded-lg focus:outline-none focus:border-emerald-500"
                      placeholder="—"
                    />
                    <span className="text-[9px] text-zinc-400">{micro?.unit}</span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      <div className="flex justify-end pt-2">
        <button
          onClick={onSave}
          disabled={saving}
          className="px-4 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors cursor-pointer disabled:opacity-50">
          {saving ? "Saving…" : "Save Prescription"}
        </button>
      </div>
    </div>
  );
}
