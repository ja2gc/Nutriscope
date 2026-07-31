import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function POST(
  _request: NextRequest,
  { params }: { params: Promise<{ id: string }> },
) {
  const { id } = await params;
  return proxy(`/admin/backups/${encodeURIComponent(id)}/keep`, { method: "POST" });
}
