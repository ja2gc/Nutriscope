import { NextRequest } from 'next/server';
import { proxy } from '@/lib/laravelProxy';

export async function PATCH(request: NextRequest, { params }: { params: Promise<{ ncpRecordId: string; mealPlanId: string }> }) {
  const { ncpRecordId, mealPlanId } = await params;
  return proxy(`/rnd/ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}/carbs`, { method: 'PATCH', body: await request.json() });
}
