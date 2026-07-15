// @vitest-environment jsdom

import { act } from "react";
import { createRoot } from "react-dom/client";
import { renderToStaticMarkup } from "react-dom/server";
import userEvent from "@testing-library/user-event";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";
import type { AuditHistoryDto } from "@/types/auditHistory";
import { AuditHistoryView } from "./AuditHistoryView";

const history: AuditHistoryDto = {
  id: "3adff5e3-8111-4a52-8390-f18a3bbc6d1d",
  event: {
    id: "70e4f184-95da-43bd-b017-8d48f803fb94",
    module: "nutrition_care",
    category: "operations",
    domain: "nutrition_library",
    record_type: "Recipe",
    action: "updated",
    action_label: "Updated",
    summary: "Maria Santos updated Brown Rice Recipe.",
    severity: "notice",
    outcome: "success",
    actor: { id: "d093b547-0967-4c4f-8ee6-33b78167b6ea", kind: "user", name: "Maria Santos", role: "RND" },
    subject: { type: "recipe", id: "a44768e8-3441-45a5-9746-eb32447df728", label: "Brown Rice Recipe" },
    context: null,
    patient: null,
    ncp_reference: null,
    detail_mode: "history",
    reason: "Corrected ingredient structure",
    history: null,
    current_record_url: null,
    occurred_at: "2026-07-15T08:30:00Z",
    details: [],
    changes: [],
  },
  version: {
    serializer: "framework_recipe",
    schema_version: 1,
    occurred_at: "2026-07-15T08:30:00Z",
  },
  before: {
    type: "framework_recipe",
    title: "Before Recipe",
    reference: "RCP-001",
    fields: [{ key: "name", label: "Name", value: { type: "text", value: "BEFORE-SENTINEL" } }],
    tables: [],
  },
  after: {
    type: "framework_recipe",
    title: "After Recipe",
    reference: "RCP-001",
    fields: [{ key: "name", label: "Name", value: { type: "text", value: "AFTER-SENTINEL" }, change: "changed" }],
    tables: [],
  },
  read_only: true,
};

describe("audit historical view", () => {
  test("defaults updates to the typed After version and exposes no edit or raw JSON controls", () => {
    const html = renderToStaticMarkup(<AuditHistoryView history={history} />);

    expect(html).toContain("Read-only historical version");
    expect(html).toContain("After Recipe");
    expect(html).toContain("AFTER-SENTINEL");
    expect(html).toContain("Changed");
    expect(html).not.toContain("BEFORE-SENTINEL");
    expect(html).not.toContain("Save");
    expect(html).not.toContain("Delete");
    expect(html).not.toContain("JSON");
    expect(html).not.toContain("<input");
    expect(html).not.toContain("<textarea");
  });

  test("switches accessibly between Before and After without mutation controls", async () => {
    const container = document.createElement("div");
    document.body.append(container);
    const root = createRoot(container);
    const user = userEvent.setup();

    await act(async () => root.render(<AuditHistoryView history={history} />));
    const before = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Before")!;
    expect(before).toBeDefined();
    expect(before.getAttribute("aria-pressed")).toBe("false");

    await act(async () => user.click(before));
    expect(container.textContent).toContain("BEFORE-SENTINEL");
    expect(container.textContent).not.toContain("AFTER-SENTINEL");
    expect(before.getAttribute("aria-pressed")).toBe("true");

    await act(async () => root.unmount());
    container.remove();
  });

  test("uses an Admin history page, UUID-only proxy, and typed service", () => {
    const page = readFileSync(join(process.cwd(), "app/admin/audit-logs/[id]/history/page.tsx"), "utf8");
    const route = readFileSync(join(process.cwd(), "app/api/admin/audit-logs/[id]/history/route.ts"), "utf8");
    const service = readFileSync(join(process.cwd(), "services/auditHistoryService.ts"), "utf8");

    expect(page).toContain("AuditHistoryView");
    expect(page).toContain("Audit history unavailable");
    expect(route).toContain("UUID_PATTERN");
    expect(route).toContain("encodeURIComponent(id)");
    expect(service).toContain("AuditHistoryDto");
    expect(service).not.toContain("JSON.stringify");
  });
});
