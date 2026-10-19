// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, expect, it, vi } from "vitest";
import MealPlanSection from "./MealPlanSection";
import { fetchFoodItems, fetchRecipes, fetchRecipeById, searchUsda } from "@/services/foodLibraryService";
import { addMealPlanItem, createMealPlan, createPlanFromTemplate, fetchAllMealPlanItems, fetchMealPlanTemplate, fetchMealPlanTemplates, fetchMealPlans, generateMealPlan, updateMealPlanItem } from "@/services/mealPlanService";
import type { MealPlan, MealPlanItem } from "@/services/mealPlanService";
import { fireEvent, getByRole } from '@testing-library/dom';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

it('adds ingredients in their own unit with a blank amount and keeps additions fixed while scaling the original recipe', async () => {
  vi.mocked(fetchAllMealPlanItems).mockResolvedValueOnce([{
    id: 'portion-item', meal_plan_day_id: 'day-1', recipe_id: 'recipe-1', quantity: '1', unit: 'serving', source: 'recipe',
    nutrient_snapshot: { name: 'Rice bowl', calories: 100, protein: 2, carbs: 20, fat: 1, serving_size: 1, serving_unit: 'serving', micronutrients: {} },
  } as MealPlanItem]);
  vi.mocked(fetchRecipeById).mockResolvedValueOnce({ id: 'recipe-1', name: 'Rice bowl', servings: 1, ingredients: [{ id: 1, quantity: '100', unit: 'g', food_item: { id: 'rice', name: 'Rice', serving_size: '100', serving_unit: 'g', calories: '100' } }] } as never);
  vi.mocked(fetchFoodItems).mockResolvedValue({ data: [{ id: 'milk', name: 'Milk', serving_size: '100', serving_unit: 'mL', calories: '60' }], meta: null } as never);
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1" prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />));
  try {
    await act(async () => container.querySelector<HTMLButtonElement>('button[title="Edit"]')!.click());
    await act(async () => getByRole(container, 'button', { name: 'Ingredients' }).click());
    await act(async () => getByRole(container, 'button', { name: 'Add Ingredient' }).click());
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    await act(async () => getByRole(container, 'button', { name: 'Add Milk' }).click());
    const amount = getByRole(container, 'spinbutton', { name: 'Amount of Milk' }) as HTMLInputElement;
    expect(amount.value).toBe('');
    const unit = getByRole(container, 'combobox', { name: 'Unit of Milk' }) as HTMLSelectElement;
    expect(unit.value).toBe('mL');
    expect(Array.from(unit.options).map(option => option.value)).not.toContain('g');
    await act(async () => getByRole(container, 'button', { name: 'Back to menu plan' }).click());
    await act(async () => getByRole(container, 'button', { name: 'Save' }).click());
    expect(updateMealPlanItem).not.toHaveBeenCalled();
    expect(getByRole(container, 'dialog', { name: 'Unsaved changes' })).toBeTruthy();
    await act(async () => getByRole(container, 'button', { name: 'Stay on this page' }).click());
    await act(async () => fireEvent.input(amount, { target: { value: '50.123456' } }));
    expect(amount.value).toBe('50.12');
    await act(async () => fireEvent.input(getByRole(container, 'spinbutton', { name: 'Planned portion' }), { target: { value: '2' } }));
    expect(amount.value).toBe('50.12');
    expect((getByRole(container, 'spinbutton', { name: 'Amount of Rice' }) as HTMLInputElement).value).toBe('200');
    await act(async () => getByRole(container, 'button', { name: 'Save Changes' }).click());
    expect(updateMealPlanItem).toHaveBeenCalledWith('ncp-1', 'plan-1', 'day-1', 'portion-item', { quantity: 2, ingredients: [{ food_item_id: 'rice', quantity: 100, unit: 'g' }, { food_item_id: 'milk', quantity: 25.061728, unit: 'mL' }] });
  } finally { await act(async () => root.unmount()); }
});

