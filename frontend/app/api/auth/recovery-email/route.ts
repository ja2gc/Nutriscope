import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function PATCH(req: NextRequest) {
  const body = await req.json();
  return proxy("/auth/recovery-email", { method: "PATCH", body });
}

export async function DELETE() {
  return proxy("/auth/recovery-email", { method: "DELETE" });
}
