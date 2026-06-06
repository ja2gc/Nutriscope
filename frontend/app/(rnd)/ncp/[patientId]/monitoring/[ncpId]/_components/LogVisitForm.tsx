"use client";

import { useState } from "react";
import { ChevronDown, ChevronUp, X } from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  Collapsible,
  CollapsibleTrigger,
  CollapsibleContent,
} from "@/components/ui/collapsible";
import {
  MonitoringPayload,
  ComplianceStatus,
  GiToleranceStatus,
  ContinuationDecision,
  calculateBmi,
} from "@/services/monitoringService";

interface LogVisitFormProps {
  heightCm: number | null;
  onSubmit: (payload: MonitoringPayload) => Promise<void>;
  onCancel: () => void;
}

// ─── Toggle option configs ────────────────────────────────────────────────────

const COMPLIANCE_OPTIONS: { value: ComplianceStatus; label: string; active: string }[] = [
  { value: 'compliant',     label: 'Compliant',  active: 'bg-emerald-600 text-white border-emerald-600' },
  { value: 'partial',       label: 'Partial',    active: 'bg-amber-500 text-white border-amber-500' },
  { value: 'non_compliant', label: 'Non-compl.', active: 'bg-rose-500 text-white border-rose-500' },
];

const GI_OPTIONS: { value: GiToleranceStatus; label: string; active: string }[] = [
  { value: 'tolerating',     label: 'Tolerating',  active: 'bg-emerald-600 text-white border-emerald-600' },
  { value: 'not_tolerating', label: 'Not Tolerating', active: 'bg-rose-500 text-white border-rose-500' },
];

type NonNullDecision = NonNullable<ContinuationDecision>;

const DECISION_OPTIONS: { value: NonNullDecision; label: string; active: string }[] = [
  { value: 'continue',    label: 'Continue',    active: 'bg-emerald-600 text-white border-emerald-600' },
  { value: 'modify',      label: 'Modify',      active: 'bg-amber-500 text-white border-amber-500' },
  { value: 'discontinue', label: 'Discontinue', active: 'bg-zinc-700 text-white border-zinc-700' },
];

const LAB_FIELDS = [
  { key: 'albumin',     label: 'Albumin',     unit: 'g/dL',    type: 'number' },
  { key: 'hba1c',       label: 'HbA1c',       unit: '%',       type: 'number' },
  { key: 'ldl',         label: 'LDL',         unit: 'mg/dL',   type: 'number' },
  { key: 'cholesterol', label: 'Cholesterol', unit: 'mg/dL',   type: 'number' },
  { key: 'creatinine',  label: 'Creatinine',  unit: 'mg/dL',   type: 'number' },
  { key: 'potassium',   label: 'Potassium',   unit: 'mEq/L',   type: 'number' },
  { key: 'hemoglobin',  label: 'Hemoglobin',  unit: 'g/dL',    type: 'number' },
  { key: 'glucose',     label: 'Glucose',     unit: 'mg/dL',   type: 'number' },
  { key: 'bp',          label: 'Blood Pressure', unit: 'mmHg', type: 'text'   },
] as const;

type LabKey = typeof LAB_FIELDS[number]['key'];

// ─── Shared input class — matches existing project form style ─────────────────
const inputCls =
  'w-full px-3.5 py-2.5 text-sm border border-zinc-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 bg-white transition-all placeholder:text-zinc-400';

// ─── Unit input wrapper — matches NutritionPrescriptionForm style ─────────────
function UnitInput({
  label,
  unit,
  value,
  onChange,
  type = 'number',
  placeholder = '',
  disabled = false,
}: {
  label: string;
  unit: string;
  value: string;
  onChange: (v: string) => void;
  type?: string;
  placeholder?: string;
  disabled?: boolean;
}) {
  return (
    <div>
      <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-1.5">
        {label}
      </label>
      <div className="flex items-center border border-zinc-200 rounded-xl overflow-hidden focus-within:border-emerald-600 focus-within:ring-2 focus-within:ring-emerald-500/20 transition-all">
        <input
          type={type}
          step="0.1"
          min="0"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          disabled={disabled}
          className="w-full px-3.5 py-2.5 text-sm font-mono text-zinc-900 bg-transparent focus:outline-none placeholder:text-zinc-400 disabled:text-zinc-400"
        />
        <span className="px-2.5 text-[9px] font-bold text-zinc-400 bg-zinc-50 border-l border-zinc-200 whitespace-nowrap select-none">
          {unit}
        </span>
      </div>
    </div>
  );
}

