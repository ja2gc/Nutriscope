import { describe, expect, test } from "vitest";

import { notificationTargetHref, shouldShowNotification } from "./notificationService";

describe("notification preference filtering", () => {
  test("hides announcement notifications when announcement alerts are off", () => {
    expect(shouldShowNotification({ type: "announcement" }, { announcements: false, followUps: true })).toBe(false);
  });

  test("hides follow-up notifications when follow-up reminders are off", () => {
    expect(shouldShowNotification({ type: "follow_up" }, { announcements: true, followUps: false })).toBe(false);
  });
});

describe("notification target routing", () => {
  test("routes RND announcement notifications to the exact announcement", () => {
    expect(
      notificationTargetHref(
        { type: "announcement", source_module: "announcements", source_uuid: "announcement-42" },
        "RND",
      ),
    ).toBe("/announcements?announcementId=announcement-42");
  });

  test("prefers the public UUID over the internal source id", () => {
    expect(
      notificationTargetHref(
        { type: "po_awaiting_receipt", source_module: "food_service", source_uuid: "po-public-uuid" },
        "FSS",
      ),
    ).toBe("/food-service/procurement?poId=po-public-uuid");
  });

  test("routes Admin announcement notifications to the admin announcement page", () => {
    expect(
      notificationTargetHref(
        { type: "announcement", source_module: "announcements", source_uuid: "announcement-42" },
        "Admin",
      ),
    ).toBe("/admin/announcements?announcementId=announcement-42");
  });

  test("routes PO receipt notifications to the procurement event", () => {
    expect(
      notificationTargetHref(
        { type: "po_awaiting_receipt", source_module: "food_service", source_uuid: "po-12" },
        "RND",
      ),
    ).toBe("/food-service/procurement?poId=po-12");
  });

  test("routes follow-up reminders directly to the related monitoring plan", () => {
    expect(
      notificationTargetHref(
        {
          type: "follow_up",
          source_module: "ncp",
          source_uuid: "ncp-public-uuid",
          source_parent_uuid: "patient-public-uuid",
        },
        "RND",
      ),
    ).toBe("/ncp/patient-public-uuid/monitoring/ncp-public-uuid");
  });

  test("falls back to notifications page when source is missing", () => {
    expect(notificationTargetHref({ type: "info" }, "RND")).toBe("/notifications");
  });

  test("routes appointment actions to the exact patient appointment record", () => {
    expect(notificationTargetHref({
      type: "appointment_due",
      source_module: "ncp_appointment",
      source_uuid: "visit-public-uuid",
      source_parent_uuid: "patient-public-uuid",
    }, "RND")).toBe("/ncp/patients/patient-public-uuid?tab=appointments&appointmentId=visit-public-uuid");
  });
});
