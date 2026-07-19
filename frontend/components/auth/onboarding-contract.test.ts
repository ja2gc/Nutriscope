import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

describe("first-login onboarding contract", () => {
  test("does not reopen mandatory setup after user chose do later", () => {
    const login = readFileSync(join(process.cwd(), "app/login/page.tsx"), "utf8");
    expect(login).toContain("user.onboarding_required && !user.onboarding_skipped");
  });

  test("settings refreshes reminder state after password completion", () => {
    const profile = readFileSync(join(process.cwd(), "components/profile/ProfilePageShell.tsx"), "utf8");
    const passwordHandler = profile.slice(profile.indexOf("async function handlePasswordSubmit"), profile.indexOf("async function handleRecoveryEmailSubmit"));
    expect(passwordHandler).toContain("await refreshUser()");
  });
});
