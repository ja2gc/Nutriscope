"use client";

import { ALL_MICROS, microKeys } from "@/lib/nutritionCalculations";
import MicronutrientToggle from "./MicronutrientToggle";
import NumericInput from "@/components/ui/NumericInput";
import { AlertTriangle, X, Lock, FlaskConical } from "lucide-react";

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
  /** Micros required by the active intervention goal — rendered locked (can't be removed). */
  requiredMicros?: string[];
  /** Label of the active goal, for the "required by …" tooltip. */
  goalLabel?: string;
}

const MACROS = [
  { key: "energy_kcal", label: "Energy",        unit: "kcal" },
  { key: "protein_g",   label: "Protein",        unit: "g"   },
  { key: "carbs_g",     label: "Carbohydrates",  unit: "g"   },
  { key: "fat_g",       label: "Fat",            unit: "g"   },
  { key: "fluid_ml",    label: "Fluid",          unit: "mL"  },
] as const;

export default function NutritionPrescriptionForm({
  values, onChange, onSave, saving, note, requiredMicros = [], goalLabel,
}: Props) {
  const setMacro = (key: string, val: string) => onChange({ ...values, [key]: val });
  const setMicros = (keys: string[]) => onChange({ ...values, displayed_nutrients: keys });
  const removeMicro = (key: string) => {
    const { [key]: _removed, ...rest } = values.micronutrient_limits;
    void _removed;
    onChange({
      ...values,
      displayed_nutrients: values.displayed_nutrients.filter((k) => k !== key),
      micronutrient_limits: rest,
    });
  };
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

  // Only real micronutrient keys belong in the limits list — strip any macro keys
  // that leaked into displayed_nutrients (legacy/seed data stored macros here).
  const microList = microKeys(Array.from(new Set([...requiredMicros, ...values.displayed_nutrients])));

  return (
    <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm space-y-5">
      <div className="flex items-center justify-between">
        <h3 className="text-xs font-extrabold text-warm-700 uppercase tracking-wider">Nutrition Prescription</h3>
        <MicronutrientToggle selected={microList} onChange={setMicros} required={requiredMicros} />
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
          <NumericInput
            key={key}
            label={label}
            value={(values as unknown as Record<string, string>)[key] ?? ""}
            onChange={(val) => setMacro(key, val)}
            unit={unit}
          />
        ))}
      </div>

      {/* Micronutrient limit rows */}
      {microList.length > 0 ? (
        <div className="space-y-2 pt-2 border-t border-warm-100">
          <p className="text-[9px] font-bold text-warm-400 uppercase tracking-widest">Micronutrient Limits</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {microList.map((key) => {
              const micro    = ALL_MICROS.find((m) => m.key === key);
              const limits   = values.micronutrient_limits[key] ?? {};
              const required = requiredMicros.includes(key);
              return (
                <div key={key} className="flex items-center gap-2 p-2.5 bg-warm-50 border border-warm-200 rounded-xl">
                  <span className="text-[10px] font-semibold text-warm-700 w-24 flex-shrink-0 flex items-center gap-1">
                    {micro?.label ?? key}
                  </span>
                  <div className="flex items-center gap-1 flex-1">
                    <span className="text-[9px] text-warm-400">max</span>
                    <input type="number" min="0" step="0.1"
                      value={limits.max ?? ""}
                      onChange={(e) => setMicroLimit(key, "max", e.target.value)}
                      className="w-16 px-2 py-1 text-xs font-mono border border-warm-200 rounded-lg focus:outline-none focus:border-emerald-500"
                      placeholder="—"
                    />
                    <span className="text-[9px] text-warm-400">{micro?.unit}</span>
                  </div>
                  {required ? (
                    <span className="p-1 text-warm-300 cursor-not-allowed" title={`Required by the ${goalLabel ?? "intervention"} goal.`}>
                      <Lock className="h-3 w-3" />
                    </span>
                  ) : (
                    <button type="button" onClick={() => removeMicro(key)} title="Remove micronutrient"
                      className="p-1 text-warm-300 hover:text-red-500 transition-colors cursor-pointer">
                      <X className="h-3 w-3" />
                    </button>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      ) : (
        <div className="flex items-start gap-2 p-3 bg-warm-50 border border-warm-200 border-dashed rounded-xl">
          <FlaskConical className="h-3.5 w-3.5 text-warm-400 flex-shrink-0 mt-0.5" />
          <p className="text-[10px] text-warm-500 leading-relaxed">
            No micronutrients selected. Use <span className="font-semibold text-warm-700">Display Micros</span> above to add
            goal-relevant micronutrients (e.g. potassium &amp; phosphorus for renal) — they&apos;ll show limits here and track in the meal plan.
          </p>
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
