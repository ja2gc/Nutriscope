import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const page = readFileSync(join(process.cwd(), "app/admin/users/page.tsx"), "utf8");

describe("admin user name form baseline", () => {
  test("the pre-migration form uses one explicit legacy name state", () => {
    expect(page).toContain('const [name, setName] = useState("")');
    expect(page).toMatch(/const payload: CreateUserPayload = \{[\s\S]*?name,/);
    expect(page).toContain("const payload: UpdateUserPayload = { name, email, role, is_active: isActive }");
    expect(page).toContain("createUser(payload)");
    expect(page).toContain("updateUser(editingUser.id, payload)");
  });
});
