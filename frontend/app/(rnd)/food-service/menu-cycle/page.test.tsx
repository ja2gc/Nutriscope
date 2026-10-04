// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import { instantiateTemplate, listCycles, listTemplates, getTemplate, saveCycle, saveTemplate, type MenuCycle, type TemplateDetail, type CycleListItem, type TemplateListItem } from "@/services/menuCycleService";
import MenuCyclePage from "./page";

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
}));
vi.mock("@/services/consumptionService", () => ({ setServedPopulation: vi.fn(), listServiceLogs: vi.fn().mockResolvedValue([]) }));

const mockListCycles = vi.mocked(listCycles);
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
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
    mockListCycles.mockResolvedValue({ data: [cycle], meta: { ...meta, total: 1 } });
    mockListTemplates.mockResolvedValue({ data: [templateSummary], meta: { ...meta, total: 1 } });
    mockGetTemplate.mockResolvedValue(template);
    mockSaveCycle.mockImplementation(async (id, payload) => makeCycle(payload.name ?? "Lunch cycle"));
    mockSaveTemplate.mockResolvedValue(template);
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
      .find((candidate) => candidate.textContent?.includes("New Cycle"));
    await act(async () => { button?.click(); });
  }

  test("keeps templates outside the Menu Cycle records table", async () => {
    await renderPage();
    const cycleRows = Array.from(container.querySelectorAll("table tbody tr"));
    expect(cycleRows).toHaveLength(1);
    expect(cycleRows[0].textContent).toContain("Lunch cycle");
    expect(cycleRows[0].textContent).not.toContain("Rice plan");
    expect(container.querySelector("thead")?.textContent).not.toContain("Per-day plan");
    expect(cycleRows[0].textContent).not.toContain("planned");
  });

  test("saves an unsaved Menu Plan as a template without creating a cycle", async () => {
    const user = userEvent.setup();
    await renderPage();
    await openNewCycle();
    const name = container.querySelector<HTMLInputElement>('input[aria-label="Menu Cycle name"]');
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
    expect(container.textContent).toContain("Monday · Lunch");
    const load = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Load this template");
    await act(async () => { load?.click(); await Promise.resolve(); });

    expect(mockGetTemplate).toHaveBeenCalledWith("template-1");
    expect(mockInstantiateTemplate).not.toHaveBeenCalled();
    expect(container.textContent).toContain("Rice");
  });

  test("saves inline Menu Cycle name on blur and shows returned name", async () => {
    const user = userEvent.setup();
    await renderPage();
    const rename = container.querySelector<HTMLButtonElement>('button[aria-label="Rename Lunch cycle"]');
    expect(rename).not.toBeNull();
    await act(async () => { rename?.click(); });
    const name = container.querySelector<HTMLInputElement>('input[aria-label="Edit Menu Cycle name"]');
    if (!name) throw new Error("Inline Menu Cycle name field missing.");
    await act(async () => { await user.clear(name); await user.type(name, "Dinner cycle"); });
    await act(async () => { name.blur(); await Promise.resolve(); });

    expect(mockSaveCycle).toHaveBeenCalledWith("cycle-1", { name: "Dinner cycle" });
    expect(container.textContent).toContain("Dinner cycle");
  });
});
