"use client";

import { ALL_MICROS, microKeys } from "@/lib/nutritionCalculations";
import MicronutrientToggle from "./MicronutrientToggle";
import NumericInput from "@/components/ui/NumericInput";
import { AlertTriangle, X } from "lucide-react";
import { InfoHint } from "@/components/ui/InfoHint";
import { Fragment, useState } from "react";
import type { CalculationTrace } from "@/lib/prescriptionCalculationTrace";
import PrescriptionCalculationPanel from "./PrescriptionCalculationPanel";
import { Button } from "@/components/ui/Button";
import type { PrescriptionFormState } from "@/lib/interventionGoalState";

type PrescriptionValues = PrescriptionFormState;

interface Props {
  goalType?: string | null;
  isPediatric?: boolean;
  hasMaternalStatus?: boolean;
  onCalculate?: () => void;
  calculationDirty?: boolean;
  values: PrescriptionValues;
  onChange: (vals: PrescriptionValues) => void;
  onSave: () => void;
  saving: boolean;
  note?: string;
  /** Goal defaults are selected during generation, not locked during editing. */
  requiredMicros?: string[];
  /** Label of the active goal, for the "required by …" tooltip. */
  goalLabel?: string;
  calculationTrace?: CalculationTrace | null;
  readOnly?: boolean;
  showSave?: boolean;
  defaultWeightTarget?: number | null;
}

const MACROS = [
  { key: "energy_kcal", label: "Energy",        unit: "kcal" },
  { key: "protein_g",   label: "Protein",        unit: "g"   },
  { key: "carbs_g",     label: "Carbohydrates",  unit: "g"   },
  { key: "fat_g",       label: "Fat",            unit: "g"   },
  { key: "fluid_ml",    label: "Fluid",          unit: "mL"  },
] as const;