// ─── Toggle group — used for compliance, GI, decision ─────────────────────────
function ToggleGroup<T extends string>({
  label,
  options,
  value,
  onChange,
}: {
  label: string;
  options: { value: T; label: string; active: string }[];
  value: T | null;
  onChange: (v: T | null) => void;
}) {
  return (
    <div>
      <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-2">{label}</p>
      <div className="flex flex-wrap gap-2">
        {options.map((opt) => (
          <button
            key={opt.value}
            type="button"
            onClick={() => onChange(value === opt.value ? null : opt.value)}
            className={`flex-1 min-w-[80px] py-2.5 text-xs font-semibold rounded-xl border transition-all ${
              value === opt.value
                ? opt.active
                : 'bg-white text-zinc-500 border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50'
            }`}
          >
            {opt.label}
          </button>
        ))}
      </div>
    </div>
  );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function LogVisitForm({ heightCm, onSubmit, onCancel }: LogVisitFormProps) {
  const todayStr = new Date().toISOString().split('T')[0];

  const [weight, setWeight]         = useState('');
  const [bmi, setBmi]               = useState('');
  const [compliance, setCompliance] = useState<ComplianceStatus | null>(null);
  const [giTolerance, setGiTolerance] = useState<GiToleranceStatus | null>(null);
  const [decision, setDecision]     = useState<ContinuationDecision>(null);
  const [clinicalSummary, setClinicalSummary] = useState('');
  const [nextDate, setNextDate]     = useState('');
  const [labsOpen, setLabsOpen]     = useState(false);
  const [labs, setLabs]             = useState<Record<LabKey, string>>({
    albumin: '', hba1c: '', ldl: '', cholesterol: '',
    creatinine: '', potassium: '', hemoglobin: '', glucose: '', bp: '',
  });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError]           = useState<string | null>(null);

  function handleWeightChange(val: string) {
    setWeight(val);
    const w = parseFloat(val);
    if (!isNaN(w) && w > 0 && heightCm && heightCm > 0) {
      setBmi(String(calculateBmi(w, heightCm)));
    } else {
      setBmi('');
    }
  }

  function buildPayload(): MonitoringPayload {
    const labValues: Record<string, number | string | null> = {};
    LAB_FIELDS.forEach(({ key }) => {
      const v = labs[key].trim();
      if (v !== '') {
        labValues[key] = key === 'bp' ? v : parseFloat(v);
      }
    });

    const goalAchievement: Record<string, string> = {};
    if (compliance) goalAchievement.compliance = compliance;
    if (giTolerance) goalAchievement.gi_tolerance = giTolerance;
    if (decision)    goalAchievement.continuation_decision = decision;

    return {
      weight:               weight ? parseFloat(weight) : null,
      bmi:                  bmi ? parseFloat(bmi) : null,
      lab_values:           Object.keys(labValues).length > 0
                              ? labValues as MonitoringPayload['lab_values']
                              : null,
      clinical_summary:     clinicalSummary.trim() || null,
      goal_achievement:     Object.keys(goalAchievement).length > 0 ? goalAchievement : null,
      next_monitoring_date: nextDate || null,
    };
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await onSubmit(buildPayload());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save visit.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between px-5 py-4 border-b border-zinc-100">
        <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Log New Visit</h3>
        <button
          onClick={onCancel}
          type="button"
          className="text-zinc-400 hover:text-zinc-600 transition-colors p-1 rounded-lg hover:bg-zinc-100"
        >
          <X className="h-4 w-4" />
        </button>
      </div>

      <form onSubmit={handleSubmit} className="px-5 py-5 space-y-5">
        {/* Weight + BMI — 2-col on sm+, stacked on xs */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <UnitInput
            label="Weight"
            unit="kg"
            value={weight}
            onChange={handleWeightChange}
            placeholder="e.g. 68.5"
          />
          <UnitInput
            label={heightCm ? 'BMI (auto-calculated)' : 'BMI'}
            unit="kg/m²"
            value={bmi}
            onChange={setBmi}
            placeholder={heightCm ? 'Auto from weight' : 'Enter manually'}
            disabled={!!heightCm && !!weight}
          />
        </div>

        {/* Diet Compliance */}
        <ToggleGroup
          label="Diet Compliance"
          options={COMPLIANCE_OPTIONS}
          value={compliance}
          onChange={setCompliance}
        />

        {/* GI Tolerance */}
        <ToggleGroup
          label="GI Tolerance"
          options={GI_OPTIONS}
          value={giTolerance}
          onChange={(v) => setGiTolerance(v as GiToleranceStatus | null)}
        />

        {/* Care Decision */}
        <ToggleGroup<NonNullDecision>
          label="Care Decision"
          options={DECISION_OPTIONS}
          value={decision}
          onChange={(v) => setDecision(v)}
        />

        {/* Lab Values — collapsible, 2-col on sm+ */}
        <Collapsible open={labsOpen} onOpenChange={setLabsOpen}>
          <div className="border border-zinc-200 rounded-xl overflow-hidden">
            <CollapsibleTrigger asChild>
              <button
                type="button"
                className="w-full flex items-center justify-between px-4 py-3 text-[9px] font-bold text-zinc-400 uppercase tracking-widest hover:bg-zinc-50 transition-colors"
              >
                <span>
                  Lab Values{' '}
                  <span className="normal-case font-normal text-zinc-300">(optional)</span>
                </span>
                {labsOpen
                  ? <ChevronUp className="h-3.5 w-3.5 text-zinc-400" />
                  : <ChevronDown className="h-3.5 w-3.5 text-zinc-400" />
                }
              </button>
            </CollapsibleTrigger>

            <CollapsibleContent>
              <div className="px-4 pb-4 border-t border-zinc-100 pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                {LAB_FIELDS.map(({ key, label, unit, type }) => (
                  <UnitInput
                    key={key}
                    label={label}
                    unit={unit}
                    type={type}
                    value={labs[key]}
                    onChange={(v) => setLabs((prev) => ({ ...prev, [key]: v }))}
                    placeholder="—"
                  />
                ))}
              </div>
            </CollapsibleContent>
          </div>
        </Collapsible>

        {/* Clinical Notes */}
        <div>
          <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-1.5">
            Clinical Notes
          </label>
          <textarea
            value={clinicalSummary}
            onChange={(e) => setClinicalSummary(e.target.value)}
            rows={3}
            placeholder="Observations, tolerance, patient feedback…"
            className="w-full px-3.5 py-2.5 text-sm border border-zinc-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 bg-white transition-all placeholder:text-zinc-400 resize-none"
          />
        </div>

        {/* Next Visit Date */}
        <div>
          <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-1.5">
            Next Visit Date
          </label>
          <input
            type="date"
            value={nextDate}
            min={todayStr}
            onChange={(e) => setNextDate(e.target.value)}
            className={inputCls}
          />
        </div>

        {/* Error */}
        {error && (
          <div className="flex items-start gap-2 p-3 bg-rose-50 border border-rose-200 rounded-xl">
            <p className="text-xs text-rose-700">{error}</p>
          </div>
        )}

        {/* Actions */}
        <div className="flex flex-col sm:flex-row gap-3 pt-1">
          <Button type="submit" variant="primary" loading={submitting} className="sm:flex-1">
            Save Visit
          </Button>
          <Button type="button" variant="ghost" onClick={onCancel} className="sm:!w-auto">
            Cancel
          </Button>
        </div>
      </form>
    </div>
  );
}
