import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function POST(
  req: NextRequest,
  { params }: { params: Promise<{ ncpRecordId: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { ncpRecordId } = await params;
  const formData = await req.formData().catch(() => null);

  if (!formData || !formData.has("file")) {
    return NextResponse.json({ message: "No file uploaded." }, { status: 400 });
  }

  const laravelRes = await fetch(`${LARAVEL_API}/rnd/ncp-records/${ncpRecordId}/upload-screening`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
    body: formData,
  });

  const data = await laravelRes.json().catch(() => ({}));

  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Failed to upload screening document to backend." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 202 });
}
