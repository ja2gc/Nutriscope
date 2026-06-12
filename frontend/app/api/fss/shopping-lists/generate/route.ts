import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function POST(req: NextRequest) {
  return proxy("/fss/shopping-lists/generate", { method: "POST", body: await req.json() });
}