vi.mock("@/services/mealPlanService", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/services/mealPlanService")>()),
  fetchMealPlans: vi.fn(async () => [{
    id: "plan-1", intervention_plan_id: "intervention-1", week_start_date: "2026-10-05",
    generation_type: "manual", scale_status: "available", scaled_at: null, status: "draft",
    days: [{ id: "day-1", day_of_week: "Monday", meal_type: "breakfast", flagged: false }],
  }]),
  fetchMealPlanTemplates: vi.fn(async () => ({ data: [], meta: null })),
  fetchMealPlanTemplate: vi.fn(),
  createPlanFromTemplate: vi.fn(),
  createMealPlan: vi.fn(async () => ({ id: "new-menu", intervention_plan_id: "new-intervention", week_start_date: "2026-10-12", generation_type: "manual", scale_status: "available", scaled_at: null, status: "draft", days: [] })),
  fetchAllMealPlanItems: vi.fn(async () => []),
  addMealPlanItem: vi.fn(async () => ({ id: "item-new", meal_plan_day_id: "day-1", recipe_id: "snack-1", quantity: "1", unit: "serving", source: "recipe", nutrient_snapshot: { name: "Fruit cup", calories: 80, protein: 1, carbs: 20, fat: 0, serving_size: 1, serving_unit: "serving", micronutrients: {} } })),
  updateMealPlanItem: vi.fn(async () => ({ id: "item-1", meal_plan_day_id: "day-1", recipe_id: "recipe-1", quantity: "1.5", unit: "serving", source: "recipe", nutrient_snapshot: { name: "Rice bowl", calories: 200, protein: 8, carbs: 35, fat: 4, serving_size: 1, serving_unit: "serving", micronutrients: {} } })),
  generateMealPlan: vi.fn(async () => ({ insufficient_recipes: true, count: 0, message: "Add more Menu Items." })),
}));

vi.mock("@/services/foodLibraryService", () => ({
  fetchFoodItems: vi.fn(),
  fetchRecipes: vi.fn(async () => ({ data: [{ id: "snack-1", name: "Fruit cup", category: "snack", servings: 1, total_calories: "80", total_protein: "1", total_carbs: "20", total_fat: "0" }], meta: null })),
  searchUsda: vi.fn(async () => []),
  importUsdaFood: vi.fn(),
  fetchRecipeById: vi.fn(),
}));

afterEach(() => {
  document.body.innerHTML = "";
  vi.clearAllMocks();
  vi.mocked(createPlanFromTemplate).mockReset();
  vi.mocked(fetchMealPlanTemplate).mockReset();
  vi.mocked(fetchMealPlans).mockReset().mockResolvedValue([{ id: 'plan-1', intervention_plan_id: 'intervention-1', week_start_date: '2026-10-05', generation_type: 'manual', scale_status: 'available', scaled_at: null, status: 'draft', days: [{ id: 'day-1', day_of_week: 'Monday', meal_type: 'breakfast', flagged: false }] }]);
  vi.mocked(fetchAllMealPlanItems).mockReset().mockResolvedValue([]);
});

async function renderEmptyPlanner(goal: string | null = null) {
  vi.mocked(fetchMealPlans).mockResolvedValueOnce([]);
  const container = document.createElement("div");
  document.body.append(container);
  const root = createRoot(container);
  await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1" interventionGoal={goal} prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />));
  return { container, root };
}

it("shows at most one decimal on menu cards while preserving exact editable servings", async () => {
  vi.mocked(fetchAllMealPlanItems).mockResolvedValueOnce([{
    id: "precision-item", meal_plan_day_id: "day-1", recipe_id: "recipe-1", quantity: "1.67", unit: "serving", ai_suggested: false, source: "recipe",
    nutrient_snapshot: { name: "Rice bowl", calories: 200, protein: 8, carbs: 35, fat: 4, serving_size: 1, serving_unit: "serving", micronutrients: {} },
  } as MealPlanItem]);
  vi.mocked(fetchRecipeById).mockResolvedValueOnce({ id: "recipe-1", name: "Rice bowl", servings: 1, ingredients: [] } as never);
  const container = document.createElement("div"); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1" prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />));
  try {
    expect(container.textContent).toContain("1.7 serving");
    expect(container.textContent).not.toContain("1.67 serving");
    await act(async () => container.querySelector<HTMLButtonElement>('button[title="Edit"]')!.click());
    expect(container.querySelector<HTMLInputElement>('[aria-label="Menu Item profile"] input[aria-label="Planned portion"]')?.value).toBe("1.67");
    expect(container.querySelector<HTMLInputElement>('[aria-label="Planned portion"]')?.step).toBe('0.1');
  } finally { await act(async () => root.unmount()); }
});

it('keeps generation and clear controls available when a saved menu already exists', async () => {
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1" prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />));
  try {
    expect(container.textContent).toContain('Auto-Generate');
    expect(container.textContent).toContain('Clear menu');
    expect(container.textContent).not.toContain('Save week');
    expect(container.textContent).not.toContain('Discard draft');
  } finally { await act(async () => root.unmount()); }
});

