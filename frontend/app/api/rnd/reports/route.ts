import { proxy } from "@/lib/laravelProxy";

export async function GET(req: Request) {
  return proxy("/rnd/reports", { search: new URL(req.url).searchParams });
}
