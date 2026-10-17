// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import { instantiateTemplate, listCycles, getCycle, listTemplates, getTemplate, saveCycle, saveTemplate, getRecipeProfile, getFsItemProfile, type MenuCycle, type TemplateDetail, type CycleListItem, type TemplateListItem } from "@/services/menuCycleService";
import MenuCyclePage, { MenuTemplateDirectory } from "./page";

vi.mock("next/navigation", () => ({
  useSearchParams: (() => {
    const params = new URLSearchParams();
    return () => params;
  })(),
  useRouter: () => ({ push: vi.fn() }),
}));
vi.mock("@/contexts/AuthContext", () => ({ useAuth: () => ({ user: { role: "RND" } }) }));
vi.mock("@/services/menuCycleService", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/services/menuCycleService")>(),
  listCycles: vi.fn(),
  getCycle: vi.fn(),
  saveCycle: vi.fn(),
  listTemplates: vi.fn(),
  getTemplate: vi.fn(),
  saveTemplate: vi.fn(),
  instantiateTemplate: vi.fn(),
  getRecipeProfile: vi.fn(),
  getFsItemProfile: vi.fn(),
}));
vi.mock("@/services/consumptionService", () => ({ setServedPopulation: vi.fn(), listServiceLogs: vi.fn().mockResolvedValue([]) }));

const mockListCycles = vi.mocked(listCycles);
const mockGetCycle = vi.mocked(getCycle);
const mockListTemplates = vi.mocked(listTemplates);
const mockGetTemplate = vi.mocked(getTemplate);
const mockInstantiateTemplate = vi.mocked(instantiateTemplate);
const mockSaveCycle = vi.mocked(saveCycle);
const mockSaveTemplate = vi.mocked(saveTemplate);
const meta = { current_page: 1, per_page: 10, total: 0, last_page: 1 };
const cycle: CycleListItem = {
  id: "cycle-1", name: "Lunch cycle", status: "draft", is_active: false, week_start_date: null,
  plan_days: {}, days_count: 0, updated_at: "2026-10-04",
};
const templateSummary: TemplateListItem = {
  id: "template-1", name: "Rice plan", description: null, cycle_days: 7, days_count: 1, updated_at: "2026-10-04",
};
const template: TemplateDetail = {
  ...templateSummary,
  days: [{ day_of_week: "Monday", meal_type: "lunch", line_order: 1, recipe_id: null, fs_item_id: "rice-id", quantity: 1, fs_item: { id: "rice-id", name: "Rice" } }],
};
function makeCycle(name = "Lunch cycle"): MenuCycle {
  return { id: "cycle-1", name, cycle_days: 7, status: "draft", is_active: false, week_start_date: null, days: [] };
}

