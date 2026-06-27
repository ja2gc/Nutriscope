import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

async function getToken() {
  const cookieStore = await cookies();
  return cookieStore.get("nutriscope_token")?.value;
}

export async function POST(req: NextRequest) {
  const token = await getToken();
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const body = await req.json();
  const res = await fetch(`${LARAVEL_API}/fss/fs-items`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });

  const data = await res.json();
  if (!res.ok) return NextResponse.json({ message: data.message ?? "Failed.", errors: data.errors }, { status: res.status });
  return NextResponse.json(data, { status: res.status });
}
