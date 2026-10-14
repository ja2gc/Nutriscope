import { NextRequest, NextResponse } from "next/server";
import { expect, test, vi } from "vitest";
import { proxy } from "@/lib/laravelProxy";
import { POST } from "./[id]/approve/route";

vi.mock("@/lib/laravelProxy", () => ({ proxy: vi.fn() }));

test("PO conversion reaches the existing shopping-list approval endpoint", async () => {
  vi.mocked(proxy).mockResolvedValueOnce(NextResponse.json({ data: { purchase_order_id: "po-1" } }, { status: 201 }));
  const response = await POST(new NextRequest("http://localhost/api/fss/shopping-lists/list-1/approve", { method: "POST" }), {
    params: Promise.resolve({ id: "list-1" }),
  });
  expect(proxy).toHaveBeenCalledWith("/fss/shopping-lists/list-1/approve", { method: "POST" });
  expect(response.status).toBe(201);
});
