// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import userEvent from "@testing-library/user-event";
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

  it("limits education and reports the shared guidance budget", async () => {
    const onChange = vi.fn();
    act(() => root.render(
      <EducationTab
        value="Education"
        onChange={onChange}
        onSave={vi.fn()}
        saving={false}
        totalCharacters={2050}
        showSave={false}
      />,
    ));

    expect(container.textContent).toContain("9/1200");
    expect(container.textContent).toContain("2,050 / 2,200 characters");
    const user = userEvent.setup();
    await act(async () => {
      await user.click(container.querySelector("textarea")!);
      await user.paste("x".repeat(1201));
    });
    expect(onChange).toHaveBeenLastCalledWith(`Education${"x".repeat(1191)}`);
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

    for (const counter of ["4/1200", "7/1200", "8/1200"]) expect(container.textContent).toContain(counter);
    expect(Array.from(container.querySelectorAll("textarea")).map(el => el.getAttribute("aria-label"))).toEqual(["Behavioral Goals", "Identified Barriers", "Strategies"]);
    expect(container.querySelector('[role="alert"]')?.textContent).toContain("1 character over");
  });
});
