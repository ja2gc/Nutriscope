import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function POST(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const body = await req.json();
  const laravelRes = await fetch(`${LARAVEL_API}/fss/inventory/${id}/restock`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(body),
  });

  const data = await laravelRes.json();
  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Restock failed.", errors: data.errors },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 200 });
}
