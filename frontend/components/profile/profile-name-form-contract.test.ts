import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const profile = readFileSync(join(process.cwd(), "components/profile/ProfilePageShell.tsx"), "utf8");
const topBar = readFileSync(join(process.cwd(), "components/layout/TopBar.tsx"), "utf8");

describe("profile split-name form", () => {
  test("packs settings cards responsively on desktop", () => {
    expect(profile).toContain("grid-cols-[repeat(auto-fit,minmax(min(100%,20rem),1fr))]");
  });

  test("shows separate optional edit fields and omits an untouched legacy name", () => {
    expect(profile).toContain('const [firstName, setFirstName] = useState("")');
    expect(profile).toContain('const [lastName, setLastName] = useState("")');
    expect(profile).toContain('label="First Name"');
    expect(profile).toContain('label="Last Name"');
    expect(profile).toContain("changedPersonNameFields(user, firstName, lastName)");
    expect(profile).toContain("...(nameFields ?? {})");
    expect(profile).not.toContain('label="Full Name"');
  });

  test("shows saved account details before editing and never edits sign-in email", () => {
    expect(profile).toContain("const [editingProfile, setEditingProfile] = useState(false)");
    expect(profile).toContain("Edit Profile");
    expect(profile).toContain("Cancel");
    expect(profile).toContain("user?.email");
    expect(profile).not.toContain('label="Sign-in Email"');
    expect(profile).not.toContain("email,");
    expect(profile).not.toContain("setEmail");
  });

  test("top bar text and image alternative use the display-name contract", () => {
    expect(topBar).toContain("personDisplayName(user)");
    expect(topBar).toContain("alt={personDisplayName(user)}");
  });
});
