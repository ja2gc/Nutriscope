import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  return proxy("/rnd/reports/demographic_census/summary", { search: req.nextUrl.searchParams });
}
