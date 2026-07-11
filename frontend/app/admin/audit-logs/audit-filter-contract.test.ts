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

  test("uses backend model class names for subject filters", () => {
    expect(source).toContain('value: "App\\\\Models\\\\User"');
    expect(source).toContain('value: "App\\\\Models\\\\Budget"');
    expect(source).toContain('value: "App\\\\Models\\\\BudgetLedger"');
    expect(source).toContain('value: "App\\\\Models\\\\PurchaseOrder"');
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
      causer_id: "actor-uuid",
      subject_type: "App\\Models\\Patient",
      event: "updated",
      start: "2026-06-01",
      end: "2026-06-30",
    });

    await GET(new NextRequest(`http://localhost/api/admin/audit-logs?${search}`));

    expect(proxyMock).toHaveBeenCalledWith("/admin/audit-logs", { search });
  });
});
