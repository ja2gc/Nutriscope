import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/admin/ai-usage-limits");
}

export async function PUT(req: NextRequest) {
  return proxy("/admin/ai-usage-limits", { method: "PUT", body: await req.json() });
}

