import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const shell = readFileSync(join(process.cwd(), "components/notifications/NotificationsPageShell.tsx"), "utf8");

describe("notification page activation", () => {
  test("uses a native keyboard-accessible button and immediate navigation", () => {
    expect(shell).toContain('<button type="button" onClick={() => open(notification)}');
    expect(shell.indexOf("router.push(notificationTargetHref(notification, role))")).toBeLessThan(shell.indexOf("markNotificationOpened(notification.id)"));
  });
});
