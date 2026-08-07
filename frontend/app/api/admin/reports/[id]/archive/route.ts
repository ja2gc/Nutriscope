import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

/** Hides a saved report from the active list. */
export async function POST(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/admin/reports/${id}/archive`, { method: "POST", search: req.nextUrl.searchParams });
}
