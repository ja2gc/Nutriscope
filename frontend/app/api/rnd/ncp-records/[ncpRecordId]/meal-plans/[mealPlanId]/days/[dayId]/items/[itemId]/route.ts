import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";
type Ctx = { params: Promise<{ ncpRecordId: string; mealPlanId: string; dayId: string; itemId: string }> };

async function itemProxy(req: NextRequest, ctx: Ctx) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { ncpRecordId, mealPlanId, dayId, itemId } = await ctx.params;
  const url = `${LARAVEL_API}/rnd/ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}/days/${dayId}/items/${itemId}`;

  const hasBody = req.method === 'PATCH';
  const res = await fetch(url, {
    method: req.method,
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
      ...(hasBody ? { 'Content-Type': 'application/json' } : {}),
    },
    body: hasBody ? await req.text() : undefined,
  });

  if (res.status === 204) return new NextResponse(null, { status: 204 });
  const data = await res.json().catch(() => ({}));
  return NextResponse.json(data, { status: res.status });
}

export async function PATCH(req: NextRequest, ctx: Ctx) { return itemProxy(req, ctx); }
export async function DELETE(req: NextRequest, ctx: Ctx) { return itemProxy(req, ctx); }
