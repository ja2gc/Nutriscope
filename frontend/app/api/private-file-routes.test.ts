import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";

describe("private file routes", () => {
  const routes = [
    ["auth/profile-photo/route.ts", 'privateBinaryProxy("/auth/profile-photo")'],
    ["announcements/[id]/author-photo/route.ts", 'privateBinaryProxy(`/announcements/${id}/author-photo`)'],
    ["fss/purchase-order-attachments/[id]/file/route.ts", 'privateBinaryProxy(`/fss/purchase-order-attachments/${id}/file`)'],
  ] as const;

  for (const [relativePath, forwardingCall] of routes) {
    it(`forwards ${relativePath} through the authenticated binary proxy`, () => {
      const path = join(process.cwd(), "app/api", relativePath);
      expect(existsSync(path)).toBe(true);
      expect(readFileSync(path, "utf8")).toContain(forwardingCall);
    });
  }
});
