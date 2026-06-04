import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function POST(
  _req: NextRequest,
  { params }: { params: Promise<{ fdcId: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { fdcId } = await params;
  const laravelRes = await fetch(`${LARAVEL_API}/rnd/usda/import/${fdcId}`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });

  const data = await laravelRes.json();
  if (!laravelRes.ok) {
    return NextResponse.json({ message: data.message ?? "Import failed." }, { status: laravelRes.status });
  }

  return NextResponse.json(data, { status: 201 });
}
