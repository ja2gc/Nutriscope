import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  const search = new URL(req.url).searchParams;
  return proxy("/admin/audit-logs", { search });
}
