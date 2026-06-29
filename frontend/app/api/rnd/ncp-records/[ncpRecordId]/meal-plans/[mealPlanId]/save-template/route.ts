import { NextRequest } from "next/server";

import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string; mealPlanId: string }> };

export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, mealPlanId } = await params;
  const body = await req.json().catch(() => ({}));

  return proxy(`/rnd/ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}/save-template`, {
    method: "POST",
    body,
  });
}
