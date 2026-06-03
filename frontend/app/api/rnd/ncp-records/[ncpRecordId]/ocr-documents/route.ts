import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ ncpRecordId: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { ncpRecordId } = await params;

  const laravelRes = await fetch(`${LARAVEL_API}/rnd/ncp-records/${ncpRecordId}/ocr-documents`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
  });

  if (!laravelRes.ok) {
    const data = await laravelRes.json().catch(() => ({}));
    return NextResponse.json(
      { message: data.message ?? "Failed to fetch ocr documents from backend." },
      { status: laravelRes.status }
    );
  }

  const data = await laravelRes.json();
  return NextResponse.json(data, { status: 200 });
}
