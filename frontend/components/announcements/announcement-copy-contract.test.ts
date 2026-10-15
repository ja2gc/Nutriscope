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
    expect(board).toContain("post.can_edit &&");
    expect(board).toContain("post.can_delete &&");
    expect(board).toContain("selectedPost.can_edit &&");
  });

  it("requires announcement body in both composers", () => {
    for (const path of ["components/announcements/AnnouncementsBoard.tsx", "app/(rnd)/dashboard/page.tsx"]) {
      const source = readFileSync(join(process.cwd(), path), "utf8");
      expect(source).toMatch(/if \(!draft\.body\.trim\(\)\)/);
      expect(source).toMatch(/Body\s*<span[^>]*>\*<\/span>/);
      expect(source).toMatch(/<textarea[\s\S]*?name="body"[\s\S]*?required/);
    }
  });

  it("previews three lines with a detail cue in the announcement feed while preserving the dashboard card", () => {
    const board = readFileSync(join(process.cwd(), "components/announcements/AnnouncementsBoard.tsx"), "utf8");
    const dashboard = readFileSync(join(process.cwd(), "app/(rnd)/dashboard/page.tsx"), "utf8");
    expect(board).toContain("line-clamp-3");
    expect(board).toContain("Read more");
    expect(board).toMatch(/<article[\s\S]*?role="button"[\s\S]*?onClick=\{\(\) => openViewer\(post\)\}/);
    expect(dashboard).toContain("line-clamp-1");
    expect(dashboard).toContain("const ANNOUNCEMENTS_PER_PAGE = 3;");
    expect(dashboard).not.toContain("Read more");
  });

  it("keeps native unread notification cards white", () => {
    const notifications = readFileSync(join(process.cwd(), "../mobile/app/notifications.tsx"), "utf8");

    expect(notifications).not.toContain("bg-[#EAF7F1] border-[#BFE3D3]");
    expect(notifications).toContain("bg-white border-[#E2EAE5]");
  });
});
