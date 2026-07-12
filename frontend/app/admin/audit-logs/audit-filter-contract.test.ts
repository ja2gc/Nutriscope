import { readFileSync } from "node:fs";
import { join } from "node:path";
import { NextRequest, NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { GET } from "@/app/api/admin/audit-logs/route";
import { proxy } from "@/lib/laravelProxy";

vi.mock("@/lib/laravelProxy", () => ({
  proxy: vi.fn(),
}));

const proxyMock = vi.mocked(proxy);

const source = readFileSync(join(process.cwd(), "app/admin/audit-logs/page.tsx"), "utf8");

describe("admin audit filter contract", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: [] }, { status: 200 }));
  });

  test("uses structured domain filters without backend model class names", () => {
    expect(source).toContain('value="patients"');
    expect(source).toContain('value="procurement"');
    expect(source).not.toContain("App\\\\Models");
    expect(source).not.toContain("subject_type");
  });

  test("includes auth and password security events", () => {
    expect(source).toContain('value="login_failed"');
    expect(source).toContain('value="password_changed"');
    expect(source).toContain('value="password_reset"');
  });

  test("forwards every audit filter and pagination query parameter", async () => {
    const search = new URLSearchParams({
      page: "3",
      per_page: "50",
      actor_id: "00000000-0000-4000-8000-000000000001",
      domain: "patients",
      action: "updated",
      start: "2026-06-01",
      end: "2026-06-30",
    });

    await GET(new NextRequest(`http://localhost/api/admin/audit-logs?${search}`));

    expect(proxyMock).toHaveBeenCalledWith("/admin/audit-logs", { search });
  });

  test("renders structured DTO fields without raw properties or JSON", () => {
    expect(source).toContain("log.occurred_at");
    expect(source).toContain("log.details");
    expect(source).toContain("log.changes");
    expect(source).not.toContain("log.properties");
    expect(source).not.toContain("JSON.stringify");
    expect(source).not.toContain("log.causer");
    expect(source).not.toContain("log.subject_type");
  });
});
