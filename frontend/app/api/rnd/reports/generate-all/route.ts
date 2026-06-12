import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function POST(req: NextRequest) {
  return proxy("/rnd/reports/generate-all", { method: "POST", body: await req.json() });
}
