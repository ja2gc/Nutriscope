// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import CounselingTab from "./CounselingTab";
import EducationTab from "./EducationTab";

(globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

describe("intervention guidance limits", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  it("limits education and reports the shared guidance budget", () => {
    act(() => root.render(
      <EducationTab
        value="Education"
        onChange={vi.fn()}
        onSave={vi.fn()}
        saving={false}
        totalCharacters={2050}
        showSave={false}
      />,
    ));

    expect(container.querySelector("textarea")?.maxLength).toBe(1200);
    expect(container.textContent).toContain("2,050 / 2,200 characters");
  });

  it("limits every counseling field and marks an exceeded shared budget", () => {
    act(() => root.render(
      <CounselingTab
        goals="Goal"
        barriers="Barrier"
        strategies="Strategy"
        onChange={vi.fn()}
        onSave={vi.fn()}
        saving={false}
        totalCharacters={2201}
        showSave={false}
      />,
    ));

    expect(Array.from(container.querySelectorAll("textarea")).map((area) => area.maxLength)).toEqual([1200, 1200, 1200]);
    expect(container.querySelector('[role="alert"]')?.textContent).toContain("1 character over");
  });
});
