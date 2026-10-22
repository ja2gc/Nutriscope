// @vitest-environment jsdom
import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { expect, test, vi } from "vitest";
import GuidanceSuggestion from "./GuidanceSuggestion";

test("shows a collapsible selectable suggestion and copies without editing fields", async () => {
  (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
  const writeText = vi.fn().mockResolvedValue(undefined);
  Object.defineProperty(navigator, "clipboard", { configurable: true, value: { writeText } });
  const host = document.createElement("div"); document.body.append(host);
  const root = createRoot(host);
  await act(async () => root.render(<GuidanceSuggestion label="Education" text="Eat regular meals." />));
  expect(host.querySelector('[aria-expanded="false"]')).not.toBeNull();
  expect(host.firstElementChild?.className).toContain('border-warm-200');
  await act(async () => (host.querySelector('[aria-expanded]') as HTMLButtonElement).click());
  expect(host.textContent).toContain("Eat regular meals.");
  const copy = host.querySelector('[aria-label="Copy suggested Education note"]') as HTMLButtonElement;
  await act(async () => copy.click());
  await act(async () => copy.click());
  expect(writeText).toHaveBeenCalledTimes(2);
  expect(writeText).toHaveBeenLastCalledWith("Eat regular meals.");
  expect(host.querySelector("textarea")).toBeNull();
  expect(host.querySelector("input, [contenteditable] ")).toBeNull();
  writeText.mockRejectedValueOnce(new Error("Denied"));
  await act(async () => copy.click());
  expect(host.textContent).toContain("Select and copy the note text.");
  await act(async () => root.unmount()); host.remove();
});
