import { proxy } from "@/lib/laravelProxy";
import { NextRequest } from "next/server";

export async function GET(req: NextRequest) {
  return proxy("/rnd/meal-plan-templates", { search: new URL(req.url).searchParams });
}
