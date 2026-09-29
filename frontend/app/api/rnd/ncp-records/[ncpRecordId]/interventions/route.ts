import { type NextRequest } from "next/server";

import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string }> };

export async function GET(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;

  return proxy(`/rnd/ncp-records/${ncpRecordId}/interventions`, {
    search: req.nextUrl.searchParams,
  });
}

export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  const body = await req.json().catch(() => ({}));

  return proxy(`/rnd/ncp-records/${ncpRecordId}/interventions`, {
    method: "POST",
    body,
  });
}
