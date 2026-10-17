// @vitest-environment jsdom
import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { expect, test } from "vitest";
import { WeeklyMenuCalendar } from "./WeeklyMenuCalendar";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
test("all clinical calendars can hide snack slots without mutating their contents", async () => {
  localStorage.clear();
  const container = document.createElement("div"); document.body.append(container);
  const root = createRoot(container);
  const renderCell = (_day: string, meal: string) => <span>{meal === "am_snack" ? "Retained snack" : "Meal"}</span>;
  try {
    await act(async () => root.render(<WeeklyMenuCalendar renderCell={renderCell} />));
    expect(container.querySelectorAll('[data-meal="am_snack"]')).toHaveLength(7);
    const toggle = container.querySelector<HTMLInputElement>('[aria-label="Show snack slots"]');
    expect(toggle).not.toBeNull();
    await act(async () => toggle!.click());
    expect(container.querySelectorAll('[data-meal="am_snack"], [data-meal="pm_snack"]')).toHaveLength(0);
    await act(async () => toggle!.click());
    expect(container.textContent).toContain("Retained snack");
  } finally { await act(async () => root.unmount()); container.remove(); localStorage.clear(); }
});
