import { proxy } from "@/lib/laravelProxy";

export async function GET(req: Request) {
  return proxy("/admin/accomplishment-periods/staff", { search: new URL(req.url).searchParams });
}
