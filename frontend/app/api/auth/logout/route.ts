import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function POST(_req: NextRequest) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (token) {
    await fetch(`${LARAVEL_API}/auth/logout`, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    });
  }

  const res = NextResponse.json({ message: "Logged out." }, { status: 200 });
  res.cookies.delete("nutriscope_token");
  return res;
}
