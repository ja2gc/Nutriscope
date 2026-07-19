import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function POST(request: NextRequest) {
  return proxy("/auth/onboarding", { method: "POST", body: await request.json() });
}
