import { proxy } from "@/lib/laravelProxy";

export async function POST() {
  return proxy("/auth/onboarding/skip", { method: "POST" });
}
