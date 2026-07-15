import { NextRequest, NextResponse } from "next/server";
import { proxy } from "@/lib/laravelProxy";

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const PRIVATE_NO_STORE = { "Cache-Control": "private, no-store, max-age=0", Pragma: "no-cache" };

export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ id: string }> },
) {
  const { id } = await params;
  if (!UUID_PATTERN.test(id)) {
    return NextResponse.json({ message: "Audit event not found." }, { status: 404, headers: PRIVATE_NO_STORE });
  }

  const response = await proxy(`/admin/audit-logs/${encodeURIComponent(id)}/history`);
  Object.entries(PRIVATE_NO_STORE).forEach(([key, value]) => response.headers.set(key, value));
  return response;
}
