"use client";

import { useState } from "react";
import { Filter, RotateCcw } from "lucide-react";
import type { AuditFilterMetadata, AuditModule, AuditOutcome, AuditSeverity } from "@/types/audit";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { MonthYearSelect } from "@/components/ui/MonthYearSelect";
import { AuditActorFilter } from "./AuditActorFilter";

export interface AuditFilterState {
  module?: AuditModule;
  subfilter?: string;
  action?: string;
  actor_id?: string;
  outcome?: AuditOutcome;
  severity?: AuditSeverity;
  month?: string;
}

const controlClass =
  "h-11 w-full rounded-lg border border-warm-200 bg-white px-3 text-base text-warm-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30";

function SelectFilter({
  label,
  value,
  allLabel,
  options,
  onChange,
}: {
  label: string;
  value?: string;
  allLabel: string;
  options: Array<{ value: string; label: string }>;
  onChange: (value: string | undefined) => void;
}) {
  return (
    <label className="block min-w-0">
      <span className="mb-1 block text-xs font-bold uppercase tracking-wider text-warm-500">{label}</span>
      <select className={controlClass} value={value || ""} onChange={(event) => onChange(event.target.value || undefined)}>
        <option value="">{allLabel}</option>
        {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
      </select>
    </label>
  );
}

export function AuditFilters({
  metadata,
  value,
  onChange,
  onClear,
}: {
  metadata: AuditFilterMetadata;
  value: AuditFilterState;
  onChange: (next: AuditFilterState) => void;
  onClear: () => void;
}) {
  const [filterYear, setFilterYear] = useState(new Date().getFullYear());
  const selectedYear = value.month ? Number(value.month.slice(0, 4)) : filterYear;
  const selectedMonth = value.month ? Number(value.month.slice(5, 7)) : null;
  const compatibleActions = value.module
    ? new Set(metadata.module_actions[value.module] || [])
    : null;
  const actionOptions = compatibleActions
    ? metadata.actions.filter((option) => compatibleActions.has(option.value))
    : metadata.actions;
  const subfilterOptions = value.module ? metadata.module_subfilters[value.module] || [] : [];

  function update(key: keyof AuditFilterState, nextValue: string | undefined) {
    onChange({ ...value, [key]: nextValue });
  }

  return (
    <Card padded>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-warm-600">
          <Filter className="h-4 w-4 text-brand-green-600" />
          Filters
        </div>
        <Button type="button" variant="ghost" size="sm" onClick={onClear}>
          <RotateCcw className="h-4 w-4" />
          Clear filters
        </Button>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div className="grid grid-cols-2 gap-2 sm:col-span-2">
          <MonthYearSelect month={selectedMonth} year={selectedYear} allowAllMonths monthAriaLabel="Audit month" yearAriaLabel="Audit year"
            onMonthChange={(month) => update("month", month === null ? undefined : `${selectedYear}-${String(month).padStart(2, "0")}`)}
            onYearChange={(year) => {
              setFilterYear(year);
              if (selectedMonth !== null) update("month", `${year}-${String(selectedMonth).padStart(2, "0")}`);
            }} />
        </div>

        {value.module && subfilterOptions.length > 0 && (
          <SelectFilter label="Context" allLabel={`All ${metadata.modules.find((module) => module.value === value.module)?.label || "contexts"}`} value={value.subfilter} options={subfilterOptions} onChange={(next) => update("subfilter", next)} />
        )}
        <SelectFilter label="Action" allLabel="All actions" value={value.action} options={actionOptions} onChange={(next) => update("action", next)} />
        <AuditActorFilter value={value.actor_id} onChange={(next) => update("actor_id", next)} />
        <SelectFilter label="Outcome" allLabel="All outcomes" value={value.outcome} options={metadata.outcomes} onChange={(next) => update("outcome", next)} />
        <SelectFilter label="Severity" allLabel="All severities" value={value.severity} options={metadata.severities} onChange={(next) => update("severity", next)} />
      </div>
    </Card>
  );
}
