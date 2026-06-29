import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function GET(req: NextRequest) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { searchParams } = new URL(req.url);
  const targetUrl = new URL(`${LARAVEL_API}/fss/inventory`);
  searchParams.forEach((value, key) => targetUrl.searchParams.append(key, value));

  const laravelRes = await fetch(targetUrl.toString(), {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });

  if (!laravelRes.ok) {
    const data = await laravelRes.json().catch(() => ({}));
    return NextResponse.json(
      { message: data.message ?? "Failed to fetch inventory." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(await laravelRes.json(), { status: 200 });
}
