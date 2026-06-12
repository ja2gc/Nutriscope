import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/fss/budgets");
}

export async function POST(req: NextRequest) {
  return proxy("/fss/budgets", { method: "POST", body: await req.json() });
}