describe("Menu Cycle template and name editing", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    localStorage.clear();
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
    mockListCycles.mockResolvedValue({ data: [cycle], meta: { ...meta, total: 1 } });
    mockGetCycle.mockResolvedValue(makeCycle());
    mockListTemplates.mockResolvedValue({ data: [templateSummary], meta: { ...meta, total: 1 } });
    mockGetTemplate.mockResolvedValue(template);
    mockSaveCycle.mockImplementation(async (id, payload) => makeCycle(payload.name ?? "Lunch cycle"));
    mockSaveTemplate.mockResolvedValue(template);
    vi.mocked(getFsItemProfile).mockResolvedValue({ id: 1, fs_item_id: 1, name: "Rice", kind: "ingredient", category: null, unit: "g", unit_cost: 1, quantity: 1, population: 1, servings: 1, total_quantity: 1, total_cost: 1, cost_per_head: 1, prep_notes: "Measured rice", formula: "", ingredient_usage: [{ fs_item_id: 1, name: "Rice", quantity: 1, unit: "g", cost: 1 }] });
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
    vi.clearAllMocks();
  });

  async function renderPage() {
    await act(async () => { root.render(<MenuCyclePage />); await Promise.resolve(); await Promise.resolve(); });
  }

  async function openNewCycle() {
    const button = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.includes("Add week"));
    await act(async () => { button?.click(); });
  }

  test("keeps templates outside the Menu Cycle records table", async () => {
    await renderPage();
    expect(mockListTemplates).not.toHaveBeenCalled();
    expect(container.textContent).toContain("Menu Calendar");
    expect(container.textContent).not.toContain("Menu Cycle");
    const cycleRows = Array.from(container.querySelectorAll("table tbody tr"));
    expect(cycleRows).toHaveLength(1);
    expect(cycleRows[0].textContent).toContain("Lunch cycle");
    expect(cycleRows[0].textContent).not.toContain("Rice plan");
    expect(container.querySelector("thead")?.textContent).not.toContain("Per-day plan");
    expect(cycleRows[0].textContent).not.toContain("planned");
  });

  test("hides snack rows without removing saved menu lines and remembers the browser preference", async () => {
    const snack = { id: "snack-line", day_of_week: "Monday", meal_type: "am_snack", line_order: 1, recipe_id: null, fs_item_id: "snack-id", quantity: 1, fs_item: { id: "snack-id", name: "Banana snack" } };
    mockGetCycle.mockResolvedValue({ ...makeCycle(), days: [snack] } as MenuCycle);
    await renderPage();
    await act(async () => { container.querySelector<HTMLButtonElement>('button[title="Edit"]')!.click(); });
    const toggle = container.querySelector<HTMLInputElement>('input[aria-label="Show snack slots"]');
    expect(toggle).not.toBeNull();
    await act(async () => { toggle!.click(); });
    expect(container.textContent).not.toContain("AM Snack");
    expect(container.textContent).not.toContain("PM Snack");
    await act(async () => { Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "Save")!.click(); });
    expect(mockSaveCycle).toHaveBeenCalledWith("cycle-1", expect.objectContaining({ days: [expect.objectContaining({ meal_type: "am_snack", fs_item_id: "snack-id" })] }));
    expect(localStorage.getItem("nutriscope.food-service.show-snacks")).toBe("false");
  });

  test("explains the difference between estimated and served population accessibly", async () => {
    await renderPage();
    const hint = container.querySelector<HTMLButtonElement>('button[aria-label="About menu population"]');
    expect(hint).not.toBeNull();
    await act(async () => { await userEvent.setup().hover(hint!); });
    await vi.waitFor(() => expect(document.body.textContent).toContain("Suggested lists use estimated population to scale planned food. Served population records actual attendance."));
  });

  test("saves an unsaved Menu Plan as a template without creating a cycle", async () => {
    const user = userEvent.setup();
    await renderPage();
    await openNewCycle();
    const name = container.querySelector<HTMLInputElement>('input[aria-label="Menu name"]');
    if (!name) throw new Error("Menu Cycle name field missing.");
    await act(async () => { await user.type(name, "Lunch plan"); });
    const saveAsTemplate = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.includes("Save as Template"));
    await act(async () => { saveAsTemplate?.click(); await Promise.resolve(); });
    const templateName = container.querySelector<HTMLInputElement>('#menu-cycle-save-template-name');
    if (!templateName) throw new Error("Template name field missing.");
    await act(async () => { await user.clear(templateName); await user.type(templateName, "Reusable menu"); });
    const saveTemplateButton = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Save template");
    await act(async () => { saveTemplateButton?.click(); await Promise.resolve(); });

    expect(mockSaveCycle).not.toHaveBeenCalled();
    expect(mockSaveTemplate).toHaveBeenCalledWith(null, expect.objectContaining({ name: "Reusable menu", cycle_days: 7 }));
  });

  test("loads selected template into the current Menu Plan draft", async () => {
    await renderPage();
    await openNewCycle();
    const templates = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Templates");
    await act(async () => { templates?.click(); await Promise.resolve(); });
    const view = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "View");
    await act(async () => { view?.click(); await Promise.resolve(); });
    const dialog = container.querySelector<HTMLElement>('[role="dialog"]')!;
    expect(Array.from(dialog.querySelectorAll("thead th")).map((cell) => cell.textContent)).toEqual(["Meal", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]);
    expect(dialog.querySelector('[data-day="Monday"][data-meal="lunch"]')?.textContent).toContain("Rice");
    expect(dialog.textContent).not.toContain("add item");
    expect(dialog.querySelector('button[aria-label="Remove Rice"]')).toBeNull();
    const load = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Load this template");
    await act(async () => { load?.click(); await Promise.resolve(); });

    expect(mockGetTemplate).toHaveBeenCalledWith("template-1");
    expect(mockInstantiateTemplate).not.toHaveBeenCalled();
    expect(container.textContent).toContain("Rice");
  });

  test("opens a read-only item profile from the template calendar and returns without saving", async () => {
    await act(async () => { root.render(<MenuTemplateDirectory />); await Promise.resolve(); });
    await act(async () => { container.querySelector<HTMLButtonElement>('button[aria-label="Edit Rice plan"]')?.click(); await Promise.resolve(); });
    expect(container.querySelector('button[title^="Copy "]')).toBeNull();
    const rice = container.querySelector<HTMLButtonElement>('[data-menu-editor-open][aria-label="View Rice"]');
    expect(rice?.disabled).toBe(false);
    await act(async () => { rice?.focus(); await userEvent.setup().keyboard("{Enter}"); await Promise.resolve(); });
    expect(getFsItemProfile).toHaveBeenCalledWith("rice-id", 1, 1);
    const dialog = container.querySelector<HTMLElement>('[aria-label="Menu Item profile"]')!;
    expect(container.querySelector('[role="dialog"]')).toBeNull();
    expect(dialog.textContent).toContain("Measured rice");
    expect(dialog.querySelector("input,textarea")).toBeNull();
    await act(async () => { dialog.querySelector<HTMLButtonElement>('button[aria-label="Back to template"]')?.click(); });
    expect(container.querySelector('[role="dialog"]')).toBeNull();
    expect(document.activeElement?.getAttribute('aria-label')).toBe('View Rice');
    expect(mockSaveTemplate).not.toHaveBeenCalled();
    expect(mockSaveCycle).not.toHaveBeenCalled();
  });

  test("shows recipe profile from picker View, then returns to the same calendar", async () => {
    const name = "Chicken with vegetables and a long descriptive Food Service recipe name";
    mockGetTemplate.mockResolvedValue({ ...template, days: [{ day_of_week: "Monday", meal_type: "lunch", line_order: 1, recipe_id: "recipe-1", fs_item_id: null, quantity: 1, recipe: { id: "recipe-1", name } }] });
    vi.mocked(getRecipeProfile).mockResolvedValue({ recipe_id: 1, name, prep_notes: "Steam before service", servings: 5, population: 1, total_cost: 20, cost_per_head: 20, ingredient_usage: [{ fs_item_id: 1, name: "Chicken", quantity: 100, unit: "g", cost: 20 }] });
    await renderPage(); await openNewCycle();
    await act(async () => { Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "Templates")?.click(); await Promise.resolve(); });
    await act(async () => { Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "View")?.click(); await Promise.resolve(); });
    await act(async () => { container.querySelector<HTMLButtonElement>(`[data-menu-editor-open][aria-label="View ${name}"]`)?.click(); await Promise.resolve(); });
    expect(getRecipeProfile).toHaveBeenCalledWith("recipe-1", 1);
    expect(container.querySelector('[aria-label="Menu Item profile"]')?.textContent).toContain("Steam before service");
    await act(async () => { container.querySelector<HTMLButtonElement>('button[aria-label="Back to template"]')?.click(); });
    expect(container.querySelector('[role="dialog"] table thead')?.textContent).toContain("Mon");
    expect(container.querySelector('[role="dialog"]')?.textContent).toContain(name);
    expect(mockSaveCycle).not.toHaveBeenCalled();
  });

  test("keeps replacement confirmation and removes copy-day controls from Menu Calendar", async () => {
    const populated = { ...makeCycle(), days: [{ id: "line-1", day_of_week: "Monday" as const, meal_type: "lunch" as const, line_order: 1, recipe_id: null, fs_item_id: "existing", quantity: 1, servings_override: null, estimate_population: null, is_event: false, event_allocation: null, fs_item: { id: "existing", name: "Existing dish" } }] };
    mockGetCycle.mockResolvedValue(populated);
    const confirm = vi.spyOn(window, "confirm").mockReturnValue(false);
    await renderPage();
    await act(async () => { container.querySelector<HTMLButtonElement>('button[title="Edit"]')?.click(); await Promise.resolve(); });
    expect(container.querySelector('button[title^="Copy "]')).toBeNull();
    await act(async () => { Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "Templates")?.click(); await Promise.resolve(); });
    await act(async () => { Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "Load")?.click(); await Promise.resolve(); });
    expect(confirm).toHaveBeenCalledWith("Load this template and replace the current Menu Plan?");
    expect(container.textContent).toContain("Existing dish");
    expect(mockSaveCycle).not.toHaveBeenCalled();
    confirm.mockRestore();
  });

  test("keeps an empty template calendar readable", async () => {
    mockGetTemplate.mockResolvedValue({ ...template, days: [] });
    await renderPage(); await openNewCycle();
    await act(async () => { Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "Templates")?.click(); await Promise.resolve(); });
    await act(async () => { Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "View")?.click(); await Promise.resolve(); });
    const dialog = container.querySelector<HTMLElement>('[role="dialog"]')!;
    expect(dialog.textContent).toContain("This template has no planned items.");
    expect(dialog.querySelectorAll("tbody tr")).toHaveLength(5);
    expect(dialog.querySelector('[aria-label="Show snack slots"]')).not.toBeNull();
  });

  test("keeps the menu name read-only in the list and edits it inside the calendar", async () => {
    await renderPage();
    const rename = container.querySelector<HTMLButtonElement>('button[aria-label="Rename Lunch cycle"]');
    expect(rename).toBeNull();
    expect(container.querySelector<HTMLInputElement>('input[aria-label="Edit menu name"]')).toBeNull();
    const edit = container.querySelector<HTMLButtonElement>('button[title="Edit"]');
    await act(async () => { edit?.click(); await Promise.resolve(); });
    expect(container.querySelector<HTMLInputElement>('input[aria-label="Menu name"]')).not.toBeNull();
  });

  test("maps Tuesday's served population to Tuesday's calendar date", async () => {
    mockGetCycle.mockResolvedValue({ ...makeCycle(), week_start_date: "2026-09-28" });
    await renderPage();
    const edit = container.querySelector<HTMLButtonElement>('button[title="Edit"]');
    await act(async () => { edit?.click(); await Promise.resolve(); });
    const served = container.querySelector<HTMLButtonElement>('button[title="Edit Tuesday served population"]');
    await act(async () => { served?.click(); });
    expect(container.querySelector<HTMLInputElement>('input[title="Tuesday served population (2026-09-29)"]')).not.toBeNull();
  });

  test("shows a purchased menu for review while keeping served population editable", async () => {
    mockGetCycle.mockResolvedValue({ ...makeCycle(), week_start_date: "2026-10-05", days: [{
      id: "line-1", day_of_week: "Monday", meal_type: "lunch", line_order: 1,
      recipe_id: null, fs_item_id: "rice-id", quantity: 1, servings_override: null,
      estimate_population: null, is_event: false, event_allocation: null,
      fs_item: { id: "rice-id", name: "Rice" }, po_snapshot: { name: "Rice" }, po_snapshot_locked: true,
    }] });
    await renderPage();
    await act(async () => { container.querySelector<HTMLButtonElement>('button[title="Edit"]')?.click(); await Promise.resolve(); });
    expect(container.textContent).toContain("Purchasing started");
    expect(container.querySelector<HTMLInputElement>('input[aria-label="Menu name"]')?.readOnly).toBe(true);
    expect(Array.from(container.querySelectorAll("button")).some((button) => button.textContent?.trim() === "Save")).toBe(false);
    expect(container.querySelector<HTMLButtonElement>('button[aria-label="Remove Rice"]')).toBeNull();
    expect(container.querySelector<HTMLButtonElement>('button[title="Edit Monday served population"]')).not.toBeNull();
  });

  test("hides the whole-menu read-only label while any purchased menu item remains unlocked", async () => {
    mockGetCycle.mockResolvedValue({ ...makeCycle(), week_start_date: "2026-10-05", days: [
      {
        id: "line-locked", day_of_week: "Monday", meal_type: "lunch", line_order: 1,
        recipe_id: null, fs_item_id: "rice-id", quantity: 1, servings_override: null,
        estimate_population: null, is_event: false, event_allocation: null,
        fs_item: { id: "rice-id", name: "Rice" }, po_snapshot: { name: "Rice" }, po_snapshot_locked: true,
      },
      {
        id: "line-editable", day_of_week: "Monday", meal_type: "lunch", line_order: 2,
        recipe_id: null, fs_item_id: "beans-id", quantity: 1, servings_override: null,
        estimate_population: null, is_event: false, event_allocation: null,
        fs_item: { id: "beans-id", name: "Beans" }, po_snapshot: null, po_snapshot_locked: false,
      },
    ] });
    await renderPage();
    await act(async () => { container.querySelector<HTMLButtonElement>('button[title="Edit"]')?.click(); await Promise.resolve(); });
    expect(container.textContent).not.toContain("Purchasing started · View only");
    expect(container.querySelector<HTMLButtonElement>('button[aria-label="Remove Beans"]')).toBeNull();
    expect(container.querySelector<HTMLButtonElement>('button[aria-label="Remove Rice"]')).toBeNull();
  });

  test("template editor hides snack rows and saves three-meal templates", async () => {
    mockGetTemplate.mockResolvedValue({ ...template, days: template.days });
    await act(async () => { root.render(<MenuTemplateDirectory />); await Promise.resolve(); });
    expect(mockListCycles).not.toHaveBeenCalled();
    const edit = container.querySelector<HTMLButtonElement>('button[aria-label="Edit Rice plan"]');
    await act(async () => { edit?.click(); await Promise.resolve(); });
    const lunch = container.querySelector('[data-day="Monday"][data-meal="lunch"]')!;
    expect(lunch.querySelectorAll('[data-menu-editor-item]')).toHaveLength(1);
    expect(lunch.querySelectorAll('[data-menu-editor-add]')).toHaveLength(1);
    expect(lunch.querySelector('[data-menu-editor-item] > div > :first-child')?.hasAttribute('data-menu-editor-actions')).toBe(true);
    expect(lunch.querySelector('[data-menu-editor-add]')?.textContent?.trim()).toBe("Add item");
    expect(lunch.querySelector('[data-menu-editor-add]')?.getAttribute("aria-label")).toBe("Add item to Monday Lunch");
    expect(Array.from(lunch.querySelectorAll<HTMLElement>('[data-menu-editor-item], [data-menu-editor-add]')).every((slot) => slot.className.includes('h-32'))).toBe(true);
    expect(lunch.querySelector('[data-menu-editor-add]')?.previousElementSibling).toBe(lunch.querySelector('[data-menu-editor-item]'));
    const toggle = container.querySelector<HTMLInputElement>('input[aria-label="Show snack slots"]')!;
    expect(toggle.checked).toBe(true);
    await act(async () => toggle.click());
    expect(container.textContent).not.toContain("AM Snack");
    expect(container.textContent).not.toContain("PM Snack");
    const save = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Save");
    await act(async () => { save?.click(); await Promise.resolve(); });
    expect(mockSaveTemplate).toHaveBeenCalledWith("template-1", expect.objectContaining({
      days: [expect.objectContaining({ meal_type: "lunch" })],
    }));
  });

  test("provides an explicit return from Templates to Menu Calendar", async () => {
    await act(async () => { root.render(<MenuTemplateDirectory />); await Promise.resolve(); });
    const back = container.querySelector<HTMLAnchorElement>('a[aria-label="Back to Menu Calendar"]');
    expect(back?.getAttribute("href")).toBe("/food-service/menu-cycle");
    expect(back?.className).toContain("rounded-lg border border-warm-200");
    expect(back?.querySelector("svg")).not.toBeNull();
  });
});
