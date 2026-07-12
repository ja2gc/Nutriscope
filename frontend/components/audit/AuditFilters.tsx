"use client";

import { Filter, RotateCcw } from "lucide-react";
import type { User } from "@/services/authService";
import type { AuditCategory, AuditFilterMetadata, AuditOutcome, AuditSeverity } from "@/types/audit";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";

export interface AuditFilterState {
  category?: AuditCategory;
  domain?: string;
  action?: string;
  actor_id?: string;
  outcome?: AuditOutcome;
  severity?: AuditSeverity;
  start?: string;
  end?: string;
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
  users,
  value,
  onChange,
  onClear,
}: {
  metadata: AuditFilterMetadata;
  users: User[];
  value: AuditFilterState;
  onChange: (next: AuditFilterState) => void;
  onClear: () => void;
}) {
  const actorOptions = users.map((user) => ({ value: String(user.id), label: `${user.name} (${user.role})` }));
  const compatibleActions = value.category
    ? new Set(metadata.category_actions[value.category] || [])
    : null;
  const actionOptions = compatibleActions
    ? metadata.actions.filter((option) => compatibleActions.has(option.value))
    : metadata.actions;

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
        <fieldset className="grid grid-cols-2 gap-2 sm:col-span-2">
          <legend className="mb-1 text-xs font-bold uppercase tracking-wider text-warm-500">Date range</legend>
          <label>
            <span className="sr-only">Start date</span>
            <input aria-label="Start date" type="date" className={controlClass} value={value.start || ""} onChange={(event) => update("start", event.target.value || undefined)} />
          </label>
          <label>
            <span className="sr-only">End date</span>
            <input aria-label="End date" type="date" className={controlClass} value={value.end || ""} onChange={(event) => update("end", event.target.value || undefined)} />
          </label>
        </fieldset>

        <SelectFilter label="Domain" allLabel="All domains" value={value.domain} options={metadata.domains} onChange={(next) => update("domain", next)} />
        <SelectFilter label="Action" allLabel="All actions" value={value.action} options={actionOptions} onChange={(next) => update("action", next)} />
        <SelectFilter label="Actor" allLabel="All actors" value={value.actor_id} options={actorOptions} onChange={(next) => update("actor_id", next)} />
        <SelectFilter label="Outcome" allLabel="All outcomes" value={value.outcome} options={metadata.outcomes} onChange={(next) => update("outcome", next)} />
        <SelectFilter label="Severity" allLabel="All severities" value={value.severity} options={metadata.severities} onChange={(next) => update("severity", next)} />
      </div>
    </Card>
  );
}
