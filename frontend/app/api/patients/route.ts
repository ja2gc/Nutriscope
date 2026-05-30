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
  const search = searchParams.get("search") ?? "";
  const status = searchParams.get("status") ?? "";
  const page = searchParams.get("page") ?? "1";

  const targetUrl = new URL(`${LARAVEL_API}/rnd/patients`);
  if (search) targetUrl.searchParams.append("search", search);
  if (status && status !== "All") targetUrl.searchParams.append("status", status);
  targetUrl.searchParams.append("page", page);

  const laravelRes = await fetch(targetUrl.toString(), {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
  });

  if (!laravelRes.ok) {
    const data = await laravelRes.json().catch(() => ({}));
    return NextResponse.json(
      { message: data.message ?? "Failed to fetch patients from backend." },
      { status: laravelRes.status }
    );
  }

  const data = await laravelRes.json();
  return NextResponse.json(data, { status: 200 });
}

export async function POST(req: NextRequest) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const body = await req.json();

  const laravelRes = await fetch(`${LARAVEL_API}/rnd/patients`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(body),
  });

  const data = await laravelRes.json();

  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Failed to create patient." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 201 });
}
