// @vitest-environment jsdom
import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { fireEvent as domEvent, screen, waitFor } from "@testing-library/dom";
import LogVisitForm from "./LogVisitForm";
import { fetchMonitoringAiBudget, requestMonitoringAiReview, type MonitoringContext } from "@/services/monitoringService";
import type { MonitoringPlan } from "@/services/monitoringPlan";

vi.mock("@/services/monitoringService", async (original) => ({
  ...await original<typeof import("@/services/monitoringService")>(),
  fetchMonitoringAiBudget: vi.fn(), requestMonitoringAiReview: vi.fn(),
}));
let root: Root;
beforeEach(() => {
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });
  const container = document.createElement("div");
  document.body.appendChild(container);
  root = createRoot(container);
});
afterEach(() => { act(() => root.unmount()); document.body.replaceChildren(); vi.resetAllMocks(); });
const fireEvent = {
  change: (element: Element, value: { target: { value: string } }) => act(() => { domEvent.change(element, value); }),
  click: (element: Element) => act(() => { domEvent.click(element); }),
};
const context: MonitoringContext = {
  source_type: "assessment", source_monitoring_id: null, source_monitoring_date: null,
  weight: 60, height: 170, edema_present: false, dry_weight_kg: null,
  physical_activity_level: "light", pregnancy_lactation_status: "none",
  allergies: [], dietary_restrictions: null, food_dislikes: [], age_years: 40, sex: "Male",
};
const plan: MonitoringPlan = {
  visits: [], pes_statements: [], goal_type: "custom", nutritional_status: null,
  indicators: [{ key: "protein_g", label: "Protein intake", unit: "g", category: "intake", sources: [], reference: null, target: 80, series: [], latest_status: "no_data" },
    { key: "micro_fiber", label: "Fiber intake", unit: "g", category: "intake", sources: [], reference: { min: 25, max: 35 }, target: null, series: [], latest_status: "no_data" }],
};
async function open(remaining = 3) {
  vi.mocked(fetchMonitoringAiBudget).mockResolvedValue({ entry_key: "entry", remaining });
  const save = vi.fn();
  await act(async () => { root.render(<LogVisitForm ncpId="ncp" plan={plan} context={context} intervention={null} onSubmit={save} onCancel={() => undefined} />); });
  return save;
}

describe("draft monitoring summary", () => {
  it("sends current unsaved intake, preserves manual text, and applies an editable suggestion", async () => {
    const save = await open();
    vi.mocked(requestMonitoringAiReview).mockResolvedValue({ narrative: "Intake improved; protein remains below target.", remaining: 2 });
    fireEvent.change(screen.getByRole("spinbutton", { name: /Protein intake/ }), { target: { value: "65" } });
    const summary = screen.getByRole("textbox", { name: "Progress Assessment" }) as HTMLTextAreaElement;
    fireEvent.change(summary, { target: { value: "Manual note." } });
    const generate = screen.getByRole("button", { name: "Generate AI Summary" }) as HTMLButtonElement;
    await waitFor(() => expect(generate.disabled).toBe(false));
    fireEvent.click(generate);
    await screen.findByRole("button", { name: "Use this summary" });
    expect(summary.value).toBe("Manual note.");
    expect(requestMonitoringAiReview).toHaveBeenCalledWith("ncp", expect.objectContaining({ lab_values: { protein_g: 65 }, entry_key: "entry" }));
    expect(save).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole("button", { name: "Use this summary" }));
    expect(summary.value).toContain("Intake improved");
    fireEvent.change(summary, { target: { value: "Reviewed summary." } });
    expect(summary.value).toBe("Reviewed summary.");
    expect(screen.getByText("2 generations left for this entry")).toBeTruthy();
    expect(screen.getByText(/25–35 g/)).toBeTruthy();
    expect(screen.queryByRole("spinbutton", { name: /Energy intake/ })).toBeNull();
  });

  it("discards a response generated for changed inputs", async () => {
    await open();
    let resolve!: (value: { narrative: string; remaining: number }) => void;
    vi.mocked(requestMonitoringAiReview).mockReturnValue(new Promise((done) => { resolve = done; }));
    const generate = screen.getByRole("button", { name: "Generate AI Summary" }) as HTMLButtonElement;
    await waitFor(() => expect(generate.disabled).toBe(false));
    fireEvent.click(generate);
    fireEvent.change(screen.getByRole("spinbutton", { name: /Protein intake/ }), { target: { value: "70" } });
    await act(async () => { resolve({ narrative: "Outdated summary.", remaining: 2 }); });
    await screen.findByText(/Entry values changed during generation/);
    expect((screen.getByRole("textbox", { name: "Progress Assessment" }) as HTMLTextAreaElement).value).toBe("");
  });

  it("keeps manual entry available after the server allowance is exhausted", async () => {
    await open(0);
    await screen.findByText("0 generations left for this entry");
    expect((screen.getByRole("button", { name: "Generate AI Summary" }) as HTMLButtonElement).disabled).toBe(true);
    expect((screen.getByRole("textbox", { name: "Progress Assessment" }) as HTMLTextAreaElement).disabled).toBe(false);
  });
});
