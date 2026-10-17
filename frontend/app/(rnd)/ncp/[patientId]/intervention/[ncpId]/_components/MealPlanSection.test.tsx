// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, expect, it, vi } from "vitest";
import MealPlanSection from "./MealPlanSection";
import { fetchFoodItems, fetchRecipes, fetchRecipeById, searchUsda } from "@/services/foodLibraryService";
import { addMealPlanItem, createMealPlan, createPlanFromTemplate, fetchAllMealPlanItems, fetchMealPlanTemplate, fetchMealPlanTemplates, fetchMealPlans, generateMealPlan, updateMealPlanItem } from "@/services/mealPlanService";
import type { MealPlan, MealPlanItem } from "@/services/mealPlanService";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

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
    expect(container.querySelector<HTMLInputElement>('.fixed input[type="number"]')?.value).toBe("1.67");
  } finally { await act(async () => root.unmount()); }
});

it("prefills the previous menu, retains edits through intervention save, and saves only an independent week", async () => {
  const previous: MealPlan = { id: 'previous-menu', intervention_plan_id: 'previous-intervention', week_start_date: '2026-10-12', generation_type: 'manual', scale_status: 'already_scaled', scaled_at: '2026-10-12', status: 'draft', days: [{ id: 'previous-day', day_of_week: 'Monday', meal_type: 'breakfast', flagged: false }] };
  const sourceItem: MealPlanItem = { id: 'previous-item', meal_plan_day_id: 'previous-day', recipe_id: 'recipe-1', food_item_id: null, fdc_id: null, source: 'recipe', ai_suggested: false, quantity: '1.2', unit: 'serving', nutrient_snapshot: { name: 'Previous rice bowl', calories: 200, protein: 8, carbs: 35, fat: 4, serving_size: 1, serving_unit: 'serving', micronutrients: {} } };
  sourceItem.nutrient_snapshot = {
    ...sourceItem.nutrient_snapshot!, recipe_yield: 5,
    recipe_original_ingredients: [{ id: 1, food_item_id: 1, name: 'Rice', quantity: 500, unit: 'g', food_item: { id: 'food-1', name: 'Rice', calories: '200', protein: '8', carbs: '35', fat: '4', serving_size: '100', serving_unit: 'g', micronutrients: {} } }],
    recipe_ingredients: [{ id: 1, name: 'Rice', quantity: 500, unit: 'g' }],
  };
  vi.mocked(fetchMealPlans).mockResolvedValueOnce([previous]).mockResolvedValueOnce([previous]);
  vi.mocked(fetchAllMealPlanItems).mockResolvedValueOnce([sourceItem]);
  vi.mocked(createPlanFromTemplate).mockRejectedValueOnce(new Error('Week save failed.'))
    .mockResolvedValueOnce({ plan: { ...previous, id: 'new-menu', intervention_plan_id: 'new-intervention' }, items: [{ ...sourceItem, id: 'new-item', quantity: '1.5' }], preview: false, compatibility: { goal_matches: true, disease_stage_matches: true, maternal_status_matches: true, warning: null } });
  const save = vi.fn().mockResolvedValueOnce(null).mockResolvedValueOnce('new-intervention');
  function Workspace() {
    const [id, setId] = React.useState('');
    return <MealPlanSection ncpId="ncp-1" interventionPlanId={id} newlySavedInterventionId={id} prescriptionTargets={{ energy: 1800, protein: 70, carbs: 250, fat: 55, fluid: 2000 }} onSaveIntervention={async () => { const saved = await save(); if (saved) setId(saved); return saved; }} />;
  }
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  const button = (label: string) => Array.from(container.querySelectorAll('button')).find((row) => row.textContent?.trim() === label)!;
  await act(async () => root.render(<Workspace />));
  try {
    expect(container.textContent).toContain('Previous rice bowl');
    expect(createPlanFromTemplate).not.toHaveBeenCalled();
    expect(createMealPlan).not.toHaveBeenCalled();
    await act(async () => container.querySelector<HTMLButtonElement>('button[title="Edit"]')!.click());
    const quantity = container.querySelector<HTMLInputElement>('input[type="number"]')!;
    await act(async () => { Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!.call(quantity, '1.5'); quantity.dispatchEvent(new Event('input', { bubbles: true })); });
    await act(async () => button('Save Changes').click());
    expect(updateMealPlanItem).not.toHaveBeenCalled();
    await act(async () => button('Save week').click());
    await act(async () => button('Save intervention and continue').click());
    expect(createPlanFromTemplate).not.toHaveBeenCalled();
    expect(container.textContent).toContain('1.5 serving');
    await act(async () => button('Save intervention and continue').click());
    expect(container.textContent).toContain('Week save failed.');
    expect(container.textContent).toContain('1.5 serving');
    expect(createPlanFromTemplate).toHaveBeenCalledWith('ncp-1', expect.objectContaining({
      intervention_plan_id: 'new-intervention', source_meal_plan_id: 'previous-menu', preview: false,
      lines: [expect.objectContaining({ source_meal_plan_item_id: 'previous-item', recipe_id: 'recipe-1', quantity: 1.5 })],
    }));
    expect(JSON.stringify(vi.mocked(createPlanFromTemplate).mock.calls[0][1])).not.toContain('nutrient_snapshot');
    await act(async () => button('Save week').click());
    expect(button('Save week')).toBeUndefined();
    expect(sourceItem.quantity).toBe('1.2');
    expect(fetchAllMealPlanItems).not.toHaveBeenCalledWith('ncp-1', expect.stringContaining('draft'));
  } finally { await act(async () => root.unmount()); }
});

