"use client";

import type { ReactNode } from 'react';
import MultiSelectPopover from '@/components/ui/MultiSelectPopover';
import NumericInput from '@/components/ui/NumericInput';
import { INTAKE_MACROS, INTAKE_FIELDS } from '@/lib/foodRecall';
import MicronutrientToggle from './MicronutrientToggle';

export function FoodRecallNutrientFields({ values, onChange, readOnly = false, renderField }: {
  values: Record<string, string>; onChange: (values: Record<string, string>) => void; readOnly?: boolean;
  renderField?: (field: typeof INTAKE_FIELDS[number], value: string, onChange: (value: string) => void) => ReactNode;
}) {
  const selected = Object.keys(values);
  const fields = INTAKE_FIELDS.filter(field => selected.includes(field.key) && (!readOnly || values[field.key] !== ''));
  function select(keys: string[]) {
    onChange(Object.fromEntries(keys.map(key => [key, values[key] ?? ''])));
  }
  return <div className="min-w-0 space-y-3">
    {!readOnly && <div className="flex flex-wrap justify-end gap-2">
      <MultiSelectPopover items={INTAKE_MACROS} selected={selected.filter(key => !key.startsWith('micro_'))} required={['energy_kcal']} onChange={keys => select([...keys, ...selected.filter(key => key.startsWith('micro_'))])} triggerLabel="Display Macros" title="Choose macronutrients to display" />
      <MicronutrientToggle selected={selected.filter(key => key.startsWith('micro_')).map(key => key.slice(6))} onChange={keys => select([...selected.filter(key => !key.startsWith('micro_')), ...keys.map(key => `micro_${key}`)])} />
    </div>}
    <div className="grid gap-4" style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,180px),1fr))' }}>
      {fields.map(field => <div key={field.key} className="min-w-0">{renderField ? renderField(field, values[field.key], value => onChange({ ...values, [field.key]: value })) : <NumericInput label={`${field.label} intake`} unit={field.unit} value={values[field.key]} onChange={value => onChange({ ...values, [field.key]: value })} min={0} max={field.max} step={0.01} disabled={readOnly} />}</div>)}
    </div>
  </div>;
}
