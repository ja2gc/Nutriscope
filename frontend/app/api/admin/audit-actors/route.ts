import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(request: NextRequest) {
  const search = new URL(request.url).searchParams;
  return proxy("/admin/audit-actors", { search });
}
