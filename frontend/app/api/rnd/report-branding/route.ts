import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";
import { proxy } from "@/lib/laravelProxy";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function GET() {
  return proxy("/rnd/report-branding");
}

/** Forwards multipart form data (branding text fields + optional logo files). */
export async function POST(req: NextRequest) {
  const token = (await cookies()).get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const form = await req.formData();
  const res = await fetch(`${LARAVEL_API}/rnd/report-branding`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    body: form,
  });

  const data = await res.json().catch(() => ({}));
  return NextResponse.json(data, { status: res.status });
}
