import { NextRequest } from "next/server";

import { proxy } from "@/lib/laravelProxy";

export async function GET(_req: NextRequest) {
  return proxy("/rnd/meal-plan-templates");
}
