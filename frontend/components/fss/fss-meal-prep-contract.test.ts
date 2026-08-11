import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const frontend = path.resolve(__dirname, "../..");
const read = (relative: string) => fs.readFileSync(path.join(frontend, relative), "utf8");

describe("FSS meal-prep web workflow", () => {
  it("adds exact authenticated proxy routes", () => {
    expect(read("app/api/fss/meal-prep-logs/route.ts")).toContain('proxy("/fss/meal-prep-logs"');
    expect(read("app/api/fss/meal-prep-logs/[id]/reverse/route.ts")).toContain('`/fss/meal-prep-logs/${id}/reverse`');
    expect(read("app/api/fss/meal-prep-logs/[id]/reverse/route.ts")).toContain('method: "POST"');
    expect(read("app/api/fss/menu-cycles/[id]/complete-day/route.ts")).toContain('`/fss/menu-cycles/${id}/complete-day`');
    expect(read("app/api/fss/menu-cycles/[id]/complete-day/route.ts")).toContain('method: "POST"');
  });

  it("uses existing service authority and explicit reversal confirmation", () => {
    const component = read("components/fss/FssMealPrep.tsx");
    for (const symbol of ["listCycles", "getCycle", "listServiceLogs", "completeServiceDay", "reverseServiceDay"]) {
      expect(component).toContain(symbol);
    }
    expect(component).toContain("window.confirm");
    expect(component).toContain('min="1"');
  });
});