it("keeps the prefilled menu unsaved after normal intervention save and discards it on cancel to history", async () => {
  const previous: MealPlan = { id: 'previous-menu', intervention_plan_id: 'previous-intervention', week_start_date: '2026-10-12', generation_type: 'manual', scale_status: 'available', scaled_at: null, status: 'draft', days: [{ id: 'previous-day', day_of_week: 'Monday', meal_type: 'breakfast', flagged: false }] };
  const sourceItem: MealPlanItem = { id: 'previous-item', meal_plan_day_id: 'previous-day', recipe_id: 'recipe-1', food_item_id: null, fdc_id: null, source: 'recipe', ai_suggested: false, quantity: '1.2', unit: 'serving', nutrient_snapshot: { name: 'Previous breakfast', calories: 200, protein: 8, carbs: 35, fat: 4, serving_size: 1, serving_unit: 'serving', micronutrients: {} } };
  vi.mocked(fetchMealPlans).mockResolvedValueOnce([previous]).mockResolvedValueOnce([previous]).mockResolvedValueOnce([previous]);
  vi.mocked(fetchAllMealPlanItems).mockResolvedValueOnce([sourceItem]).mockResolvedValueOnce([sourceItem]);
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  const targets = { energy: 1800, protein: 70, carbs: 250, fat: 55, fluid: 2000 };
  await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="" prescriptionTargets={targets} />));
  try {
    expect(container.textContent).toContain('Previous breakfast');
    await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="new-intervention" newlySavedInterventionId="new-intervention" prescriptionTargets={targets} />));
    expect(container.textContent).toContain('Previous breakfast');
    expect(Array.from(container.querySelectorAll('button')).some((button) => button.textContent === 'Save week')).toBe(true);
    expect(createPlanFromTemplate).not.toHaveBeenCalled();
    await act(async () => root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="previous-intervention" readOnly prescriptionTargets={targets} />));
    expect(container.textContent).toContain('Previous breakfast');
    expect(Array.from(container.querySelectorAll('button')).some((button) => button.textContent === 'Save week')).toBe(false);
    expect(createPlanFromTemplate).not.toHaveBeenCalled();
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
    expect(container.textContent).toContain('Save the intervention before continuing');
    expect(createMealPlan).not.toHaveBeenCalled(); expect(generateMealPlan).not.toHaveBeenCalled();
    await act(async () => button('Save intervention and continue').click());
    expect(save).toHaveBeenCalledTimes(1);
    expect(button('Save intervention and continue')).toBeDefined();
    expect(excludeSnacks.checked).toBe(true);
    await act(async () => button('Save intervention and continue').click());
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

