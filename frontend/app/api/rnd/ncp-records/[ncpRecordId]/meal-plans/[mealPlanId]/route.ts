import { NextRequest } from "next/server";

import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string; mealPlanId: string }> };

export async function GET(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, mealPlanId } = await params;

  return proxy(`/rnd/ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}`);
}

export async function PATCH(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, mealPlanId } = await params;
  const body = await req.json().catch(() => ({}));

  return proxy(`/rnd/ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}`, {
    method: "PATCH",
    body,
  });
}

export async function DELETE(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, mealPlanId } = await params;

  return proxy(`/rnd/ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}`, {
    method: "DELETE",
  });
}
