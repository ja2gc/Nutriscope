import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

describe("first-login onboarding contract", () => {
  test("account setup uses separate password, recovery email, and OTP stages", () => {
    const setup = readFileSync(join(process.cwd(), "components/auth/AccountSetup.tsx"), "utf8");

    expect(setup).toContain("user.must_change_password");
    expect(setup).toContain(">Next</Button>");
    expect(setup).toContain("Send verification code");
    expect(setup).toContain("verifyRecoveryEmail");
    expect(setup).toContain("Verification code");
    expect(setup).toContain("Send another code");
    expect(setup).not.toContain("Save and send code");
    expect(setup).not.toContain("ShieldCheck");
    expect(setup).not.toContain("If deferred, this reminder stays visible");
    expect(setup).not.toContain("No email verification code is needed");
    expect(setup).not.toContain("Recovery email:");
    expect(setup).not.toContain(">{recoveryEmail}</span>");
  });

  test("reload keeps password-complete users on recovery verification stage", () => {
    const setup = readFileSync(join(process.cwd(), "components/auth/AccountSetup.tsx"), "utf8");

    expect(setup).toContain("!user.must_change_password && user.must_set_recovery_email");
    expect(setup).toContain("verificationEmail");
    expect(setup).toContain("verificationStage");
  });

  test("does not reopen mandatory setup after user chose do later", () => {
    const login = readFileSync(join(process.cwd(), "app/login/page.tsx"), "utf8");
    expect(login).toContain("user.onboarding_required && !user.onboarding_skipped");
  });

  test("settings refreshes reminder state after password completion", () => {
    const profile = readFileSync(join(process.cwd(), "components/profile/ProfilePageShell.tsx"), "utf8");
    const passwordHandler = profile.slice(profile.indexOf("async function handlePasswordSubmit"), profile.indexOf("async function handleRecoveryEmailSubmit"));
    expect(passwordHandler).toContain("await refreshUser()");
  });

  test("profile requires OTP while recovery setup remains incomplete", () => {
    const profile = readFileSync(join(process.cwd(), "components/profile/ProfilePageShell.tsx"), "utf8");

    expect(profile).not.toContain("No verification code is needed");
    expect(profile).not.toContain("!user?.must_set_recovery_email\n            &&");
    expect(profile).toContain("Verify Recovery Email");
  });

  test("persistent reminder is driven by server onboarding state", () => {
    const reminder = readFileSync(join(process.cwd(), "components/auth/OnboardingReminder.tsx"), "utf8");

    expect(reminder).toContain("user?.onboarding_required");
    expect(reminder).toContain("user.onboarding_skipped");
    expect(reminder).toContain('pathname === "/account-setup"');
    expect(reminder).toContain("Profile settings");
  });
});
