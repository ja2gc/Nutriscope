import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/fss/dashboard/summary");
}
