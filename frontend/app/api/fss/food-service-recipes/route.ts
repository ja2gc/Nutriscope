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

  const incoming = new URL(req.url).searchParams;
  const query = new URLSearchParams();
  for (const key of ["category", "search", "page", "per_page"]) {
    const value = incoming.get(key);
    if (value) query.set(key, value);
  }
  const suffix = query.size ? `?${query}` : "";
  const res = await fetch(`${LARAVEL_API}/fss/food-service-recipes${suffix}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    return NextResponse.json({ message: data.message ?? "Failed." }, { status: res.status });
  }

  return NextResponse.json(await res.json(), { status: 200 });
}

export async function POST(req: NextRequest) {
  const token = await getToken();
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const body = await req.json();
  const res = await fetch(`${LARAVEL_API}/fss/food-service-recipes`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });

  const data = await res.json();
  if (!res.ok) return NextResponse.json({ message: data.message ?? "Failed.", errors: data.errors }, { status: res.status });
  return NextResponse.json(data, { status: 201 });
}
