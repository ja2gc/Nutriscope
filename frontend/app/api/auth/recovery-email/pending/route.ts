import { proxy } from "@/lib/laravelProxy";

export async function DELETE() {
  return proxy("/auth/recovery-email/pending", { method: "DELETE" });
}
