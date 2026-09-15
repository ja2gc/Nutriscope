import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";

describe("announcement copy", () => {
  it("does not repeat the destination under announcement authors", () => {
    const board = readFileSync(join(process.cwd(), "components/announcements/AnnouncementsBoard.tsx"), "utf8");
    const dashboard = readFileSync(join(process.cwd(), "app/(rnd)/dashboard/page.tsx"), "utf8");

    expect(board).not.toContain("Posted to department announcements");
    expect(dashboard).not.toContain("Posted to department announcements");
  });

  it("wraps tags and owner actions inside announcement cards", () => {
    const board = readFileSync(join(process.cwd(), "components/announcements/AnnouncementsBoard.tsx"), "utf8");
    const dashboard = readFileSync(join(process.cwd(), "app/(rnd)/dashboard/page.tsx"), "utf8");

    expect(board).toContain('className="flex max-w-full flex-wrap items-center justify-end gap-2"');
    expect(dashboard).toContain('className="flex max-w-full flex-wrap items-center justify-end gap-1.5"');
    expect(board).not.toMatch(/<Megaphone\b/);
  });

  it("keeps native unread notification cards white", () => {
    const notifications = readFileSync(join(process.cwd(), "../mobile/app/notifications.tsx"), "utf8");

    expect(notifications).not.toContain("bg-[#EAF7F1] border-[#BFE3D3]");
    expect(notifications).toContain("bg-white border-[#E2EAE5]");
  });
});