it('ignores a completed generation response after switching to another intervention', async () => {
  let finish!: (plan: MealPlan) => void;
  vi.mocked(generateMealPlan).mockImplementationOnce(() => new Promise((resolve) => { finish = resolve; }));
  const { container, root } = await renderEmptyPlanner();
  try {
    await act(async () => container.querySelector<HTMLInputElement>('input[type="checkbox"]')!.click());
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Auto-Generate')!.click());
    await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-2" prescriptionTargets={{ energy: 1800, protein: 70, carbs: 250, fat: 55, fluid: 2000 }} />));
    await act(async () => finish({ id: 'late-generated-menu', intervention_plan_id: 'intervention-1', week_start_date: '2026-10-19', generation_type: 'auto', scale_status: 'already_scaled', scaled_at: '', status: 'draft', days: [] }));
    expect(container.textContent).not.toContain('Clear menu');
  } finally { await act(async () => root.unmount()); }
});

it("prefills the previous menu and automatically saves an independent week after intervention save", async () => {
  const previous: MealPlan = { id: 'previous-menu', intervention_plan_id: 'previous-intervention', week_start_date: '2026-10-12', generation_type: 'manual', scale_status: 'available', scaled_at: null, status: 'draft', days: [{ id: 'previous-day', day_of_week: 'Monday', meal_type: 'breakfast', flagged: false }] };
  const source: MealPlanItem = { id: 'previous-item', meal_plan_day_id: 'previous-day', recipe_id: 'recipe-1', food_item_id: null, fdc_id: null, source: 'recipe', ai_suggested: false, quantity: '1.2', unit: 'serving', nutrient_snapshot: { name: 'Previous breakfast', calories: 200, protein: 8, carbs: 35, fat: 4, serving_size: 1, serving_unit: 'serving', micronutrients: {} } };
  vi.mocked(fetchMealPlans).mockResolvedValue([previous]);
  vi.mocked(fetchAllMealPlanItems).mockResolvedValue([source]);
  vi.mocked(createPlanFromTemplate).mockResolvedValue({ plan: { ...previous, id: 'new-menu', intervention_plan_id: 'new-intervention' }, items: [source], preview: false, compatibility: { goal_matches: true, disease_stage_matches: true, maternal_status_matches: true, warning: null } });
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  const targets = { energy: 1800, protein: 70, carbs: 250, fat: 55, fluid: 2000 };
  await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="" prescriptionTargets={targets} />));
  try {
    expect(container.textContent).toContain('Previous breakfast');
    expect(createPlanFromTemplate).not.toHaveBeenCalled();
    await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="new-intervention" newlySavedInterventionId="new-intervention" prescriptionTargets={targets} />));
    expect(createPlanFromTemplate).toHaveBeenCalledWith('ncp-1', expect.objectContaining({ intervention_plan_id: 'new-intervention', source_meal_plan_id: 'previous-menu', preview: false }));
    expect(container.textContent).not.toContain('Save week');
    expect(source.quantity).toBe('1.2');
  } finally { await act(async () => root.unmount()); }
});

it("keeps a failed copied menu visible and allows retry without a Save week button", async () => {
  const previous: MealPlan = { id: 'previous-menu', intervention_plan_id: 'previous-intervention', week_start_date: '2026-10-12', generation_type: 'manual', scale_status: 'available', scaled_at: null, status: 'draft', days: [] };
  vi.mocked(fetchMealPlans).mockResolvedValue([previous]);
  vi.mocked(createPlanFromTemplate).mockRejectedValueOnce(new Error('Could not save copied menu.'));
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  const targets = { energy: 1800, protein: 70, carbs: 250, fat: 55, fluid: 2000 };
  await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="" prescriptionTargets={targets} />));
  try {
    await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="new-intervention" newlySavedInterventionId="new-intervention" prescriptionTargets={targets} />));
    expect(container.textContent).toContain('Could not save copied menu.');
    expect(container.textContent).not.toContain('Save week');
    vi.mocked(createPlanFromTemplate).mockResolvedValueOnce({ plan: { ...previous, id: 'new-menu', intervention_plan_id: 'new-intervention' }, items: [], preview: false, compatibility: { goal_matches: true, disease_stage_matches: true, maternal_status_matches: true, warning: null } });
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent === 'Retry')!.click());
    expect(createPlanFromTemplate).toHaveBeenCalledTimes(2);
  } finally { await act(async () => root.unmount()); }
});

