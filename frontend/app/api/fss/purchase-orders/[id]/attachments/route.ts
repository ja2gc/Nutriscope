import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

// Multipart upload — forward the FormData (file) straight through; do NOT set
// Content-Type so fetch generates the multipart boundary.
export async function POST(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const token = (await cookies()).get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const { id } = await params;
  const form = await req.formData();

  const res = await fetch(`${LARAVEL_API}/fss/purchase-orders/${id}/attachments`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    body: form,
  });

  const data = await res.json().catch(() => ({}));
  return NextResponse.json(data, { status: res.status });
}
