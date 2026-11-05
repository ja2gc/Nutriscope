/** @vitest-environment jsdom */
import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, expect, test, vi } from "vitest";
import { ReportsBrowser, FULL_CATALOG } from "./ReportsBrowser";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
let container: HTMLDivElement;
let root: Root;
let submitted: FormData | undefined;
const branding = { hospital_name: "Example Hospital", address: "", accreditation: "", service_name: "Nutrition", province: "", lgu: "", logo_left_path: "saved", logo_right_path: "saved" };
beforeEach(() => {
  submitted = undefined;
  vi.stubGlobal("fetch", async (url: string, init?: RequestInit) => {
    const isBranding = url.endsWith("/report-branding");
    if (isBranding && init?.method === "POST") submitted = init.body as FormData;
    return Response.json({ data: isBranding ? branding : url.includes("/instances") ? { axis: "entity", instances: [] } : [], meta: { total: 0, current_page: 1, last_page: 1, per_page: 10 } });
  });
  container = document.createElement("div");
  document.body.append(container);
  root = createRoot(container);
});
afterEach(async () => {
  await act(async () => root.unmount());
  container.remove();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});
const button = (name: string) => [...container.querySelectorAll<HTMLButtonElement>("button")].find(item => item.textContent?.trim() === name);

test.each(["rnd", "admin"] as const)("%s saves only the selected replacement logo from a drop", async apiPrefix => {
  await act(async () => root.render(<ReportsBrowser apiPrefix={apiPrefix} catalog={FULL_CATALOG.slice(0, 1)} />));
  await act(async () => button("Template Edit")!.click());
  await act(async () => button("Edit")!.click());
  const zone = container.querySelector('[aria-label="Drop files for Left logo"]');
  const file = new File(["replacement"], "replacement.png", { type: "image/png" });
  const event = new Event("drop", { bubbles: true, cancelable: true });
  Object.defineProperty(event, "dataTransfer", { value: { files: [file] } });
  await act(async () => zone!.dispatchEvent(event));
  await vi.waitFor(async () => {
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 0)); });
    expect(container.querySelector('section[aria-label="Left logo"] [role="status"]')?.textContent).toContain("Ready to save");
  });
  await act(async () => button("Save")!.click());
  expect((submitted?.get("logo_left") as File)?.name).toBe("replacement.png");
  expect((submitted?.get("logo_left") as File)?.size).toBe(11);
  const right = submitted?.get("logo_right") as File | null;
  expect(right?.size ?? 0).toBe(0);
});

test("branding cannot save while a replacement logo is still being read", async () => {
  const readers: FileReader[] = [];
  vi.spyOn(FileReader.prototype, "readAsDataURL").mockImplementation(function (this: FileReader) { readers.push(this); });
  await act(async () => root.render(<ReportsBrowser apiPrefix="rnd" catalog={FULL_CATALOG.slice(0, 1)} />));
  await act(async () => button("Template Edit")!.click());
  await act(async () => button("Edit")!.click());
  const event = new Event("drop", { bubbles: true, cancelable: true });
  Object.defineProperty(event, "dataTransfer", { value: { files: [new File(["image"], "pending.png", { type: "image/png" })] } });
  await act(async () => container.querySelector('[aria-label="Drop files for Left logo"]')!.dispatchEvent(event));
  expect(button("Save")?.disabled).toBe(true);
  await act(async () => button("Save")!.click());
  expect(submitted).toBeUndefined();
  await act(async () => {
    const reader = readers[0];
    Object.defineProperty(reader, "result", { value: "data:image/png;base64,aW1hZ2U=" });
    reader.dispatchEvent(new ProgressEvent("load"));
  });
  expect(button("Save")?.disabled).toBe(false);
  await act(async () => button("Save")!.click());
  expect((submitted?.get("logo_left") as File)?.name).toBe("pending.png");
});
