import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

/** Streams an archived copy's frozen PDF INLINE (for the in-app preview pane). */
export async function GET(_req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const token = (await cookies()).get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const res = await fetch(`${LARAVEL_API}/admin/reports/${id}/view`, {
    headers: { Authorization: `Bearer ${token}` },
  });

  if (!res.ok) {
    return NextResponse.json({ message: "Report file not available." }, { status: res.status });
  }

  return new NextResponse(await res.arrayBuffer(), {
    status: 200,
    headers: {
      "Content-Type": res.headers.get("Content-Type") ?? "application/pdf",
      "Content-Disposition": res.headers.get("Content-Disposition") ?? "inline",
    },
  });
}
