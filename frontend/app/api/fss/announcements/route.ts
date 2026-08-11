import { proxy } from "@/lib/laravelProxy";

export async function GET(request: Request) {
  return proxy("/fss/announcements", { search: new URL(request.url).searchParams });
}
