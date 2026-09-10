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
});
