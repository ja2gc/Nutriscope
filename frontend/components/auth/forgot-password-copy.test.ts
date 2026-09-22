import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";

const source = (path: string) => readFileSync(resolve(process.cwd(), path), "utf8");

describe("forgot-password sign-in email contract", () => {
  test("web and mobile request the sign-in email and explain recovery delivery", () => {
    const web = source("app/forgot-password/page.tsx");
    const mobile = source("../mobile/app/forgot-password.tsx");
    const explanation = "We will send the reset link to the verified recovery email saved for that account.";

    expect(web).toContain('label="Sign-in Email"');
    expect(mobile).toContain("Sign-in Email");
    expect(web).toContain(explanation);
    expect(mobile).toContain(explanation);
    expect(web).not.toContain("Enter your verified recovery email");
    expect(mobile).not.toContain("Enter your verified recovery email");
  });

  test("reset form carries the sign-in email from the reset link", () => {
    const reset = source("app/reset-password/page.tsx");

    expect(reset).toContain('params.get("email")');
    expect(reset).toContain('label="Sign-in Email"');
    expect(reset).not.toContain('label="Recovery Email"');
  });
});
