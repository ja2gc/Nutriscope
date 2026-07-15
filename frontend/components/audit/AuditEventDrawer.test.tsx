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
  module: "nutrition_care",
  category: "clinical",
  domain: "patients",
  record_type: "Patient",
  action: "updated",
  action_label: "Updated",
  summary: "Changed authorized clinical record fields.",
  severity: "notice",
  outcome: "success",
  actor: { id: "user-public-id", kind: "user", name: longActor, role: "Admin" },
  subject: { type: "patient", id: null, label: longSubject },
  context: { type: "ncp", id: null, label: "Nutrition care plan" },
  patient: { display_name: "Patient Example" },
  ncp_reference: "NCP-EXAMPLE",
  detail_mode: "field_names",
  reason: null,
  history: null,
  current_record_url: null,
  occurred_at: "2026-07-12T08:30:00Z",
  details: [{ key: "status", label: "Status", kind: "enum", value: "completed", typed_value: { type: "enum", value: "completed" } }],
  changes: [{
    field: "medical_diagnosis", label: "Medical diagnosis", old_value: "SECRET-OLD", new_value: "SECRET-NEW",
    before: { type: "redacted", value: null }, after: { type: "redacted", value: null }, redacted: true,
  }],
};

const operationalEvent: AuditEventDto = {
  ...event,
  id: "evt_operational",
  module: "food_service_operations",
  category: "operations",
  domain: "food_service",
  record_type: "FS Item",
  action: "updated",
  action_label: "Updated",
  summary: "Maria Santos updated Brown Rice.",
  actor: { id: "actor-public-id", kind: "user", name: "Maria Santos", role: "RND" },
  subject: { type: "fs_item", id: "item-public-id", label: "Brown Rice" },
  context: null,
  patient: null,
  ncp_reference: null,
  detail_mode: "changes",
  reason: "Corrected vendor invoice",
  details: [
    { key: "purchase_price", label: "Purchase Price", kind: "currency", value: 120.5, typed_value: { type: "currency", value: 120.5, currency: "PHP" } },
    { key: "is_active", label: "Active", kind: "boolean", value: true, typed_value: { type: "boolean", value: true } },
  ],
  changes: [
    {
      field: "name", label: "Name", old_value: null, new_value: "Brown Rice",
      before: { type: "text", value: null }, after: { type: "text", value: "Brown Rice" }, redacted: false,
    },
    {
      field: "purchase_price", label: "Purchase Price", old_value: 100, new_value: 120.5,
      before: { type: "currency", value: 100, currency: "PHP" }, after: { type: "currency", value: 120.5, currency: "PHP" }, redacted: false,
    },
  ],
};

const createdEvent: AuditEventDto = {
  ...operationalEvent,
  id: "evt_created",
  action: "created",
  action_label: "Created",
  summary: "Maria Santos created Brown Rice.",
  reason: null,
  changes: [operationalEvent.changes[0]],
};

const deletedEvent: AuditEventDto = {
  ...operationalEvent,
  id: "evt_deleted",
  action: "deleted",
  action_label: "Deleted",
  summary: "Maria Santos deleted Brown Rice.",
  changes: [{
    field: "name", label: "Name", old_value: "Brown Rice", new_value: null,
    before: { type: "text", value: "Brown Rice" }, after: { type: "text", value: null }, redacted: false,
  }],
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
    for (const section of ["Event summary", "Actor", "Record context", "Result", "Recorded values", "Field changes"]) {
      expect(html).toContain(section);
    }
    expect(html).toContain("Patient Example");
    expect(html).toContain("NCP-EXAMPLE");
    expect(html).toContain("Patient");
    expect(html).toContain("Value hidden; field changed");
    expect(html).not.toContain("Safe request metadata");
    expect(html).not.toContain("No request metadata recorded");
    expect(html).not.toContain("SECRET-OLD");
    expect(html).not.toContain("SECRET-NEW");
  });

  test("renders typed recorded values, reason, and explicit before/after null transitions", () => {
    const html = renderToStaticMarkup(<AuditEventDrawer event={operationalEvent} onClose={vi.fn()} />);

    expect(html).toContain("FS Item");
    expect(html).toContain("Corrected vendor invoice");
    expect(html).toContain("₱120.50");
    expect(html).toContain("Yes");
    expect(html).toContain("Not recorded");
    expect(html).toContain("Brown Rice");
    expect(html).toContain('aria-label="Name before value"');
    expect(html).toContain('aria-label="Name after value"');
    expect(html).toContain("Read-only audit record");
    expect(html).not.toContain("food_service");
    expect(html).not.toContain(">operations<");
  });

  test.each([
    ["created", createdEvent, "Created"],
    ["deleted", deletedEvent, "Deleted"],
  ])("renders a safe %s entity snapshot with typed null transitions", (_case, drawerEvent, actionLabel) => {
    const html = renderToStaticMarkup(<AuditEventDrawer event={drawerEvent} onClose={vi.fn()} />);

    expect(html).toContain(actionLabel);
    expect(html).toContain("Brown Rice");
    expect(html).toContain("Not recorded");
    expect(html).toContain('aria-label="Name before value"');
    expect(html).toContain('aria-label="Name after value"');
  });

  test("never renders redacted values as placeholder bullets", () => {
    const html = renderToStaticMarkup(<AuditChangeList changes={event.changes} clinical />);
    expect(html).toContain("Value hidden; field changed");
    expect(html).not.toMatch(/[•●]{2,}/u);
  });

  test("limits actions to the selected backend module-action map", () => {
    const metadata: AuditFilterMetadata = {
      categories: [{ value: "clinical", label: "Clinical" }],
      domains: [],
      modules: [{ value: "nutrition_care", label: "Nutrition Care" }],
      actions: [
        { value: "updated", label: "Updated" },
        { value: "login_failed", label: "Login failed" },
      ],
      outcomes: [],
      severities: [],
      category_actions: { clinical: ["updated"], security: [], operations: [] },
      module_subfilters: { security_administration: [], nutrition_care: [], food_service_operations: [], reports: [] },
      module_actions: { security_administration: [], nutrition_care: ["updated"], food_service_operations: [], reports: [] },
      module_counts: { all: 0, security_administration: 0, nutrition_care: 0, food_service_operations: 0, reports: 0 },
    };
    const html = renderToStaticMarkup(
      <AuditFilters metadata={metadata} value={{ module: "nutrition_care" }} onChange={vi.fn()} onClear={vi.fn()} />,
    );
    expect(html).toContain("Updated");
    expect(html).not.toContain("Login failed");
  });
});
