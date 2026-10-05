import { NextRequest } from "next/server";

import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string; mealPlanId: string }> };

export async function POST(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, mealPlanId } = await params;

  return proxy(`/rnd/ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}/scale-to-prescription`, {
    method: "POST",
  });
}
