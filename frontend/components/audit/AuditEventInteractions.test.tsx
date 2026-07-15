// @vitest-environment jsdom

import { act, useState } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test } from "vitest";
import userEvent from "@testing-library/user-event";
import type { AuditEventDto } from "@/types/audit";
import { AuditEventDrawer } from "./AuditEventDrawer";
import { AuditEventTable } from "./AuditEventTable";

const event: AuditEventDto = {
  id: "evt_keyboard",
  module: "security_administration",
  category: "security",
  domain: "accounts",
  record_type: "Authentication",
  action: "login_failed",
  action_label: "Login failed",
  summary: "Authentication failed.",
  severity: "warning",
  outcome: "failure",
  actor: null,
  subject: null,
  context: null,
  patient: null,
  ncp_reference: null,
  detail_mode: "changes",
  reason: null,
  history: null,
  current_record_url: null,
  occurred_at: "2026-07-12T08:30:00Z",
  details: [],
  changes: [],
};

function Harness() {
  const [selected, setSelected] = useState<AuditEventDto | null>(null);
  return (
    <>
      <AuditEventTable events={[event]} onSelect={setSelected} />
      {selected && <AuditEventDrawer event={selected} onClose={() => setSelected(null)} />}
    </>
  );
}

describe("audit event keyboard interactions", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
    act(() => root.render(<Harness />));
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  test("Enter opens, focuses and traps the drawer; Escape closes and restores button focus", async () => {
    const user = userEvent.setup();
    const trigger = container.querySelector<HTMLButtonElement>('button[aria-label^="Inspect"]')!;
    trigger.focus();

    await act(async () => user.keyboard("{Enter}"));

    const drawer = container.querySelector<HTMLElement>('[role="dialog"]')!;
    const close = drawer.querySelector<HTMLButtonElement>('button[aria-label="Close event details"]')!;
    expect(drawer).not.toBeNull();
    expect(drawer.getAttribute("aria-describedby")).toBe("audit-drawer-description");
    expect(drawer.textContent).toContain("Read-only audit record");
    expect(document.activeElement).toBe(close);

    await act(async () => user.tab());
    expect(document.activeElement).toBe(close);
    await act(async () => user.tab({ shift: true }));
    expect(document.activeElement).toBe(close);

    await act(async () => user.keyboard("{Escape}"));
    expect(container.querySelector('[role="dialog"]')).toBeNull();
    expect(document.activeElement).toBe(trigger);
  });

  test("Space activates the labeled desktop button", async () => {
    const user = userEvent.setup();
    const trigger = container.querySelector<HTMLButtonElement>('button[aria-label^="Inspect"]')!;
    trigger.focus();

    await act(async () => user.keyboard("[Space]"));

    expect(container.querySelector('[role="dialog"]')).not.toBeNull();
  });
});
