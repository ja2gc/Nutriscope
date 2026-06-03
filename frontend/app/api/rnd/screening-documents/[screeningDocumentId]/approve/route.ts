import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ screeningDocumentId: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { screeningDocumentId } = await params;
  const body = await req.json().catch(() => null);

  if (!body || typeof body !== "object") {
    return NextResponse.json({ message: "Invalid request body." }, { status: 400 });
  }

  const laravelRes = await fetch(`${LARAVEL_API}/rnd/screening-documents/${screeningDocumentId}/approve`, {
    method: "PATCH",
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(body),
  });

  const data = await laravelRes.json().catch(() => ({}));

  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Failed to approve screening document." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 200 });
}