it("ignores a late previous-menu lookup after switching care cycles", async () => {
  const previous: MealPlan = { id: 'previous-menu', intervention_plan_id: 'previous-intervention', week_start_date: '2026-10-12', generation_type: 'manual', scale_status: 'available', scaled_at: null, status: 'draft', days: [{ id: 'previous-day', day_of_week: 'Monday', meal_type: 'breakfast', flagged: false }] };
  vi.mocked(fetchMealPlans).mockResolvedValueOnce([previous]).mockResolvedValueOnce([]);
  let finish!: (items: MealPlanItem[]) => void;
  vi.mocked(fetchAllMealPlanItems).mockReturnValueOnce(new Promise((resolve) => { finish = resolve; }));
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  const targets = { energy: 1800, protein: 70, carbs: 250, fat: 55, fluid: 2000 };
  await act(async () => root.render(<MealPlanSection ncpId="old-cycle" interventionPlanId="" prescriptionTargets={targets} />));
  try {
    await act(async () => root.render(<MealPlanSection ncpId="new-cycle" interventionPlanId="" prescriptionTargets={targets} />));
    await act(async () => finish([{ id: 'previous-item', meal_plan_day_id: 'previous-day', recipe_id: 'recipe-1', food_item_id: null, fdc_id: null, source: 'recipe', ai_suggested: false, quantity: '1', unit: 'serving', nutrient_snapshot: { name: 'Wrong cycle breakfast', calories: 200, protein: 8, carbs: 35, fat: 4, serving_size: 1, serving_unit: 'serving', micronutrients: {} } }]));
    expect(container.textContent).not.toContain('Wrong cycle breakfast');
    expect(createPlanFromTemplate).not.toHaveBeenCalled();
  } finally { await act(async () => root.unmount()); }
});

it.each(['Create Menu', 'Load Template', 'Auto-Generate'])("retains %s through intervention save failure and resumes with the new UUID", async (action) => {
  vi.mocked(fetchMealPlans).mockResolvedValueOnce([]);
  const save = vi.fn().mockResolvedValueOnce(null).mockResolvedValueOnce('new-intervention');
  function NewIntervention() {
    const [id, setId] = React.useState('');
    return <MealPlanSection ncpId="ncp-new" interventionPlanId={id} newlySavedInterventionId={id} prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }}
      onSaveIntervention={async () => { const savedId = await save(); if (savedId) setId(savedId); return savedId; }} />;
  }
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  const button = (label: string) => Array.from(container.querySelectorAll('button')).find((row) => row.textContent?.trim() === label)!;
  await act(async () => root.render(<NewIntervention />));
  try {
    expect(container.textContent).toContain('Weekly Meal Plan');
    for (const label of ['Create Menu', 'Load Template', 'Auto-Generate']) expect(button(label)).toBeDefined();
    expect(fetchMealPlans).toHaveBeenCalledWith('ncp-new');
    const excludeSnacks = Array.from(container.querySelectorAll('label')).find((row) => row.textContent?.includes('Exclude snacks'))!.querySelector('input')!;
    await act(async () => excludeSnacks.click());
    await act(async () => button(action).click());
    expect(container.textContent).not.toContain('Save intervention and continue');
    expect(createMealPlan).not.toHaveBeenCalled(); expect(generateMealPlan).not.toHaveBeenCalled();
    expect(save).toHaveBeenCalledTimes(1);
    expect(excludeSnacks.checked).toBe(true);
    await act(async () => button(action).click());
    expect(save).toHaveBeenCalledTimes(2);
    if (action === 'Create Menu') expect(createMealPlan).toHaveBeenCalledWith('ncp-new', expect.objectContaining({ intervention_plan_id: 'new-intervention' }));
    if (action === 'Load Template') expect(container.textContent).toContain('No templates saved yet.');
    if (action === 'Auto-Generate') {
      expect(container.textContent).toContain('Choose Carb Menu Item');
      expect(generateMealPlan).not.toHaveBeenCalled();
      expect(excludeSnacks.checked).toBe(true);
    }
  } finally { await act(async () => root.unmount()); }
});

it.each([true, false])("resumes after a normal intervention save but never after canceling to an existing intervention (%s)", async (saved) => {
  vi.mocked(fetchMealPlans).mockResolvedValueOnce([]);
  function Workspace() {
    const [id, setId] = React.useState('');
    return <><button onClick={() => setId(saved ? 'new-intervention' : 'previous-intervention')}>Finish intervention</button>
      <MealPlanSection ncpId="ncp-new" interventionPlanId={id} newlySavedInterventionId={saved ? id : null} prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />
    </>;
  }
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<Workspace />));
  try {
    await act(async () => Array.from(container.querySelectorAll('button')).find((row) => row.textContent?.trim() === 'Create Menu')!.click());
    await act(async () => Array.from(container.querySelectorAll('button')).find((row) => row.textContent === 'Finish intervention')!.click());
    if (saved) expect(createMealPlan).toHaveBeenCalledWith('ncp-new', expect.objectContaining({ intervention_plan_id: 'new-intervention' }));
    else expect(createMealPlan).not.toHaveBeenCalled();
  } finally { await act(async () => root.unmount()); }
});

