import { NextRequest, NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { proxy } from "@/lib/laravelProxy";
import { PATCH as updateProfile } from "./auth/profile/route";
import { POST as createAdminUser } from "./admin/users/route";
import { PATCH as updateAdminUser } from "./admin/users/[id]/route";
import { GET as listPatients, POST as createPatient } from "./patients/route";
import { PATCH as updatePatient } from "./patients/[id]/route";

vi.mock("@/lib/laravelProxy", () => ({
  proxy: vi.fn(),
}));

const proxyMock = vi.mocked(proxy);
const splitName = { first_name: "Maria Luisa", last_name: "De la Cruz" };

function request(path: string, method: string, body: object) {
  return new NextRequest(`http://localhost${path}`, {
    method,
    body: JSON.stringify(body),
  });
}

describe("person-name Next.js proxies", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: null }, { status: 200 }));
  });

  test("profile forwards split names unchanged", async () => {
    const body = { ...splitName, email: "maria@example.test" };
    await updateProfile(request("/api/auth/profile", "PATCH", body));
    expect(proxyMock).toHaveBeenCalledWith("/auth/profile", { method: "PATCH", body });
  });

  test("admin create and update forward split names unchanged", async () => {
    const body = { ...splitName, email: "maria@example.test", role: "RND" };
    await createAdminUser(request("/api/admin/users", "POST", body));
    expect(proxyMock).toHaveBeenCalledWith("/admin/users", { method: "POST", body });

    await updateAdminUser(request("/api/admin/users/42", "PATCH", body), {
      params: Promise.resolve({ id: "42" }),
    });
    expect(proxyMock).toHaveBeenCalledWith("/admin/users/42", { method: "PATCH", body });
  });

  test("patient list preserves every compatibility query parameter", async () => {
    await listPatients(new NextRequest("http://localhost/api/patients?search=Maria&status=Active&page=2&per_page=25"));
    expect(proxyMock).toHaveBeenCalledWith("/rnd/patients", {
      search: new URLSearchParams("search=Maria&status=Active&page=2&per_page=25"),
    });
  });

  test("patient create and update forward split names unchanged", async () => {
    const body = { ...splitName, dob: "1990-01-01", sex: "Female" };
    await createPatient(request("/api/patients", "POST", body));
    expect(proxyMock).toHaveBeenCalledWith("/rnd/patients", { method: "POST", body });

    await updatePatient(request("/api/patients/7", "PATCH", body), {
      params: Promise.resolve({ id: "7" }),
    });
    expect(proxyMock).toHaveBeenCalledWith("/rnd/patients/7", { method: "PATCH", body });
  });
});
