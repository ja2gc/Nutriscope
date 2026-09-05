import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";

describe("report prepare proxies", () => {
  for (const role of ["admin", "rnd", "fss"] as const) {
    it(`forwards ${role} report preparation to Laravel`, () => {
      const path = join(process.cwd(), `app/api/${role}/reports/[id]/prepare/route.ts`);

      expect(existsSync(path)).toBe(true);

      const source = readFileSync(path, "utf8");
      expect(source).toContain("export async function POST");
      expect(source).toContain(`proxy(\`/${role}/reports/\${id}/prepare\``);
      expect(source).toContain('method: "POST"');
      expect(source).toContain("search: req.nextUrl.searchParams");
    });
  }
});
