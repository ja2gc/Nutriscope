import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ ncpRecordId: string; mealPlanId: string; dayId: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { ncpRecordId, mealPlanId, dayId } = await params;

  const laravelRes = await fetch(
    `${LARAVEL_API}/rnd/ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}/days/${dayId}/items`,
    {
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    }
  );

  const data = await laravelRes.json().catch(() => ({}));
  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Failed to fetch meal plan items." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 200 });
}

export async function POST(
  req: NextRequest,
  { params }: { params: Promise<{ ncpRecordId: string; mealPlanId: string; dayId: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { ncpRecordId, mealPlanId, dayId } = await params;
  const body = await req.json().catch(() => ({}));

  const laravelRes = await fetch(
    `${LARAVEL_API}/rnd/ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}/days/${dayId}/items`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(body),
    }
  );

  const data = await laravelRes.json().catch(() => ({}));
  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Failed to add meal plan item." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 201 });
}