it("previews a template calendar and keeps the loaded baseline draft after a save failure", async () => {
  const header = { id: "template-1", name: "Measured week", description: null, goal_type: null, disease_stage: null, maternal_status: null, created_at: "2026-10-15" };
  vi.mocked(fetchMealPlanTemplates).mockResolvedValueOnce({ data: [header], meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 } });
  vi.mocked(fetchMealPlanTemplate).mockResolvedValueOnce({ ...header, days: [{ id: 1, day_of_week: "Monday", meal_type: "breakfast", quantity: "2.00", unit: "serving", food_name: "Oats", calories: 200, items: [
    { id: "template-line-1", recipe_id: "carb-1", food_item_id: null, quantity: "2.00", unit: "serving", food_name: "Oats", nutrient_snapshot: null, line_order: 0 },
  ] }] });
  vi.mocked(createPlanFromTemplate).mockResolvedValueOnce({
    plan: { id: "draft-week", intervention_plan_id: "intervention-1", week_start_date: "2026-10-12", generation_type: "manual", scale_status: "available", scaled_at: null, status: "draft", days: [{ id: "draft-day", day_of_week: "Monday", meal_type: "breakfast", flagged: false }] },
    items: [{ id: "draft-item", meal_plan_day_id: "draft-day", recipe_id: "carb-1", food_item_id: null, fdc_id: null, source: "recipe", ai_suggested: false, source_template_item_id: "template-line-1", quantity: "2.00", unit: "serving", nutrient_snapshot: { name: "Oats", calories: 200, protein: 10, carbs: 20, fat: 5, serving_size: 1, serving_unit: "serving", micronutrients: {} } }],
    preview: true, compatibility: { goal_matches: true, disease_stage_matches: true, maternal_status_matches: true, warning: null },
  }).mockRejectedValueOnce(new Error("Save failed. Try again."));
  const { container, root } = await renderEmptyPlanner();
  try {
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.includes('Load Template'))!.click());
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent === 'View')!.click());
    expect(container.querySelector('table[aria-label="Weekly menu calendar"]')).not.toBeNull();
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.includes('Use This Template'))!.click());
    expect(createPlanFromTemplate).toHaveBeenCalledTimes(1);
    expect(createPlanFromTemplate).toHaveBeenLastCalledWith('ncp-1', expect.objectContaining({ preview: true, template_id: 'template-1' }));
    expect(fetchAllMealPlanItems).not.toHaveBeenCalledWith('ncp-1', 'draft-week');
    expect(container.textContent).toContain('2 serving');
    const save = Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Save week')!;
    expect(save).toBeDefined();
    await act(async () => save.click());
    expect(container.textContent).toContain('Save failed. Try again.');
    expect(container.textContent).toContain('2 serving');
    expect(createPlanFromTemplate).toHaveBeenLastCalledWith('ncp-1', expect.objectContaining({
      preview: false, lines: [expect.objectContaining({ source_template_item_id: 'template-line-1', recipe_id: 'carb-1', quantity: 2, unit: 'serving' })],
    }));
    const loaded: Awaited<ReturnType<typeof createPlanFromTemplate>> = await vi.mocked(createPlanFromTemplate).mock.results[0].value;
    vi.mocked(createPlanFromTemplate).mockResolvedValueOnce({ ...loaded, plan: { ...loaded.plan, scale_status: 'already_scaled' }, items: loaded.items.map((item) => ({ ...item, quantity: '1.20' })) });
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Scale to prescription')!.click());
    expect(createPlanFromTemplate).toHaveBeenLastCalledWith('ncp-1', expect.objectContaining({ preview: true, scale: true }));
    expect(container.textContent).toContain('1.2 serving');
    vi.mocked(createPlanFromTemplate).mockRejectedValueOnce(new Error('Retry save.'));
    await act(async () => save.click());
    expect(createPlanFromTemplate).toHaveBeenLastCalledWith('ncp-1', expect.objectContaining({
      preview: false, scale: false, lines: [expect.objectContaining({ quantity: 1.2 })],
    }));
    const measuredRecipe = { id: 'carb-1', rnd_user_id: 1, name: 'Oats', category: 'carb', servings: 4, meal_types: null, prep_notes: null, total_calories: '800', total_protein: '40', total_carbs: '80', total_fat: '20', prepared_portion_amount: null, prepared_portion_unit: null, micronutrients: {}, ingredients: [{ id: 1, food_item_id: 1, quantity: '0.4', unit: 'kg', food_item: { id: 'food-1', name: 'Oats', calories: '200', protein: '10', carbs: '20', fat: '5', water_g: '70', serving_size: '100', serving_unit: 'g', micronutrients: {} } }], created_at: '', updated_at: '' };
    vi.mocked(fetchRecipeById).mockResolvedValueOnce(measuredRecipe);
    await act(async () => container.querySelector<HTMLButtonElement>('button[title="Edit"]')!.click());
    const quantity = container.querySelector<HTMLInputElement>('input[type="number"]')!;
    await act(async () => {
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!.call(quantity, '1.4');
      quantity.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Save Changes')!.click());
    vi.mocked(fetchRecipeById).mockResolvedValueOnce(measuredRecipe);
    await act(async () => container.querySelector<HTMLButtonElement>('button[title="Edit"]')!.click());
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Ingredients')!.click());
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Save Changes')!.click());
    expect(container.textContent).toContain('Fluid from planned foods, if this menu is followed: 98 mL');
    expect(container.textContent).not.toContain('Per person:');
    expect(updateMealPlanItem).not.toHaveBeenCalled();
    expect(container.textContent).toContain('1.4 serving');
    expect(createPlanFromTemplate).toHaveBeenCalledTimes(4);
    await act(async () => Array.from(container.querySelectorAll('button')).find((button) => button.textContent === 'Discard draft')!.click());
    expect(Array.from(container.querySelectorAll('button')).some((button) => button.textContent === 'Save week')).toBe(false);
  } finally { await act(async () => root.unmount()); }
});

