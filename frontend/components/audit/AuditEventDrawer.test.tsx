import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test, vi } from "vitest";
import { AuditChangeList } from "./AuditChangeList";
import { AuditEventDrawer } from "./AuditEventDrawer";
import { AuditEventTable } from "./AuditEventTable";
import { AuditFilters } from "./AuditFilters";
import type { AuditEventDto, AuditFilterMetadata } from "@/types/audit";

const longActor = "Alexandria Cassandra Montgomery-Worthington the Third, Regional Compliance Administrator";
const longSubject = "Quarterly nutrition-care compliance record for the longest authorized display label";

const event: AuditEventDto = {
  id: "evt_01HXYZ",
  category: "clinical",
  domain: "patients",
  action: "updated",
  action_label: "Updated",
  summary: "Changed authorized clinical record fields.",
  severity: "notice",
  outcome: "success",
  actor: { id: "user-public-id", kind: "user", name: longActor, role: "Admin" },
  subject: { type: "patient", id: "patient-public-id", label: longSubject },
  context: { type: "ncp", id: "ncp-public-id", label: "Nutrition care plan" },
  occurred_at: "2026-07-12T08:30:00Z",
  details: [{ key: "status", label: "Status", kind: "status", value: "completed" }],
  changes: [{ field: "medical_diagnosis", label: "Medical diagnosis", old_value: "SECRET-OLD", new_value: "SECRET-NEW", redacted: true }],
};

describe("structured audit event components", () => {
  test("wraps longest labels in desktop table and mobile cards", () => {
    const html = renderToStaticMarkup(<AuditEventTable events={[event]} onSelect={vi.fn()} />);
    expect(html).toContain(longActor);
    expect(html).toContain(longSubject);
    expect(html).toContain("hidden md:block");
    expect(html).toContain("md:hidden");
    expect(html).toContain("break-words");
  });

  test("renders stable semantic timestamps in Asia/Manila", () => {
    const tableHtml = renderToStaticMarkup(<AuditEventTable events={[event]} onSelect={vi.fn()} />);
    const drawerHtml = renderToStaticMarkup(<AuditEventDrawer event={event} onClose={vi.fn()} />);
    for (const html of [tableHtml, drawerHtml]) {
      expect(html).toContain('<time dateTime="2026-07-12T08:30:00.000Z"');
      expect(html).toContain("Jul 12, 2026");
      expect(html).toContain("04:30:00 PM PHT");
      expect(html).toContain("2026-07-12T08:30:00.000Z · Jul 12, 2026 04:30:00 PM Asia/Manila");
    }
  });

  test("renders all drawer sections without raw clinical values", () => {
    const html = renderToStaticMarkup(<AuditEventDrawer event={event} onClose={vi.fn()} />);
    for (const section of ["Event summary", "Actor", "Subject / context", "Result", "Safe request metadata", "Field changes"]) {
      expect(html).toContain(section);
    }
    expect(html).toContain("Value hidden; field changed");
    expect(html).not.toContain("SECRET-OLD");
    expect(html).not.toContain("SECRET-NEW");
  });

  test("never renders redacted values as placeholder bullets", () => {
    const html = renderToStaticMarkup(<AuditChangeList changes={event.changes} clinical />);
    expect(html).toContain("Value hidden; field changed");
    expect(html).not.toMatch(/[•●]{2,}/u);
  });

  test("limits actions to the backend category-action compatibility map", () => {
    const metadata: AuditFilterMetadata = {
      categories: [{ value: "clinical", label: "Clinical" }],
      domains: [],
      actions: [
        { value: "updated", label: "Updated" },
        { value: "login_failed", label: "Login failed" },
      ],
      outcomes: [],
      severities: [],
      category_actions: { clinical: ["updated"], security: [], operations: [] },
    };
    const html = renderToStaticMarkup(
      <AuditFilters metadata={metadata} users={[]} value={{ category: "clinical" }} onChange={vi.fn()} onClear={vi.fn()} />,
    );
    expect(html).toContain("Updated");
    expect(html).not.toContain("Login failed");
  });
});
