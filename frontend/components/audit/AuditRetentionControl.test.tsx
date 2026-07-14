// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import type { AuditRetentionState } from "@/types/audit";
import { AuditRetentionControl } from "./AuditRetentionControl";

const disabledState: AuditRetentionState = {
  enabled: false,
  source: "config",
  periods: { security: 365, clinical: 2190, operations: 1095, legacy: 90 },
};

describe("AuditRetentionControl", () => {
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
    vi.restoreAllMocks();
  });

  test("shows current state and every static category period as read-only information", () => {
    act(() => root.render(<AuditRetentionControl retention={disabledState} onUpdate={vi.fn()} />));

    expect(container.textContent).toContain("Scheduled deletion is OFF");
    expect(container.textContent).toContain("Security365 days");
    expect(container.textContent).toContain("Clinical2,190 days");
    expect(container.textContent).toContain("Operations1,095 days");
    expect(container.textContent).toContain("Legacy90 days");
    expect(container.querySelectorAll("input")).toHaveLength(0);
  });

  test("requires the explicit permanent-deletion confirmation before enabling", async () => {
    const onUpdate = vi.fn(async () => ({ ...disabledState, enabled: true, source: "database" as const }));
    act(() => root.render(<AuditRetentionControl retention={disabledState} onUpdate={onUpdate} />));

    await act(async () => userEvent.setup().click(container.querySelector("button")!));

    expect(onUpdate).not.toHaveBeenCalled();
    const dialog = container.querySelector('[role="dialog"]');
    expect(dialog?.textContent).toContain("Deletion is scheduled daily.");
    expect(dialog?.textContent).toContain(
      "Rows older than the configured periods for each category are permanently deleted and unrecoverable.",
    );
    expect(dialog?.textContent).toContain(
      "Enable this only after privacy/compliance owner approval.",
    );

    const confirm = Array.from(container.querySelectorAll("button"))
      .find((button) => button.textContent?.includes("Confirm and enable"))!;
    await act(async () => userEvent.setup().click(confirm));

    expect(onUpdate).toHaveBeenCalledExactlyOnceWith(true);
  });

  test("disables immediately without a confirmation dialog", async () => {
    const onUpdate = vi.fn(async () => ({ ...disabledState, source: "database" as const }));
    act(() => root.render(
      <AuditRetentionControl retention={{ ...disabledState, enabled: true, source: "database" }} onUpdate={onUpdate} />,
    ));

    await act(async () => userEvent.setup().click(container.querySelector("button")!));

    expect(onUpdate).toHaveBeenCalledExactlyOnceWith(false);
    expect(container.querySelector('[role="dialog"]')).toBeNull();
  });

  test("traps dialog focus, closes on Escape, and restores the enable button", async () => {
    act(() => root.render(<AuditRetentionControl retention={disabledState} onUpdate={vi.fn()} />));
    const user = userEvent.setup();
    const enable = container.querySelector("button")!;

    await act(async () => user.click(enable));

    const dialog = container.querySelector<HTMLElement>('[role="dialog"]')!;
    const buttons = Array.from(dialog.querySelectorAll<HTMLButtonElement>("button"));
    expect(document.activeElement).toBe(buttons[0]);

    await act(async () => user.keyboard("{Shift>}{Tab}{/Shift}"));
    expect(document.activeElement).toBe(buttons.at(-1));

    await act(async () => user.keyboard("{Escape}"));
    expect(container.querySelector('[role="dialog"]')).toBeNull();
    expect(document.activeElement).toBe(enable);
  });
});
