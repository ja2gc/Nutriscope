import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest, { params }: { params: Promise<{ ncpRecordId: string }> }) {
  const { ncpRecordId } = await params;
  const query = req.nextUrl.searchParams.toString();
  return proxy(`/rnd/ncp-records/${ncpRecordId}/activity${query ? `?${query}` : ""}`);
}
