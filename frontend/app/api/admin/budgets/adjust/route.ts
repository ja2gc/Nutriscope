import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function POST(req: NextRequest) {
  return proxy("/admin/budgets/adjust", { method: "POST", body: await req.json() });
}
