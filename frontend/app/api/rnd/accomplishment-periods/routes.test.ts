import { NextRequest, NextResponse } from "next/server";
import { beforeEach, expect, test, vi } from "vitest";
import { proxy } from "@/lib/laravelProxy";
import { GET as getStaff } from "./staff/route";
import { GET as getToReview } from "./to-review/route";

vi.mock("@/lib/laravelProxy", () => ({ proxy: vi.fn() }));

beforeEach(() => {
  vi.mocked(proxy).mockReset();
  vi.mocked(proxy).mockResolvedValue(NextResponse.json({ data: [] }));
});

test("staff search and page reach Laravel", async () => {
  await getStaff(new NextRequest("http://localhost/api/rnd/accomplishment-periods/staff?search=Ada&page=2"));
  expect(proxy).toHaveBeenCalledWith("/rnd/accomplishment-periods/staff", { search: new URLSearchParams("search=Ada&page=2") });
});

test("selected staff and month reach the review queue", async () => {
  await getToReview(new NextRequest("http://localhost/api/rnd/accomplishment-periods/to-review?fss_user_id=staff-uuid&covered_month=2026-06"));
  expect(proxy).toHaveBeenCalledWith("/rnd/accomplishment-periods/to-review", { search: new URLSearchParams("fss_user_id=staff-uuid&covered_month=2026-06") });
});
