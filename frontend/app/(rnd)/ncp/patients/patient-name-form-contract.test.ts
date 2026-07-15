import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const page = readFileSync(join(process.cwd(), "app/(rnd)/ncp/patients/page.tsx"), "utf8");

describe("patient name form baseline", () => {
  test("the pre-migration create flow submits the one legacy name field", () => {
    expect(page).toContain('const [newName, setNewName] = useState("")');
    expect(page).toMatch(/createPatient\([\s\S]*?name:\s*newName\.trim\(\)/);
  });
});