it("previews a template calendar and persists the selected template without extra draft actions", async () => {
  vi.mocked(fetchMealPlanTemplates).mockResolvedValueOnce({ data: [{ id: 'template-1', name: 'Measured week', description: null, goal_type: null, disease_stage: null, maternal_status: null, created_at: '' }], meta: null } as never);
  const plan: MealPlan = { id: 'loaded-menu', intervention_plan_id: 'intervention-1', week_start_date: '2026-10-19', generation_type: 'manual', scale_status: 'available', scaled_at: null, status: 'draft', days: [] };
  vi.mocked(createPlanFromTemplate).mockResolvedValueOnce({ plan, items: [], preview: false, compatibility: { goal_matches: true, disease_stage_matches: true, maternal_status_matches: true, warning: null } });
  const { container, root } = await renderEmptyPlanner();
  try {
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Load Template')!.click());
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Use')!.click());
    expect(createPlanFromTemplate).toHaveBeenCalledWith('ncp-1', expect.objectContaining({ template_id: 'template-1', preview: false }));
    expect(container.textContent).not.toContain('Save week');
    expect(container.textContent).not.toContain('Discard draft');
    expect(container.textContent).toContain('Clear menu');
    expect(container.textContent).toContain('Auto-Generate');
  } finally { await act(async () => root.unmount()); }
});

it("allows Exclude snacks for liver disease", async () => {
  const { container, root } = await renderEmptyPlanner("liver_disease");
  try {
    const label = Array.from(container.querySelectorAll("label")).find((element) => element.textContent?.includes("Exclude snacks"))!;
    expect(label.querySelector("input")?.disabled).toBe(false);
  } finally { await act(async () => root.unmount()); }
});

it("loads a template directly and preserves a nonempty saved week on load failure", async () => {
  vi.mocked(fetchMealPlanTemplates).mockResolvedValueOnce({ data: [{ id: "template-1", name: "Measured week", description: null, goal_type: null, disease_stage: null, maternal_status: null, created_at: "2026-10-15" }], meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 } });
  vi.mocked(fetchAllMealPlanItems).mockResolvedValueOnce([{ id: "saved-item", meal_plan_day_id: "day-1", recipe_id: "meal-1", food_item_id: null, fdc_id: null, source: "recipe", ai_suggested: false, quantity: "1.50", unit: "serving", nutrient_snapshot: { name: "Original breakfast", calories: 200, protein: 10, carbs: 20, fat: 5, serving_size: 1, serving_unit: "serving", micronutrients: {} } }]);
  vi.mocked(createPlanFromTemplate).mockRejectedValueOnce(new Error('Could not load this template.'));
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1" prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />));
  try {
    expect(container.textContent).toContain('Original breakfast');
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.includes('Load Template'))!.click());
    const use = Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Use')!;
    await act(async () => use.click());
    expect(container.querySelector('[aria-label="Replace weekly menu draft"]')).toBeNull();
    expect(createPlanFromTemplate).toHaveBeenCalledWith('ncp-1', expect.objectContaining({ meal_plan_id: 'plan-1', preview: false }));
    expect(container.textContent).toContain('Original breakfast');
    expect(container.textContent).toContain('Could not load this template.');
    expect(Array.from(container.querySelectorAll('button')).some((button) => button.textContent === 'Save week')).toBe(false);
  } finally { await act(async () => root.unmount()); }
});

it("ignores a late template response after changing intervention scope", async () => {
  vi.mocked(fetchMealPlanTemplates).mockResolvedValueOnce({ data: [{ id: "template-1", name: "Measured week", description: null, goal_type: null, disease_stage: null, maternal_status: null, created_at: "2026-10-15" }], meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 } });
  let finishPreview!: (value: Awaited<ReturnType<typeof createPlanFromTemplate>>) => void;
  vi.mocked(createPlanFromTemplate).mockImplementationOnce(() => new Promise((resolve) => { finishPreview = resolve; }));
  const { container, root } = await renderEmptyPlanner();
  try {
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.includes('Load Template'))!.click());
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Use')!.click());
    await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-2" prescriptionTargets={{ energy: 1800, protein: 70, carbs: 250, fat: 55, fluid: 2000 }} />));
    await act(async () => finishPreview({
      plan: { id: 'old-draft', intervention_plan_id: 'intervention-1', week_start_date: '2026-10-12', generation_type: 'manual', scale_status: 'available', scaled_at: null, status: 'draft', days: [] },
      items: [], preview: true, compatibility: { goal_matches: true, disease_stage_matches: true, maternal_status_matches: true, warning: null },
    }));
    expect(Array.from(container.querySelectorAll('button')).some((button) => button.textContent === 'Save week')).toBe(false);
  } finally { await act(async () => root.unmount()); }
});

