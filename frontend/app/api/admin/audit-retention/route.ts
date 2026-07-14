import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/admin/audit-retention");
}

export async function PUT(req: NextRequest) {
  return proxy("/admin/audit-retention", { method: "PUT", body: await req.json() });
}
