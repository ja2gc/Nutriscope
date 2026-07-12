import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const query = req.nextUrl.searchParams.toString();
  return proxy(`/fss/purchase-orders/${id}/activity${query ? `?${query}` : ""}`);
}
