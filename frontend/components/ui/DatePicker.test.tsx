// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { DatePicker } from "./DatePicker";

describe("DatePicker", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  test("emits an ISO date from organized month day and year controls", () => {
    const onChange = vi.fn();
    act(() => root.render(<DatePicker label="Date of birth" value="" onChange={onChange} />));
    const [month, day, year] = container.querySelectorAll<HTMLSelectElement>("select");

    act(() => { year.value = "2000"; year.dispatchEvent(new Event("change", { bubbles: true })); });
    act(() => { month.value = "2"; month.dispatchEvent(new Event("change", { bubbles: true })); });
    act(() => { day.value = "29"; day.dispatchEvent(new Event("change", { bubbles: true })); });

    expect(onChange).toHaveBeenLastCalledWith("2000-02-29");
  });

  test("shows only valid days for selected month and year", () => {
    act(() => root.render(<DatePicker label="Date" value="2025-02-28" onChange={() => undefined} />));
    const day = container.querySelectorAll<HTMLSelectElement>("select")[1];
    expect(Array.from(day.options).map((option) => option.value)).not.toContain("29");

    act(() => root.render(<DatePicker label="Date" value="2024-02-29" onChange={() => undefined} />));
    expect(Array.from(day.options).map((option) => option.value)).toContain("29");
    expect(Array.from(day.options).map((option) => option.value)).not.toContain("30");
  });

  test("renders compact hints when requested while retaining full accessible labels", () => {
    act(() => root.render(<DatePicker label="Start date" ariaLabel="Start date" value="" onChange={() => undefined} compactLabels />));

    const selects = Array.from(container.querySelectorAll<HTMLSelectElement>("select"));
    expect(selects.map((select) => select.options[0]?.textContent)).toEqual(["MM", "DD", "YYYY"]);
    expect(selects.map((select) => select.getAttribute("aria-label"))).toEqual([
      "Start date month",
      "Start date day",
      "Start date year",
    ]);
  });

  test("sizes every date control to its widest text using a responsive character unit", () => {
    act(() => root.render(<DatePicker label="Start date" value="2025-09-08" onChange={() => undefined} compactLabels />));

    const selects = Array.from(container.querySelectorAll<HTMLSelectElement>("select"));
    expect(selects.map((select) => select.style.width)).toEqual([
      "calc(3ch + 2.5rem)",
      "calc(2ch + 2.5rem)",
      "calc(4ch + 2.5rem)",
    ]);
    expect(container.querySelector("fieldset > div")?.className).toContain("flex");
    expect(container.querySelector("fieldset > div")?.className).toContain("w-fit");
  });

  test("keeps full placeholders sized to their text across every shared picker instance", () => {
    act(() => root.render(<DatePicker label="Date" value="" onChange={() => undefined} />));

    const selects = Array.from(container.querySelectorAll<HTMLSelectElement>("select"));
    expect(selects.map((select) => select.style.width)).toEqual([
      "calc(5ch + 2.5rem)",
      "calc(3ch + 2.5rem)",
      "calc(4ch + 2.5rem)",
    ]);
  });
});
