import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

async function currentToken() {
  const cookieStore = await cookies();
  return cookieStore.get("nutriscope_token")?.value;
}

export async function GET(req: NextRequest) {
  const token = await currentToken();

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const search = new URL(req.url).searchParams.toString();
  const laravelRes = await fetch(`${LARAVEL_API}/rnd/announcements${search ? `?${search}` : ""}`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
  });

  const data = await laravelRes.json().catch(() => ({}));

  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Failed to fetch announcements from backend." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 200 });
}

export async function POST(req: NextRequest) {
  const token = await currentToken();

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const body = await req.json();

  const laravelRes = await fetch(`${LARAVEL_API}/rnd/announcements`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(body),
  });

  const data = await laravelRes.json().catch(() => ({}));

  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Failed to create announcement." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 201 });
}
