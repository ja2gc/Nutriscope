import { NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function GET() {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    const res = NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
    res.cookies.delete("nutriscope_role");
    return res;
  }

  const laravelRes = await fetch(`${LARAVEL_API}/auth/me`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
  });

  if (!laravelRes.ok) {
    const res = NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
    res.cookies.delete("nutriscope_token");
    res.cookies.delete("nutriscope_role");
    return res;
  }

  const data = await laravelRes.json();
  return NextResponse.json(data, { status: 200 });
}
