import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const laravelRes = await fetch(`${LARAVEL_API}/rnd/recipes/${id}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });

  if (!laravelRes.ok) {
    const data = await laravelRes.json().catch(() => ({}));
    return NextResponse.json({ message: data.message ?? "Not found." }, { status: laravelRes.status });
  }

  return NextResponse.json(await laravelRes.json(), { status: 200 });
}

export async function PUT(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const body = await req.json();

  const laravelRes = await fetch(`${LARAVEL_API}/rnd/recipes/${id}`, {
    method: "PUT",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(body),
  });

  const data = await laravelRes.json();
  if (!laravelRes.ok) {
    return NextResponse.json({ message: data.message ?? "Failed to update recipe.", errors: data.errors }, { status: laravelRes.status });
  }

  return NextResponse.json(data, { status: 200 });
}

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const laravelRes = await fetch(`${LARAVEL_API}/rnd/recipes/${id}`, {
    method: "DELETE",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
  });

  if (laravelRes.status === 204) return new NextResponse(null, { status: 204 });

  const data = await laravelRes.json().catch(() => ({}));
  return NextResponse.json({ message: data.message ?? "Failed to delete." }, { status: laravelRes.status });
}