it("keeps a historical week readable without template loading or edit actions", async () => {
  vi.mocked(fetchMealPlanTemplates).mockResolvedValueOnce({ data: [{ id: 'template-1', name: 'Measured week', description: null, goal_type: null, disease_stage: null, maternal_status: null, created_at: '2026-10-15' }], meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 } });
  vi.mocked(fetchAllMealPlanItems).mockResolvedValueOnce([{ id: 'saved-item', meal_plan_day_id: 'day-1', recipe_id: 'meal-1', food_item_id: null, fdc_id: null, source: 'recipe', ai_suggested: false, quantity: '1.50', unit: 'serving', nutrient_snapshot: { name: 'Historical breakfast', calories: 200, protein: 10, carbs: 20, fat: 5, serving_size: 1, serving_unit: 'serving', micronutrients: {} } }]);
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1" readOnly prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />));
  try {
    expect(container.textContent).toContain('Historical breakfast');
    expect(container.querySelector('table[aria-label="Weekly menu calendar"]')).not.toBeNull();
    expect(container.querySelector('[aria-label="View Historical breakfast"]')).not.toBeNull();
    expect(container.querySelector('button[title="Edit"]')).toBeNull();
    expect(Array.from(container.querySelectorAll('button')).some((button) => /Load Template|Save week|Save Template|Auto-Generate|Create Menu|^Add$/.test(button.textContent ?? ''))).toBe(false);
    expect(createPlanFromTemplate).not.toHaveBeenCalled();
  } finally { await act(async () => root.unmount()); }
});

it("preselects an available Carb before generation and allows changing it", async () => {
  const { container, root } = await renderEmptyPlanner();
  const carb = { id: "carb-1", rnd_user_id: 1, name: "Oats", category: "carb", meal_types: null, prep_notes: null, servings: 1, total_calories: "150", total_protein: "5", total_carbs: "25", total_fat: "2", prepared_portion_amount: null, prepared_portion_unit: null, micronutrients: {}, ingredients: [], created_at: "", updated_at: "" };
  vi.mocked(fetchRecipes).mockResolvedValueOnce({ data: [carb, { ...carb, id: "carb-2", name: "Sweet potato" }] });
  try {
    const generate = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("Auto-Generate"))!;
    await act(async () => generate.click());
    expect(generateMealPlan).not.toHaveBeenCalled();
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 300)); });
    const dialog = container.querySelector('[aria-label="Choose Carb Menu Item"]')!;
    expect(dialog).not.toBeNull();
    expect(fetchRecipes).toHaveBeenCalledWith("", "carb", 1);
    const confirm = Array.from(dialog.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Generate week")!;
    expect(confirm.disabled).toBe(false);
    const choices = dialog.querySelectorAll<HTMLInputElement>('input[type="radio"]');
    expect(choices[0].checked).toBe(true);
    await act(async () => choices[1].click());
    await act(async () => confirm.click());
    expect(generateMealPlan).toHaveBeenCalledWith("ncp-1", expect.objectContaining({ exclude_carbs: false, carb_menu_item_id: "carb-2" }));
  } finally { await act(async () => root.unmount()); }
});

it("generates without a Carb selection when Exclude carbs is checked", async () => {
  const { container, root } = await renderEmptyPlanner();
  try {
    const label = Array.from(container.querySelectorAll("label")).find((element) => element.textContent?.includes("Exclude carbs"));
    expect(label).toBeDefined();
    await act(async () => label!.querySelector("input")!.click());
    const generate = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("Auto-Generate"))!;
    await act(async () => generate.click());
    expect(generateMealPlan).toHaveBeenCalledWith("ncp-1", expect.objectContaining({ exclude_carbs: true }));
    expect(container.querySelector('[aria-label="Choose Carb Menu Item"]')).toBeNull();
  } finally { await act(async () => root.unmount()); }
});

