import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

/**
 * On-demand render: stream the live PDF through with the session token (binary
 * passthrough, inline). `id` carries the report type; render params are forwarded
 * verbatim from the query string. Opened directly in a new tab from the browser.
 */
export async function GET(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const token = (await cookies()).get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const res = await fetch(`${LARAVEL_API}/admin/reports/${id}/render${req.nextUrl.search}`, {
    headers: { Authorization: `Bearer ${token}` },
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({ message: "Unable to render report." }));
    return NextResponse.json(data, { status: res.status });
  }

  return new NextResponse(await res.arrayBuffer(), {
    status: 200,
    headers: {
      "Content-Type": res.headers.get("Content-Type") ?? "application/pdf",
      "Content-Disposition": res.headers.get("Content-Disposition") ?? `inline; filename="${id}.pdf"`,
    },
  });
}
