"use client";

import { useState } from 'react';
import { FormulaCollection } from './FormulaCollection';
import { MenuItemCreateForm } from './MenuItemCreateForm';
import { MenuItemPicker } from './MenuItemPicker';
import { Button } from '@/components/ui/Button';
import { createRecipe } from '@/services/foodLibraryService';
import { fetchMealPlanTemplates, fetchMealPlanTemplate, saveLibraryMealPlanTemplate, type MealPlanTemplate } from '@/services/mealPlanService';
import { formulaFromRecipe, type FormulaItem } from '@/lib/formulaPlan';
import type { PaginationMeta } from '@/components/ui/Pagination';

export function TubeFormulaPlanner({ items, onChange, readOnly, selectedMicros, onSavePlan }: {
  items: FormulaItem[]; onChange: (items: FormulaItem[]) => void; readOnly: boolean;
  selectedMicros: string[]; onSavePlan: () => Promise<string | null>;
}) {
  const [creating, setCreating] = useState(false), [templates, setTemplates] = useState<MealPlanTemplate[] | null>(null);
  const [meta, setMeta] = useState<PaginationMeta | null>(null), [page, setPage] = useState(1);
  const [savingTemplate, setSavingTemplate] = useState(false), [name, setName] = useState('');
  const [busy, setBusy] = useState(false), [error, setError] = useState<string | null>(null), [notice, setNotice] = useState<string | null>(null);
  async function loadTemplates(next = 1) {
    if (templates === null) setTemplates([]);
    setBusy(true); setError(null);
    try { const result = await fetchMealPlanTemplates(next, 'tube_feeding'); setTemplates(result.data); setMeta(result.meta); setPage(next); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not load templates.'); }
    finally { setBusy(false); }
  }
  if (creating) return <MenuItemCreateForm onCancel={() => setCreating(false)} onCreate={async (payload, preview, saveToLibrary) => {
    const recipe = saveToLibrary ? await createRecipe(payload) : preview;
    const item = formulaFromRecipe(recipe);
    if (!saveToLibrary) { item.recipe_id = null; item.new_formula = payload; }
    onChange([...items, item]);
    setCreating(false);
  }} />;
  return <div className="space-y-4">
    {error && <p role="alert" className="text-sm text-red-700">{error}</p>}{notice && <p role="status" className="text-sm text-emerald-700">{notice}</p>}
    <FormulaCollection items={items} onChange={onChange} readOnly={readOnly} selectedMicros={selectedMicros} onCreate={() => setCreating(true)} actions={!readOnly && <><Button variant="secondary" onClick={() => void loadTemplates()}>Load Template</Button>{items.length > 0 && <Button variant="secondary" onClick={() => { setName(''); setSavingTemplate(true); }}>Save as template</Button>}</>} />
    {templates && <MenuItemPicker title="Tube Feeding templates" itemLabel="Template" showSearch={false} query="" onQueryChange={() => {}} loading={busy} busyId={busy ? 'template' : null} error={error} items={templates.map(template => ({ id: template.id, name: template.name }))} onClose={() => setTemplates(null)} page={page} meta={meta} onPageChange={next => void loadTemplates(next)} onSelect={async id => {
      setBusy(true); setError(null);
      try { const detail = await fetchMealPlanTemplate(id); if (!detail || detail.nutrition_support !== 'tube_feeding') throw new Error('Choose a Tube Feeding template.');
        onChange((detail.formula_items ?? []).map(item => ({ ...structuredClone(item), id: crypto.randomUUID(), original_snapshot: structuredClone(item.nutrient_snapshot), source_template_id: detail.id, source_template_item_id: item.id })));
        setTemplates(null);
      } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not load template.'); } finally { setBusy(false); }
    }} />}
    {savingTemplate && <form aria-label="Save formula template" className="flex flex-wrap items-end gap-3 rounded-xl border border-warm-200 p-4" onSubmit={async event => {
      event.preventDefault(); setBusy(true); setError(null);
      try { const id = await onSavePlan(); if (!id) return;
        await saveLibraryMealPlanTemplate({ name: name.trim(), nutrition_support: 'tube_feeding', source_intervention_id: id, lines: [] });
        setSavingTemplate(false); setNotice('Template saved.');
      } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not save template.'); } finally { setBusy(false); }
    }}><label className="min-w-0 flex-1 text-sm font-semibold">Template name<input required maxLength={255} value={name} onChange={event => setName(event.target.value)} className="mt-1 block min-h-11 w-full rounded-lg border border-warm-200 px-3" /></label><Button type="button" variant="secondary" onClick={() => setSavingTemplate(false)}>Cancel</Button><Button type="submit" loading={busy}>Save Template</Button></form>}
  </div>;
}
