import { NextRequest } from "next/server";
import { NextResponse } from "next/server";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function POST(req: NextRequest) {
  const body = await req.json();
  const res = await fetch(`${LARAVEL_API}/auth/reset-password`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));

  return NextResponse.json(data, { status: res.status });
}
