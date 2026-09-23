import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

describe("recovery email removal", () => {
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