it("offers Menu Items only and allows a Snack in a manually planned main slot", async () => {
  const container = document.createElement("div");
  document.body.appendChild(container);
  const root = createRoot(container);
  await act(async () => {
    root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1"
      prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />);
  });
  await act(async () => { await Promise.resolve(); });

  const calendar = container.querySelector('table[aria-label="Weekly menu calendar"]');
  expect(calendar).not.toBeNull();
  expect(calendar!.querySelectorAll('td[data-day]')).toHaveLength(35);

  const add = container.querySelector<HTMLButtonElement>('[data-menu-editor-add][aria-label^="Add item to Monday"]');
  expect(add).toBeDefined();
  expect(add?.textContent?.trim()).toBe("Add item");
  await act(async () => { add!.click(); });
  const input = container.querySelector<HTMLInputElement>('[role="dialog"] input[type="search"]');
  expect(input).not.toBeNull();
  await act(async () => { await new Promise((resolve) => setTimeout(resolve, 300)); });
  const picker = container.querySelector('[aria-label="Add Menu Item"]')!;
  expect(picker.textContent).not.toMatch(/USDA|Library|Recipes/);
  expect(fetchRecipes).toHaveBeenCalledWith("", "all", 1);
  expect(fetchFoodItems).not.toHaveBeenCalled();
  expect(searchUsda).not.toHaveBeenCalled();
  const addSnack = picker.querySelector<HTMLButtonElement>('[aria-label="Add Fruit cup"]')!;
  await act(async () => { addSnack.click(); });
  expect(addMealPlanItem).toHaveBeenCalledWith("ncp-1", "plan-1", "day-1", { recipe_id: "snack-1", quantity: 1, unit: "serving" });
  expect(container.textContent).toContain("Fruit cup");
  await act(async () => root.unmount());
});

it("shows scaled ingredient amounts per person and removes the generic scale slider", async () => {
  vi.mocked(fetchAllMealPlanItems).mockResolvedValueOnce([{
    id: "item-1",
    meal_plan_day_id: "day-1",
    food_item_id: null,
    fdc_id: null,
    recipe_id: "recipe-1",
    quantity: "1.5",
    unit: "serving",
    nutrient_snapshot: {
      name: "Rice bowl",
      calories: 200,
      protein: 8,
      carbs: 35,
      fat: 4,
      micronutrients: {},
      serving_size: 1,
      serving_unit: "serving",
    },
    ai_suggested: false,
    source: "recipe",
    recipe_portion: {
      yield: 4,
      ingredients: [{ name: "Rice", quantity: 400, unit: "g" }],
    },
  }]);

  const container = document.createElement("div");
  document.body.appendChild(container);
  const root = createRoot(container);
  vi.mocked(fetchRecipeById).mockResolvedValueOnce({
    id: "recipe-1", rnd_user_id: 1, name: "Rice bowl", category: "lunch", meal_types: null,
    prep_notes: null, servings: 4, total_calories: "800", total_protein: "32",
    total_carbs: "140", total_fat: "16", prepared_portion_amount: null,
    prepared_portion_unit: null, micronutrients: {}, ingredients: [{
      id: 1, food_item_id: 1, quantity: "0.4", unit: "kg", food_item: {
        id: "food-1", name: "Rice", calories: "200", protein: "8", carbs: "35",
        fat: "4", serving_size: "100", serving_unit: "g", micronutrients: {},
      },
    }], created_at: "", updated_at: "",
  });
  await act(async () => {
    root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1"
      prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />);
  });
  await act(async () => { await Promise.resolve(); });

  expect(container.textContent).toContain("1.5 serving");
  expect(container.textContent).not.toContain("Per person:");
  const breakfast = container.querySelector('[data-day="Monday"][data-meal="breakfast"]')!;
  expect(breakfast.textContent).not.toContain("kcal");
  expect(breakfast.querySelectorAll('[data-menu-editor-item]')).toHaveLength(1);
  expect(breakfast.querySelector('[data-menu-editor-item] > div > :first-child')?.hasAttribute('data-menu-editor-actions')).toBe(true);
  expect(breakfast.querySelectorAll('[data-menu-editor-add]')).toHaveLength(1);
  expect(breakfast.querySelector('[data-menu-editor-item]')?.className).toContain('h-32');
  expect(breakfast.querySelector('[data-menu-editor-add]')?.className).not.toContain('h-32');
  expect(breakfast.querySelector('[data-menu-editor-add]')?.previousElementSibling).toBe(breakfast.querySelector('[data-menu-editor-item]'));
  const edit = Array.from(container.querySelectorAll("button")).find((button) => button.title === "Edit");
  expect(edit).toBeDefined();
  await act(async () => { container.querySelector<HTMLButtonElement>('button[title="Edit"]')!.click(); });
  expect(container.textContent).not.toContain("Master Scale");
  expect(container.querySelector('input[type="range"]')).toBeNull();
  const ingredientTab = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Ingredients")!;
  await act(async () => ingredientTab.click());
  const editor = container.querySelector('[aria-label="Menu Item profile"]')!;
  expect(editor.className).not.toContain('fixed');
  expect(editor.textContent).toContain("Energy300 kcal");
  expect(editor.querySelector('input[aria-label^="Amount of"]')?.getAttribute("step")).toBe("0.1");
  const save = Array.from(editor.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Save Changes")!;
  await act(async () => save.click());
  expect(updateMealPlanItem).toHaveBeenCalledWith("ncp-1", "plan-1", "day-1", "item-1", { quantity: 1.5, ingredients: [{ food_item_id: 'food-1', quantity: 0.4, unit: 'kg' }] });

  await act(async () => root.unmount());
});

