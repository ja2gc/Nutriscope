// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import { MonthYearSelect } from "./MonthYearSelect";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

afterEach(() => { document.body.innerHTML = ""; });

describe("MonthYearSelect", () => {
  it("uses separate month and year dropdowns and permits clearing a search filter", async () => {
    const onMonthChange = vi.fn();
    const onYearChange = vi.fn();
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);
    await act(async () => root.render(<MonthYearSelect month={6} year={2026} onMonthChange={onMonthChange} onYearChange={onYearChange} monthAriaLabel="Audit month" yearAriaLabel="Audit year" allowAllMonths />));

    const month = container.querySelector<HTMLSelectElement>('[aria-label="Audit month"]');
    const year = container.querySelector<HTMLSelectElement>('[aria-label="Audit year"]');
    expect(month?.value).toBe("6");
    expect(year?.value).toBe("2026");
    expect(container.querySelector('input[type="month"]')).toBeNull();

    await act(async () => { month!.value = ""; month!.dispatchEvent(new Event("change", { bubbles: true })); });
    expect(onMonthChange).toHaveBeenCalledWith(null);
    await act(async () => { year!.value = "2025"; year!.dispatchEvent(new Event("change", { bubbles: true })); });
    expect(onYearChange).toHaveBeenCalledWith(2025);
    await act(async () => root.unmount());
  });

  it("disables the year when a search filter includes all dates", async () => {
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);
    await act(async () => root.render(<MonthYearSelect month={null} year={2026} onMonthChange={vi.fn()} onYearChange={vi.fn()} monthAriaLabel="Report month" yearAriaLabel="Report year" allowAllMonths />));
    expect(container.querySelector<HTMLSelectElement>('[aria-label="Report year"]')?.disabled).toBe(true);
    expect(container.querySelector<HTMLSelectElement>('[aria-label="Report month"]')?.selectedOptions[0].textContent).toBe("All dates");
    await act(async () => root.unmount());
  });
});
