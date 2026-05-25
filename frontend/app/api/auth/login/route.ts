import { NextRequest, NextResponse } from "next/server";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function POST(req: NextRequest) {
  const body = await req.json();

  const laravelRes = await fetch(`${LARAVEL_API}/auth/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });

  const data = await laravelRes.json();

  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Invalid credentials." },
      { status: laravelRes.status }
    );
  }

  const res = NextResponse.json({ user: data.user }, { status: 200 });

  res.cookies.set("nutriscope_token", data.token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: 60 * 60 * 24 * 7, // 7 days
  });

  return res;
}
