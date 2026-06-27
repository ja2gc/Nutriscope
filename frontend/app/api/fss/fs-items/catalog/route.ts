import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

async function getToken() {
  const cookieStore = await cookies();
  return cookieStore.get("nutriscope_token")?.value;
}

export async function GET(req: NextRequest) {
  const token = await getToken();
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const qs = new URL(req.url).searchParams.toString();
  const res = await fetch(`${LARAVEL_API}/fss/fs-items/catalog${qs ? `?${qs}` : ""}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });

  const data = await res.json();
  if (!res.ok) return NextResponse.json({ message: data.message ?? "Failed." }, { status: res.status });
  return NextResponse.json(data, { status: 200 });
}
