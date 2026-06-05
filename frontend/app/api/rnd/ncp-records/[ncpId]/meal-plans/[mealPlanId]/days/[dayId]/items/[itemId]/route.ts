import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ ncpId: string; mealPlanId: string; dayId: string; itemId: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { ncpId, mealPlanId, dayId, itemId } = await params;

  const laravelRes = await fetch(
    `${LARAVEL_API}/rnd/ncp-records/${ncpId}/meal-plans/${mealPlanId}/days/${dayId}/items/${itemId}`,
    {
      method: "DELETE",
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    }
  );

  if (laravelRes.status === 204) return new NextResponse(null, { status: 204 });

  const data = await laravelRes.json().catch(() => ({}));
  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Failed to remove meal plan item." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 200 });
}
