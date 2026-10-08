import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/admin/reports/archive-settings");
}

export async function PUT(request: NextRequest) {
  return proxy("/admin/reports/archive-settings", { method: "PUT", body: await request.json() });
}
