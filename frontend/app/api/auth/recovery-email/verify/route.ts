import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function POST(req: NextRequest) {
  const body = await req.json();
  return proxy("/auth/recovery-email/verify", { method: "POST", body });
}
