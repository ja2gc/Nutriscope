import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

describe("recovery email removal", () => {
  test("profile separates current status from the add or replacement field", () => {
    const profile = readFileSync(join(process.cwd(), "components/profile/ProfilePageShell.tsx"), "utf8");

    expect(profile).toContain("Current Recovery Email");
    expect(profile).toContain("No verified recovery email");
    expect(profile).toContain("Pending verification");
    expect(profile).toContain('user?.recovery_email_verified ? "New Recovery Email" : "Recovery Email"');
    expect(profile).toContain('user.pending_recovery_email ?? (user.recovery_email_verified ? "" : user.recovery_email ?? "")');
    expect(profile).toContain('const [editingRecoveryEmail, setEditingRecoveryEmail] = useState(false)');
    expect(profile).toContain("Change Recovery Email");
    expect(profile).toContain("Add Recovery Email");
    expect(profile).toContain("Cancel");
  });

  test("password fields are hidden until the user chooses to change the password", () => {
    const profile = readFileSync(join(process.cwd(), "components/profile/ProfilePageShell.tsx"), "utf8");

    expect(profile).toContain('const [editingPassword, setEditingPassword] = useState(false)');
    expect(profile).toContain("Change Password");
    expect(profile).toContain("Cancel");
  });

  test("web profile can remove a verified recovery email", () => {
    const profile = readFileSync(join(process.cwd(), "components/profile/ProfilePageShell.tsx"), "utf8");
    const service = readFileSync(join(process.cwd(), "services/authService.ts"), "utf8");
    const route = readFileSync(join(process.cwd(), "app/api/auth/recovery-email/route.ts"), "utf8");

    expect(service).toContain("export async function removeRecoveryEmail");
    expect(service).toContain('method: "DELETE"');
    expect(profile).toContain("removeRecoveryEmail");
    expect(profile).toContain("Remove Recovery Email");
    expect(route).toContain("export async function DELETE");
    expect(route).toContain('method: "DELETE"');
  });
});
