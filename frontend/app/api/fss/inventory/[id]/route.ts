import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

async function getToken() {
  const cookieStore = await cookies();
  return cookieStore.get("nutriscope_token")?.value;
}

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const token = await getToken();
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const laravelRes = await fetch(`${LARAVEL_API}/fss/inventory/${id}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });

  if (!laravelRes.ok) {
    const data = await laravelRes.json().catch(() => ({}));
    return NextResponse.json({ message: data.message ?? "Not found." }, { status: laravelRes.status });
  }

  return NextResponse.json(await laravelRes.json(), { status: 200 });
}
