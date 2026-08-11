import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(request: NextRequest) {
  return proxy("/fss/meal-prep-logs", { search: request.nextUrl.searchParams });
}
