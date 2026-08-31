import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(request: NextRequest) {
  return proxy("/admin/backups", { search: request.nextUrl.searchParams });
}

export async function POST() {
  return proxy("/admin/backups", { method: "POST" });
}
