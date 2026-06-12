import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/rnd/reports");
}

export async function POST(req: NextRequest) {
  return proxy("/rnd/reports", { method: "POST", body: await req.json() });
}
