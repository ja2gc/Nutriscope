import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

/** Browse: enumerate renderable instances of a report type (`id` carries the type). */
export async function GET(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/admin/reports/${id}/instances`, { search: req.nextUrl.searchParams });
}
