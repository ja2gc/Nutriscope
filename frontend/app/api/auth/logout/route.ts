import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function POST(_req: NextRequest) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  // Always clear the cookie first — ensures logout even if backend is unreachable
  const res = NextResponse.json({ message: "Logged out." }, { status: 200 });
  res.cookies.delete("nutriscope_token");

  if (token) {
    try {
      await fetch(`${LARAVEL_API}/auth/logout`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
        },
      });
    } catch {
      // Best-effort: token already cleared client-side
    }
  }

  return res;
}
