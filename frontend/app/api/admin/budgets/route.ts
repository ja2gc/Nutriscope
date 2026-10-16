import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/admin/budgets");
}

export async function POST(req: NextRequest) {
  return proxy("/admin/budgets", { method: "POST", body: await req.json() });
}
