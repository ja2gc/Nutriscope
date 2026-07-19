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
        { type: "announcement", source_module: "announcements", source_id: 42 },
        "RND",
      ),
    ).toBe("/announcements?announcementId=42");
  });

  test("prefers the public UUID over the internal source id", () => {
    expect(
      notificationTargetHref(
        { type: "po_awaiting_receipt", source_module: "food_service", source_id: 12, source_uuid: "po-public-uuid" },
        "FSS",
      ),
    ).toBe("/food-service/procurement?poId=po-public-uuid");
  });

  test("routes Admin announcement notifications to the admin announcement page", () => {
    expect(
      notificationTargetHref(
        { type: "announcement", source_module: "announcements", source_id: 42 },
        "Admin",
      ),
    ).toBe("/admin/announcements?announcementId=42");
  });

  test("routes PO receipt notifications to the procurement event", () => {
    expect(
      notificationTargetHref(
        { type: "po_awaiting_receipt", source_module: "food_service", source_id: 12 },
        "RND",
      ),
    ).toBe("/food-service/procurement?poId=12");
  });

  test("falls back to notifications page when source is missing", () => {
    expect(notificationTargetHref({ type: "info" }, "RND")).toBe("/notifications");
  });
});
