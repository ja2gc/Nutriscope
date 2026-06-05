import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
type Ctx = { params: Promise<{ ncpRecordId: string; mealPlanId: string }> };

async function proxy(req: NextRequest, path: string) {
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const hasBody = req.method !== 'GET' && req.method !== 'DELETE';
  const res = await fetch(`${API}/api/rnd/${path}`, {
    method: req.method,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: hasBody ? await req.text() : undefined,
  });
  if (res.status === 204) return new NextResponse(null, { status: 204 });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}

export async function GET(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, mealPlanId } = await params;
  return proxy(req, `ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}`);
}
export async function PATCH(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, mealPlanId } = await params;
  return proxy(req, `ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}`);
}
export async function DELETE(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, mealPlanId } = await params;
  return proxy(req, `ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}`);
}
