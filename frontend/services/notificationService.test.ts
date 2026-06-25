import { describe, expect, test } from "vitest";

import { shouldShowNotification } from "./notificationService";

describe("notification preference filtering", () => {
  test("hides announcement notifications when announcement alerts are off", () => {
    expect(shouldShowNotification({ type: "announcement" }, { announcements: false, followUps: true })).toBe(false);
  });

  test("hides follow-up notifications when follow-up reminders are off", () => {
    expect(shouldShowNotification({ type: "follow_up" }, { announcements: true, followUps: false })).toBe(false);
  });
});
