import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

/** Archive: freeze an as-filed copy (`id` carries the type; params in the query). */
export async function POST(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/rnd/reports/${id}/archive`, { method: "POST", search: req.nextUrl.searchParams });
}
