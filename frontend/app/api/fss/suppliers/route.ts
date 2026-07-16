import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

async function token() {
  return (await cookies()).get("nutriscope_token")?.value;
}

export async function GET(req: NextRequest) {
  const t = await token();
  if (!t) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const search = new URL(req.url).searchParams.toString();
  const res = await fetch(`${LARAVEL_API}/fss/suppliers${search ? `?${search}` : ""}`, {
    headers: { Authorization: `Bearer ${t}`, Accept: "application/json" },
  });
  const data = await res.json().catch(() => ({}));
  return NextResponse.json(data, { status: res.status });
}

export async function POST(req: NextRequest) {
  const t = await token();
  if (!t) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const body = await req.json();
  const res = await fetch(`${LARAVEL_API}/fss/suppliers`, {
    method: "POST",
    headers: { Authorization: `Bearer ${t}`, "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  return NextResponse.json(data, { status: res.status });
}
