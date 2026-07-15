import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const profile = readFileSync(join(process.cwd(), "components/profile/ProfilePageShell.tsx"), "utf8");
const topBar = readFileSync(join(process.cwd(), "components/layout/TopBar.tsx"), "utf8");

describe("profile split-name form", () => {
  test("shows separate optional edit fields and omits an untouched legacy name", () => {
    expect(profile).toContain('const [firstName, setFirstName] = useState("")');
    expect(profile).toContain('const [lastName, setLastName] = useState("")');
    expect(profile).toContain('label="First Name"');
    expect(profile).toContain('label="Last Name"');
    expect(profile).toContain("changedPersonNameFields(user, firstName, lastName)");
    expect(profile).toContain("...(nameFields ?? {})");
    expect(profile).not.toContain('label="Full Name"');
  });

  test("top bar text and image alternative use the display-name contract", () => {
    expect(topBar).toContain("personDisplayName(user)");
    expect(topBar).toContain("alt={personDisplayName(user)}");
  });
});
