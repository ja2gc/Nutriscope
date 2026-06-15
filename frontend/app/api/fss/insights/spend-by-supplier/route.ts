import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  return proxy("/fss/insights/spend-by-supplier", { search: new URL(req.url).searchParams });
}
