import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

async function token() {
  return (await cookies()).get("nutriscope_token")?.value;
}

export async function PATCH(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const t = await token();
  if (!t) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const body = await req.json();
  const res = await fetch(`${LARAVEL_API}/fss/suppliers/${id}`, {
    method: "PATCH",
    headers: { Authorization: `Bearer ${t}`, "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  return NextResponse.json(data, { status: res.status });
}

export async function DELETE(_req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const t = await token();
  if (!t) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const res = await fetch(`${LARAVEL_API}/fss/suppliers/${id}`, {
    method: "DELETE",
    headers: { Authorization: `Bearer ${t}`, Accept: "application/json" },
  });
  if (res.status === 204) return new NextResponse(null, { status: 204 });
  const data = await res.json().catch(() => ({}));
  return NextResponse.json(data, { status: res.status });
}
