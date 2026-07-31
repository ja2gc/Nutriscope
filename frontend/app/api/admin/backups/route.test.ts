import { NextRequest, NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";
import { proxy } from "@/lib/laravelProxy";
import { GET, POST } from "./route";
import { DELETE } from "./[id]/route";
import { POST as keep } from "./[id]/keep/route";
import { POST as recover } from "./[id]/recovery-requests/route";

vi.mock("@/lib/laravelProxy", () => ({ proxy: vi.fn() }));

const proxyMock = vi.mocked(proxy);
const context = { params: Promise.resolve({ id: "backup-1" }) };

describe("/api/admin/backups proxy routes", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: null }));
  });

  test("lists and creates backups", async () => {
    await GET();
    await POST();

    expect(proxyMock).toHaveBeenNthCalledWith(1, "/admin/backups");
    expect(proxyMock).toHaveBeenNthCalledWith(2, "/admin/backups", { method: "POST" });
  });

  test("deletes and keeps a selected backup", async () => {
    const request = new NextRequest("http://localhost/api/admin/backups/backup-1");
    await DELETE(request, context);
    await keep(request, context);

    expect(proxyMock).toHaveBeenNthCalledWith(1, "/admin/backups/backup-1", { method: "DELETE" });
    expect(proxyMock).toHaveBeenNthCalledWith(2, "/admin/backups/backup-1/keep", { method: "POST" });
  });

  test("forwards a recovery request body", async () => {
    await recover(new NextRequest("http://localhost/api/admin/backups/backup-1/recovery-requests", {
      method: "POST",
      body: JSON.stringify({ incident_type: "damaged_database", note: "Restore requested after a failed release." }),
    }), context);

    expect(proxyMock).toHaveBeenCalledWith("/admin/backups/backup-1/recovery-requests", {
      method: "POST",
      body: { incident_type: "damaged_database", note: "Restore requested after a failed release." },
    });
  });
});
