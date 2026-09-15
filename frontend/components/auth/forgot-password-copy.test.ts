import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";

const source = (path: string) => readFileSync(resolve(process.cwd(), path), "utf8");

describe("forgot-password recovery address copy", () => {
  test("distinguishes the user's recovery email from sign-in and system sender addresses", () => {
    const web = source("app/forgot-password/page.tsx");
    const mobile = source("../mobile/app/forgot-password.tsx");
    const explanation = "This is the recovery email saved in your profile, not your sign-in email or the system sender address.";

    expect(web).toContain(explanation);
    expect(mobile).toContain(explanation);
  });
});