it("allows Exclude snacks for liver disease", async () => {
  const { container, root } = await renderEmptyPlanner("liver_disease");
  try {
    const label = Array.from(container.querySelectorAll("label")).find((element) => element.textContent?.includes("Exclude snacks"))!;
    expect(label.querySelector("input")?.disabled).toBe(false);
  } finally { await act(async () => root.unmount()); }
});

it("asks before replacing a nonempty saved week and keeps it after cancel or load failure", async () => {
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
    let confirmation = container.querySelector('[aria-label="Replace weekly menu draft"]')!;
    expect(confirmation).not.toBeNull();
    expect(createPlanFromTemplate).not.toHaveBeenCalled();
    await act(async () => Array.from(confirmation.querySelectorAll('button')).find((button) => button.textContent === 'Cancel')!.click());
    expect(container.textContent).toContain('Original breakfast');
    expect(createPlanFromTemplate).not.toHaveBeenCalled();
    await act(async () => use.click());
    confirmation = container.querySelector('[aria-label="Replace weekly menu draft"]')!;
    await act(async () => Array.from(confirmation.querySelectorAll('button')).find((button) => button.textContent === 'Replace draft')!.click());
    expect(createPlanFromTemplate).toHaveBeenCalledWith('ncp-1', expect.objectContaining({ meal_plan_id: 'plan-1', preview: true }));
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
    expect(container.querySelector('a')?.getAttribute('href')).toBe('/food-library/recipes/meal-1');
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
  const input = container.querySelector<HTMLInputElement>('[aria-label="Search Menu Items"]');
  expect(input).not.toBeNull();
  await act(async () => { await new Promise((resolve) => setTimeout(resolve, 300)); });
  const picker = container.querySelector('[aria-label="Add Menu Item"]')!;
  expect(picker.textContent).not.toMatch(/USDA|Library|Recipes/);
  expect(fetchRecipes).toHaveBeenCalledWith("", "all", 1);
  expect(fetchFoodItems).not.toHaveBeenCalled();
  expect(searchUsda).not.toHaveBeenCalled();
  const addSnack = Array.from(picker.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Add")!;
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
  expect(Array.from(breakfast.querySelectorAll<HTMLElement>('[data-menu-editor-item], [data-menu-editor-add]')).every((slot) => slot.className.includes('h-32'))).toBe(true);
  expect(breakfast.querySelector('[data-menu-editor-add]')?.previousElementSibling).toBe(breakfast.querySelector('[data-menu-editor-item]'));
  const edit = Array.from(container.querySelectorAll("button")).find((button) => button.title === "Edit");
  expect(edit).toBeDefined();
  await act(async () => { container.querySelector<HTMLButtonElement>('button[title="Edit"]')!.click(); });
  expect(container.textContent).not.toContain("Master Scale");
  expect(container.querySelector('input[type="range"]')).toBeNull();
  const ingredientTab = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Ingredients")!;
  await act(async () => ingredientTab.click());
  const editor = container.querySelector('.fixed')!;
  expect(editor.textContent).toContain("Energy200kcal");
  expect(editor.querySelector('input[type="number"]')?.getAttribute("step")).toBe("0.000001");
  const save = Array.from(editor.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Save Changes")!;
  await act(async () => save.click());
  expect(updateMealPlanItem).toHaveBeenCalledWith("ncp-1", "plan-1", "day-1", "item-1", { quantity: 1.5, ingredient_overrides: [{ id: 1, quantity: 0.4 }] });

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
    const portion = container.querySelector<HTMLInputElement>('.fixed input[type="number"]')!;
    await act(async () => {
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!.call(portion, "2");
      portion.dispatchEvent(new Event("input", { bubbles: true }));
      portion.dispatchEvent(new Event("change", { bubbles: true }));
    });
    expect(container.querySelector('.fixed')?.textContent).toContain("Energy400kcal");
    await act(async () => Array.from(container.querySelectorAll<HTMLButtonElement>('.fixed button')).find((button) => button.textContent?.trim() === "Save Changes")!.click());
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
    expect(container.querySelector<HTMLInputElement>('.fixed input[type="number"]')?.value).toBe('400');
    expect(container.querySelector('.fixed')?.textContent).toContain('Energy80kcal');
  } finally { await act(async () => root.unmount()); }
});
