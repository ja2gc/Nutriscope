// @vitest-environment jsdom
import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { fireEvent, screen } from "@testing-library/dom";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import Page from "./page";
import { fetchPatients } from "@/services/patientService";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("@/services/patientService");
let root: Root;
beforeEach(() => {
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });
  const container = document.createElement("div");
  document.body.append(container);
  root = createRoot(container);
});
afterEach(() => {
  act(() => root.unmount());
  document.body.replaceChildren();
  vi.resetAllMocks();
});

it("cancels superseded searches and ignores their late responses", async () => {
  let finishOld!: (value: unknown) => void;
  vi.mocked(fetchPatients).mockReturnValueOnce(new Promise(resolve => { finishOld = resolve as typeof finishOld; }));
  vi.mocked(fetchPatients).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 10, total: 0, last_page: 1, from: 0, to: 0, path: "/api/patients" } });
  await act(async () => { root.render(<Page />); });
  await act(async () => { await new Promise(resolve => setTimeout(resolve, 300)); });
  const signal = vi.mocked(fetchPatients).mock.calls[0][5];
  await act(async () => { fireEvent.change(screen.getByRole("searchbox"), { target: { value: "new search" } }); });
  expect(signal?.aborted).toBe(true);
  await act(async () => { await new Promise(resolve => setTimeout(resolve, 300)); });
  await act(async () => { finishOld({ data: [{ id: "obsolete", first_name: "Obsolete", last_name: "Result", display_name: "Obsolete Result" }], meta: { total: 1 } }); });
  expect(screen.queryByText("Obsolete Result")).toBeNull();
  expect(screen.getByRole("searchbox").getAttribute("value")).toBe("new search");
});

it("aborts the download when leaving the patient directory", async () => {
  vi.mocked(fetchPatients).mockReturnValue(new Promise(() => {}));
  await act(async () => { root.render(<Page />); });
  await act(async () => { await new Promise(resolve => setTimeout(resolve, 300)); });
  const signal = vi.mocked(fetchPatients).mock.calls[0][5];
  act(() => root.render(null));
  expect(signal?.aborted).toBe(true);
});
