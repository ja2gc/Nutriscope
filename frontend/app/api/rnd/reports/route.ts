import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/rnd/reports");
}
