import { proxy } from "@/lib/laravelProxy";

export async function GET(req: Request) {
  return proxy("/rnd/accomplishment-periods/to-review", { search: new URL(req.url).searchParams });
}
