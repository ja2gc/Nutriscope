import { proxy } from "@/lib/laravelProxy";

export async function GET(request: Request) {
  return proxy("/admin/ai-usage", {
    search: new URL(request.url).searchParams,
  });
}
