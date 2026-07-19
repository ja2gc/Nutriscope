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
});
