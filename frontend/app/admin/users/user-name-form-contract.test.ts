import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const page = readFileSync(join(process.cwd(), "app/admin/users/page.tsx"), "utf8");

describe("admin user split-name form", () => {
  test("creates accounts with required first and last name inputs", () => {
    expect(page).toContain('const [firstName, setFirstName] = useState("")');
    expect(page).toContain('const [lastName, setLastName] = useState("")');
    expect(page).toContain("requiredPersonNameFields(firstName, lastName)");
    expect(page).toMatch(/const payload: CreateUserPayload = \{[\s\S]*?\.\.\.nameFields/);
    expect(page).toContain("First Name");
    expect(page).toContain("Last Name");
    expect(page).toContain('htmlFor="account-first-name"');
    expect(page).toContain('htmlFor="account-last-name"');
    expect(page).toContain('min-h-11');
    expect(page).not.toContain("Full Name");
    expect(page).toContain("createUser(payload)");
  });

  test("omits untouched legacy split fields during unrelated edits", () => {
    expect(page).toContain("changedPersonNameFields(editingUser, firstName, lastName)");
    expect(page).toContain("...(nameFields ?? {})");
    expect(page).toContain("updateUser(editingUser.id, payload)");
  });

  test("renders account labels through the display-name contract", () => {
    expect(page).toContain("personDisplayName(u)");
    expect(page).toContain("personDisplayName(resettingUser)");
  });
});
