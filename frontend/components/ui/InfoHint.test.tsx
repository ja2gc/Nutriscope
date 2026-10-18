/** @vitest-environment jsdom */

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test } from "vitest";
import { InfoHint } from "./InfoHint";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

describe("InfoHint", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
  });

  afterEach(async () => {
    await act(async () => root.unmount());
    document.body.innerHTML = "";
  });

  test('clicking an already hovered trigger keeps guidance open', async () => {
    await act(async () => root.render(<InfoHint label="Prescription help">Targets and limits explained.</InfoHint>));
    const trigger = container.querySelector<HTMLButtonElement>('button')!;
    await act(async () => trigger.dispatchEvent(new MouseEvent('pointerover', { bubbles: true })));
    await act(async () => trigger.click());
    expect(document.body.textContent).toContain('Targets and limits explained.');
  });

  test('keyboard focus keeps help open without stealing focus and closing itself', async () => {
    await act(async () => root.render(<InfoHint label="Prescription help">Targets and limits explained.</InfoHint>));
    const trigger = container.querySelector<HTMLButtonElement>('button')!;
    await act(async () => trigger.focus());
    await act(async () => new Promise(resolve => setTimeout(resolve, 150)));
    expect(document.activeElement).toBe(trigger);
    expect(document.body.textContent).toContain('Targets and limits explained.');
  });

  test("opens useful guidance on hover without adding a square hover surface", async () => {
    await act(async () => root.render(
      <InfoHint label="How AI token costs are calculated" title="How cost is calculated">
        Input tokens ÷ 1,000,000 × configured input rate.
      </InfoHint>,
    ));

    const trigger = container.querySelector<HTMLButtonElement>('button[aria-label="How AI token costs are calculated"]');
    expect(trigger).not.toBeNull();

    expect(trigger?.className).not.toContain("hover:bg-");
    expect(trigger?.className).toContain("h-6 w-6");
    expect(trigger?.className).not.toContain("min-h-11");

    await act(async () => trigger?.dispatchEvent(new MouseEvent("pointerover", { bubbles: true })));
    expect(document.body.textContent).toContain("Input tokens ÷ 1,000,000 × configured input rate.");

    await act(async () => document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true })));
    expect(document.body.textContent).not.toContain("Input tokens ÷ 1,000,000 × configured input rate.");
  });
});
