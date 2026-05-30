import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

async function currentToken() {
  const cookieStore = await cookies();
  return cookieStore.get("nutriscope_token")?.value;
}

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const token = await currentToken();

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { id } = await params;
  const body = await req.json();

  const laravelRes = await fetch(`${LARAVEL_API}/rnd/announcements/${id}`, {
    method: "PATCH",
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
      { message: data.message ?? "Failed to update announcement." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 200 });
}

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const token = await currentToken();

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { id } = await params;

  const laravelRes = await fetch(`${LARAVEL_API}/rnd/announcements/${id}`, {
    method: "DELETE",
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
  });

  if (!laravelRes.ok) {
    const data = await laravelRes.json().catch(() => ({}));
    return NextResponse.json(
      { message: data.message ?? "Failed to delete announcement." },
      { status: laravelRes.status }
    );
  }

  return new NextResponse(null, { status: 204 });
}
