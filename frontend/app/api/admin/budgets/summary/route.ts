import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  return proxy("/admin/budgets/summary", { search: new URL(req.url).searchParams });
}
