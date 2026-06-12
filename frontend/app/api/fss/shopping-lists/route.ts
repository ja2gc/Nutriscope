import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/fss/shopping-lists");
}

export async function POST(req: NextRequest) {
  return proxy("/fss/shopping-lists", { method: "POST", body: await req.json() });
}