it("scales the nutrient preview and saved request when portion changes", async () => {
  vi.mocked(fetchRecipeById).mockResolvedValueOnce({
    id: "recipe-1", rnd_user_id: 1, name: "Rice bowl", category: "meal", meal_types: null, prep_notes: null,
    servings: 1, total_calories: "200", total_protein: "8", total_carbs: "35", total_fat: "4",
    prepared_portion_amount: null, prepared_portion_unit: null, micronutrients: {}, ingredients: [], created_at: "", updated_at: "",
  });
  vi.mocked(fetchAllMealPlanItems).mockResolvedValueOnce([{
    id: "scaled-item", meal_plan_day_id: "day-1", food_item_id: null, fdc_id: null, recipe_id: "recipe-1", quantity: "1.5", unit: "serving", ai_suggested: false, source: "recipe",
    nutrient_snapshot: { name: "Rice bowl", calories: 200, protein: 8, carbs: 35, fat: 4, serving_size: 1, serving_unit: "serving", micronutrients: {} },
  }]);
  const container = document.createElement("div"); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1" prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />));
  try {
    await act(async () => container.querySelector<HTMLButtonElement>('button[title="Edit"]')!.click());
    const portion = container.querySelector<HTMLInputElement>('[aria-label="Menu Item profile"] input[aria-label="Planned portion"]')!;
    await act(async () => {
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!.call(portion, "2");
      portion.dispatchEvent(new Event("input", { bubbles: true }));
      portion.dispatchEvent(new Event("change", { bubbles: true }));
    });
    expect(container.querySelector('[aria-label="Menu Item profile"]')?.textContent).toContain("Energy400 kcal");
    await act(async () => Array.from(container.querySelectorAll<HTMLButtonElement>('[aria-label="Menu Item profile"] button')).find((button) => button.textContent?.trim() === "Save Changes")!.click());
    expect(updateMealPlanItem).toHaveBeenCalledWith("ncp-1", "plan-1", "day-1", "scaled-item", { quantity: 2 });
  } finally { await act(async () => root.unmount()); }
});

it("edits saved ingredient amounts using the frozen nutrient basis without fetching the changed library recipe", async () => {
  vi.mocked(fetchAllMealPlanItems).mockResolvedValueOnce([{
    id: 'frozen-item', meal_plan_day_id: 'day-1', food_item_id: null, fdc_id: null, recipe_id: 'recipe-1', quantity: '1.2', unit: 'serving', ai_suggested: false, source: 'recipe',
    nutrient_snapshot: {
      name: 'Saved rice', calories: 80, protein: 1.6, carbs: 16, fat: 0.8, water_g: 56, micronutrients: {}, serving_size: 1, serving_unit: 'serving', recipe_yield: 5,
      recipe_original_ingredients: [{ id: 1, food_item_id: 1, name: 'Rice', quantity: 500, unit: 'g', food_item: { id: 'food-1', name: 'Rice', calories: '100', protein: '2', carbs: '20', fat: '1', water_g: '70', serving_size: '100', serving_unit: 'g', micronutrients: {} } }],
      recipe_ingredients: [{ id: 1, name: 'Rice', quantity: 400, unit: 'g' }],
    },
  }]);
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1" prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />));
  try {
    await act(async () => container.querySelector<HTMLButtonElement>('button[title="Edit"]')!.click());
    expect(fetchRecipeById).not.toHaveBeenCalled();
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Ingredients')!.click());
    expect(container.querySelector<HTMLInputElement>('[aria-label="Menu Item profile"] input[aria-label="Amount of Rice"]')?.value).toBe('96');
    expect(container.querySelector('[aria-label="Menu Item profile"]')?.textContent).toContain('Energy96 kcal');
    const amount = container.querySelector<HTMLInputElement>('[aria-label="Amount of Rice"]')!;
    await act(async () => {
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!.call(amount, '120');
      amount.dispatchEvent(new Event('input', { bubbles: true }));
    });
    expect(container.querySelector('[aria-label="Menu Item profile"]')?.textContent).toContain('Energy120 kcal');
    await act(async () => Array.from(container.querySelectorAll<HTMLButtonElement>('[aria-label="Menu Item profile"] button')).find((button) => button.textContent?.trim() === 'Save Changes')!.click());
    expect(updateMealPlanItem).toHaveBeenCalledWith('ncp-1', 'plan-1', 'day-1', 'frozen-item', { quantity: 1.2, ingredients: [{ food_item_id: 'food-1', quantity: 500, unit: 'g' }] });
  } finally { await act(async () => root.unmount()); }
});
