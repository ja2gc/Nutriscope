import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ ncpRecordId: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { ncpRecordId } = await params;

  const laravelRes = await fetch(
    `${LARAVEL_API}/rnd/ncp-records/${ncpRecordId}`,
    {
      method: "DELETE",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    }
  );

  if (laravelRes.status === 204) {
    return new NextResponse(null, { status: 204 });
  }

  const data = await laravelRes.json().catch(() => ({}));
  return NextResponse.json(
    { message: (data as { message?: string }).message ?? "Failed to delete NCP record." },
    { status: laravelRes.status }
  );
}
