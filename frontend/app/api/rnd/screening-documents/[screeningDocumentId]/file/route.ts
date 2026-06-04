import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ screeningDocumentId: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { screeningDocumentId } = await params;

  const laravelRes = await fetch(
    `${LARAVEL_API}/rnd/screening-documents/${screeningDocumentId}/file`,
    {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    }
  );

  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: "File not found or access denied." },
      { status: laravelRes.status }
    );
  }

  const contentType = laravelRes.headers.get("Content-Type") ?? "application/octet-stream";
  const buffer = await laravelRes.arrayBuffer();

  return new NextResponse(buffer, {
    status: 200,
    headers: {
      "Content-Type": contentType,
      "Cache-Control": "private, max-age=3600",
    },
  });
}
