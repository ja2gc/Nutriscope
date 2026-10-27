// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { DatePicker, DateTimePicker } from "./DatePicker";

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

  test("uses a calendar grid for day selection and month/year dropdowns", async () => {
    const onChange = vi.fn();
    await act(async () => root.render(<DatePicker label="Date of birth" required value="2026-10-19" onChange={onChange} />));
    await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Date of birth"]')!.click());

    expect(container.querySelector("legend")?.textContent).toContain("required");
    expect(document.querySelector('[aria-label="Calendar month"]')).not.toBeNull();
    expect(document.querySelector('[aria-label="Calendar year"]')).not.toBeNull();
    expect(document.querySelector('[aria-label="2026-10-20"]')).not.toBeNull();
    expect(document.querySelector('[aria-label="Date of birth day"]')).toBeNull();

    await act(async () => document.querySelector<HTMLButtonElement>('[aria-label="2026-10-20"]')!.click());

    expect(onChange).toHaveBeenLastCalledWith("2026-10-20");
  });

  test("shows valid calendar days for the selected month and year", async () => {
    await act(async () => root.render(<DatePicker label="Date" value="2024-02-29" onChange={() => undefined} />));
    await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Date"]')!.click());
    expect(document.querySelector('[aria-label="2024-02-29"]')).not.toBeNull();

    const year = document.querySelector<HTMLSelectElement>('[aria-label="Calendar year"]')!;
    await act(async () => { year.value = "2025"; year.dispatchEvent(new Event("change", { bubbles: true })); });
    expect(document.querySelector('[aria-label="2025-02-29"]')).toBeNull();
  });

  test("does not display a rejected date while retaining a different submitted value", () => {
    const change = vi.fn();
    act(() => root.render(<DatePicker value="2026-10-19" min="2026-10-19" onChange={change} />));
    act(() => container.querySelector<HTMLButtonElement>('button[aria-label="Date"]')!.click());
    const rejected = document.querySelector<HTMLButtonElement>('[aria-label="2026-10-18 unavailable"]')!;
    expect(rejected.disabled).toBe(true);
    expect(change).not.toHaveBeenCalled();
  });

  test('calendar shows unavailable days and supports selecting another month and year', async () => {
    const change = vi.fn();
    await act(async () => root.render(<DatePicker label="Start date" value="2026-10-19" isDateDisabled={(date) => date === '2026-10-20'} onChange={change} />));
    await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Start date"]')!.click());
    const unavailable = document.querySelector<HTMLButtonElement>('[aria-label="2026-10-20 unavailable"]')!;
    expect(unavailable.disabled).toBe(true);
    const year = document.querySelector<HTMLSelectElement>('[aria-label="Calendar year"]')!;
    await act(async () => { year.value = '2027'; year.dispatchEvent(new Event('change', { bubbles: true })); });
    const month = document.querySelector<HTMLSelectElement>('[aria-label="Calendar month"]')!;
    await act(async () => { month.value = '11'; month.dispatchEvent(new Event('change', { bubbles: true })); });
    await act(async () => document.querySelector<HTMLButtonElement>('[aria-label="2027-11-08"]')!.click());
    expect(change).toHaveBeenLastCalledWith('2027-11-08');
  });

  test("aligns referral time input with date controls", () => {
    act(() => root.render(<DateTimePicker label="Referral date and time" value="2026-10-10T12:30" onChange={() => undefined} />));

    expect(container.querySelector("div.grid")?.className).toContain("items-end");
    expect(container.querySelector('input[type="time"]')).not.toBeNull();
  });
});
