import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const pages = [
  "app/(rnd)/notifications/page.tsx",
  "app/admin/notifications/page.tsx",
].map((path) => readFileSync(join(process.cwd(), path), "utf8"));

describe("notification page activation", () => {
  test.each(pages)("supports keyboard activation and immediate navigation", (page) => {
    expect(page).toContain('role="button"');
    expect(page).toContain("tabIndex={0}");
    expect(page).toContain('event.key === "Enter" || event.key === " "');
    expect(page.indexOf("router.push(href)")).toBeLessThan(page.indexOf("markNotificationOpened(n.id)"));
  });
});
