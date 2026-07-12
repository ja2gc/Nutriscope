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

  test("renders backend-provided filter options without a duplicate taxonomy", () => {
    expect(source).toContain("meta.filters");
    expect(source).toContain("category_actions");
    expect(source).not.toContain('<option value="patients">');
    expect(source).not.toContain('<option value="procurement">');
    expect(source).not.toContain(["App", "Models"].join("\\"));
    expect(source).not.toContain(["subject", "type"].join("_"));
  });

  test("does not hard-code auth and password security actions", () => {
    expect(source).not.toContain('<option value="login_failed">');
    expect(source).not.toContain('<option value="password_changed">');
    expect(source).not.toContain('<option value="password_reset">');
  });

  test("forwards every audit filter and pagination query parameter", async () => {
    const search = new URLSearchParams({
      page: "3",
      per_page: "50",
      actor_id: "00000000-0000-4000-8000-000000000001",
      domain: "patients",
      action: "updated",
      outcome: "success",
      severity: "notice",
      start: "2026-06-01",
      end: "2026-06-30",
    });

    await GET(new NextRequest(`http://localhost/api/admin/audit-logs?${search}`));

    expect(proxyMock).toHaveBeenCalledWith("/admin/audit-logs", { search });
  });

  test("renders structured DTO fields only", () => {
    expect(source).toContain("AuditEventTable");
    expect(source).toContain("AuditEventDrawer");
    expect(source).not.toContain(`log.${["pro", "perties"].join("")}`);
    expect(source).not.toContain(["JSON", "stringify"].join("."));
    expect(source).not.toContain("log.causer");
    expect(source).not.toContain(`log.${["subject", "type"].join("_")}`);
  });
});
