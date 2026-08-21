import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const frontend = path.resolve(__dirname, "../..");
const read = (relative: string) => fs.readFileSync(path.join(frontend, relative), "utf8");

describe("FSS app authentication contract", () => {
  it("has a dedicated in-scope login that identifies app traffic", () => {
    expect(read("app/fss/login/page.tsx")).toContain("FssLogin");
    const login = read("components/fss/FssLogin.tsx");
    expect(login).toContain('loginUser(email, password, "app")');
    expect(login).toContain('"/fss/account-setup"');
    expect(login).toContain(': "/fss"');
  });

  it("keeps session expiry and sign-out inside the FSS app", () => {
    const middleware = read("middleware.ts");
    expect(middleware).toContain('pathname === "/fss/login"');
    expect(middleware).toContain('new URL("/fss/login", request.url)');

    const shell = read("components/fss/FssShell.tsx");
    expect(shell).toContain('router.replace("/fss/login")');
    expect(shell).toContain('pathname === "/fss/login"');
    expect(shell).toContain('pathname === "/fss/account-setup"');
  });

  it("reuses account setup without leaving app scope", () => {
    expect(read("app/account-setup/page.tsx")).toContain("AccountSetup");
    expect(read("app/fss/account-setup/page.tsx")).toContain("appMode");
    const setup = read("components/auth/AccountSetup.tsx");
    expect(setup).toContain('appMode ? "/fss"');
    expect(setup).toContain('appMode ? "/fss/login"');
  });
});
