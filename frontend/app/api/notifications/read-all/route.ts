import { proxy } from "@/lib/laravelProxy";

export async function PATCH() {
  return proxy("/notifications/read-all", { method: "PATCH" });
}