export default function NutritionPrescriptionForm({
  values, onChange, onSave, saving, note, calculationTrace,
  readOnly = false, showSave = true, defaultWeightTarget,
  goalType, isPediatric = false, hasMaternalStatus = false, onCalculate, calculationDirty = false,
}: Props) {
  const [showCalculations, setShowCalculations] = useState(false);
  const weight = values.weight_target ?? (defaultWeightTarget != null ? { target: defaultWeightTarget } : {});
  const weightRange = weight.min != null || weight.max != null;
  const weightInput = (field: 'target' | 'min' | 'max', label: string) => <input type="text" inputMode="decimal" maxLength={5}
    aria-label={label} value={weight[field] ?? ''} disabled={readOnly}
    onChange={event => { if (/^\d{0,3}(?:\.\d?)?$/.test(event.target.value)) onChange({ ...values, weight_target: { ...weight, [field]: event.target.value } }); }}
    className="min-w-0 w-24 rounded-lg border border-warm-200 px-2.5 py-2 font-mono text-sm disabled:bg-warm-50" />;
  const setMacro = (key: string, val: string) => onChange({ ...values, [key]: val });
  const setOption = (key: string, value: number | string | boolean | undefined) => onChange({
    ...values, calculation_options: { ...values.calculation_options, [key]: value },
  });
  const setMicros = (keys: string[]) => onChange({ ...values, displayed_nutrients: keys,
    micronutrient_limits: Object.fromEntries(Object.entries(values.micronutrient_limits).filter(([key]) => keys.includes(key))),
  });
  const boundSelect = (label: string, value: string, change: (value: string) => void, locked = false) => (
    <select aria-label={`${label} bound`} value={value} onChange={event => change(event.target.value)} disabled={readOnly || locked}
      className="w-12 shrink-0 bg-transparent py-2 pl-1 text-sm text-warm-700 disabled:opacity-70">
      <option value="" label=" ">Target (blank)</option><option value="max">≤</option><option value="min">≥</option>
      {value === 'range' && <option value="range">Range</option>}
    </select>
  );
  const setMacroBound = (key: string, value: string) => {
    const operators = { ...values.nutrient_operators };
    if (value === 'min' || value === 'max') operators[key] = value;
    else delete operators[key];
    onChange({ ...values, nutrient_operators: operators });
  };
  const setMicroBound = (key: string, value: string) => {
    const previous = values.micronutrient_limits[key] ?? { unit: ALL_MICROS.find(micro => micro.key === key)?.unit ?? '' };
    const amount = value === 'max' ? previous.max ?? previous.target ?? previous.min : previous.min ?? previous.target ?? previous.max;
    onChange({ ...values, micronutrient_limits: { ...values.micronutrient_limits,
      [key]: { [value || 'target']: amount, unit: previous.unit },
    } });
  };
  const removeMicro = (key: string) => {
    const { [key]: _removed, ...rest } = values.micronutrient_limits;
    void _removed;
    onChange({
      ...values,
      displayed_nutrients: values.displayed_nutrients.filter((k) => k !== key),
      micronutrient_limits: rest,
    });
  };
  const setMicroLimit = (key: string, field: "max" | "min" | "target", val: string) => {
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
  const microList = microKeys(values.displayed_nutrients);

  return (
    <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-1.5 min-w-0">
          <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Nutrition Prescription</h3>
          <InfoHint label="About prescription bounds" title="Targets and limits">Leave the selector blank for a target to aim near. ≤ means a maximum: lower amounts are acceptable. ≥ means a minimum to reach. A range keeps both bounds.</InfoHint>
        </div>
        {!readOnly && <MicronutrientToggle selected={microList} onChange={setMicros} />}
      </div>

      {note && (
        <div className="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-xl">
          <AlertTriangle className="h-3.5 w-3.5 text-amber-600 flex-shrink-0 mt-0.5" />
          <p className="text-xs text-amber-800 leading-relaxed">{note}</p>
        </div>
      )}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
          <label htmlFor="energy-adjustment" className="mb-1 block text-xs font-bold uppercase tracking-widest text-warm-400">Energy adjustment</label>
          <select id="energy-adjustment" value={values.energy_adjustment_mode ?? "none"} disabled={readOnly || goalType === "custom"}
            onChange={event => onChange({ ...values, energy_adjustment_mode: event.target.value as "none" | "surplus" | "deficit",
              energy_adjustment_kcal: event.target.value === "none" ? "" : values.energy_adjustment_kcal ?? "" })}
            className="w-full rounded-lg border border-warm-200 bg-white px-2.5 py-2 text-base">
            <option value="none">No Adjustment</option><option value="surplus">Add Surplus</option><option value="deficit">Add Deficit</option>
          </select>
        </div>
        {values.energy_adjustment_mode && values.energy_adjustment_mode !== "none" && <NumericInput
          label="Adjustment amount" value={values.energy_adjustment_kcal ?? ""} unit="kcal/day" step={1} min={1} max={10000}
          disabled={readOnly} onChange={value => onChange({ ...values, energy_adjustment_kcal: value })} />}
        <div>
          <label htmlFor="fluid-mode" className="mb-1 block text-xs font-bold uppercase tracking-widest text-warm-400">Fluid</label>
          <select id="fluid-mode" value={values.fluid_mode ?? ""} disabled={readOnly}
            onChange={event => {
              const restricted = event.target.value === "restricted";
              const operators = { ...values.nutrient_operators };
              if (restricted) operators.fluid_ml = "max"; else delete operators.fluid_ml;
              onChange({ ...values, fluid_mode: restricted ? "restricted" : "estimated", nutrient_operators: operators });
            }}
            className="w-full rounded-lg border border-warm-200 bg-white px-2.5 py-2 text-base">
            {!values.fluid_mode && <option value="">Legacy value — meaning not recorded</option>}
            <option value="estimated">Estimated Fluid Needs</option><option value="restricted">Prescribed Fluid Limit</option>
          </select>
        </div>
      </div>

      {goalType === "liver_disease" && !isPediatric && <div>
        <label htmlFor="nutrition-status" className="mb-1 block text-xs font-bold uppercase tracking-widest text-warm-400">Assessed nutritional status</label>
        <select id="nutrition-status" value={values.calculation_options?.nutrition_status ?? "unconfirmed"} disabled={readOnly}
          onChange={event => setOption("nutrition_status", event.target.value)}
          className="w-full rounded-lg border border-warm-200 bg-white px-2.5 py-2 text-base">
          <option value="unconfirmed">Confirm nutritional status</option><option value="adequate">No identified malnutrition or muscle loss</option>
          <option value="malnutrition_or_muscle_loss">Malnutrition or muscle loss</option>
        </select>
      </div>}

      {!readOnly && <details className="rounded-lg border border-warm-200 p-3">
        <summary className="cursor-pointer text-sm font-bold text-warm-700">Clinical inputs and notes</summary>
        <div className="mt-3 space-y-3">
          {!isPediatric && <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {["renal_diet", "high_protein", "liver_disease"].includes(goalType ?? "") && <NumericInput label="Energy coefficient"
              value={values.calculation_options?.energy_per_kg?.toString() ?? ""} unit="kcal/kg/day" min={1} max={100}
              onChange={value => setOption("energy_per_kg", value ? Number(value) : undefined)} />}
            <NumericInput label="Protein coefficient" value={values.calculation_options?.protein_per_kg?.toString() ?? ""}
              unit="g/kg/day" min={0.1} max={5} onChange={value => setOption("protein_per_kg", value ? Number(value) : undefined)} />
            <NumericInput label="Clinical calculation weight" value={values.calculation_options?.clinical_weight_kg?.toString() ?? ""}
              unit="kg" min={1} max={400} onChange={value => onChange({ ...values, calculation_options: {
                ...values.calculation_options, clinical_weight_kg: value ? Number(value) : undefined,
                weight_basis: value ? "clinician_selected" : undefined,
              } })} />
            <NumericInput label="Protein calculation weight" value={values.calculation_options?.protein_weight_kg?.toString() ?? ""}
              unit="kg" min={1} max={400} onChange={value => setOption("protein_weight_kg", value ? Number(value) : undefined)} />
          </div>}
          <label className="flex items-start gap-2 text-sm text-warm-700">
            <input type="checkbox" checked={values.calculation_options?.refeeding_risk ?? false} className="mt-1"
              onChange={event => setOption("refeeding_risk", event.target.checked)} />
            Include assessed refeeding-risk guidance (notes only)
          </label>
          {goalType !== "liver_disease" && <label className="flex items-start gap-2 text-sm text-warm-700">
            <input type="checkbox" checked={values.calculation_options?.nutrition_status === "malnutrition_or_muscle_loss"} className="mt-1"
              onChange={event => setOption("nutrition_status", event.target.checked ? "malnutrition_or_muscle_loss" : "unconfirmed")} />
            Include assessed malnutrition guidance
          </label>}
          {!isPediatric && hasMaternalStatus && ["renal_diet", "high_protein", "liver_disease"].includes(goalType ?? "") && <label className="flex items-start gap-2 text-sm text-warm-700">
            <input type="checkbox" checked={values.calculation_options?.maternal_confirmed ?? false} className="mt-1"
              onChange={event => setOption("maternal_confirmed", event.target.checked)} />
            Maternal additions reviewed for this clinical prescription, when applicable
          </label>}
        </div>
      </details>}
      {!readOnly && onCalculate && <div className="flex flex-wrap items-center gap-3">
        <Button type="button" variant="secondary" onClick={onCalculate} disabled={saving || !goalType}>Calculate targets</Button>
        {calculationDirty && <p className="text-sm text-amber-800">Calculate targets to apply the changed inputs.</p>}
      </div>}

      <div className="space-y-1.5">
        <p className="text-xs font-bold text-warm-500">Target weight</p>
        {readOnly && !values.weight_target ? <p className="text-sm text-warm-500">No target weight recorded.</p> : <div className="flex flex-wrap items-center gap-2">
          <select aria-label="Target weight type" disabled={readOnly} value={weightRange ? 'range' : 'single'}
            onChange={event => onChange({ ...values, weight_target: event.target.value === 'range'
              ? { min: weight.target ?? defaultWeightTarget ?? '', max: weight.target ?? defaultWeightTarget ?? '' }
              : { target: weight.min ?? defaultWeightTarget ?? '' } })}
            className="rounded-lg border border-warm-200 bg-white px-2 py-2 text-sm">
            <option value="single">Single value</option><option value="range">Range</option>
          </select>
          {weightRange ? <>{weightInput('min', 'Target weight minimum')}<span aria-hidden="true">–</span>{weightInput('max', 'Target weight maximum')}</> : weightInput('target', 'Target weight')}
          <span className="text-xs text-warm-500">kg</span>
        </div>}
        {!readOnly && defaultWeightTarget != null && <p className="text-xs text-warm-500">Calculated IBW: {defaultWeightTarget} kg</p>}
      </div>

      {/* Macro inputs */}
      <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,180px),1fr))] gap-3">
        {MACROS.map(({ key, label, unit }) => (
          <NumericInput
            key={key}
            label={label}
            value={(values as unknown as Record<string, string>)[key] ?? ""}
            onChange={(val) => setMacro(key, val)}
            unit={unit}
            disabled={readOnly}
            prefix={boundSelect(label, values.nutrient_operators?.[key] ?? '', value => setMacroBound(key, value), key === 'fluid_ml' && values.fluid_mode === 'restricted')}
          />
        ))}
      </div>

      {calculationTrace && (
        <PrescriptionCalculationPanel
          trace={calculationTrace}
          expanded={showCalculations}
          onToggle={() => setShowCalculations((current) => !current)}
        />
      )}

      {/* Micronutrient limit rows */}
      {microList.length > 0 ? (
        <div className="space-y-2 pt-2 border-t border-warm-100">
          <p className="text-xs font-bold text-warm-400 uppercase tracking-widest">Micronutrient Targets / Limits</p>
          <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,320px),1fr))] gap-2">
            {microList.map((key) => {
              const micro    = ALL_MICROS.find((m) => m.key === key);
              const limits   = values.micronutrient_limits[key] ?? {};
              const bound = limits.min != null && limits.max != null ? 'range' : limits.min != null ? 'min' : limits.max != null ? 'max' : '';
              const limitFields: Array<"min" | "max" | "target"> = limits.min != null && limits.max != null
                ? ["min", "max"]
                : [limits.min != null ? "min" : limits.max != null ? "max" : "target"];
              return (
                <div key={key} className="flex flex-wrap items-center gap-2 p-2.5 bg-warm-50 border border-warm-200 rounded-xl">
                  <span className="text-xs font-semibold text-warm-700 w-24 flex-shrink-0 flex items-center gap-1">
                    {micro?.label ?? key}
                  </span>
                  <div className="flex min-w-0 flex-wrap items-center gap-1 flex-1">
                    {boundSelect(micro?.label ?? key, bound, value => setMicroBound(key, value))}
                    {limitFields.map((field) => <Fragment key={field}>
                      {bound === 'range' && <span className="text-xs text-warm-400">{field === 'min' ? '≥' : '≤'}</span>}
                      <input type="number" min="0" step="0.1"
                        value={limits[field] ?? ""}
                        onChange={(e) => setMicroLimit(key, field, e.target.value)}
                        disabled={readOnly}
                        aria-label={`${micro?.label ?? key} ${field}`}
                        className="w-16 px-2 py-1 text-sm font-mono border border-warm-200 rounded-lg focus:outline-none focus:border-emerald-500 disabled:bg-warm-50"
                      />
                    </Fragment>)}
                    <span className="text-xs text-warm-400">{micro?.unit}</span>
                  </div>
                  {readOnly ? null : (
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
      ) : null}

      {showSave && !readOnly && <div className="flex justify-end pt-2">
        <Button type="button"
          onClick={onSave}
          loading={saving}>
          Save Prescription
        </Button>
      </div>}
    </div>
  );
}
