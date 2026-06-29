import { NextRequest } from "next/server";

import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string }> };

export async function GET(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;

  return proxy(`/rnd/ncp-records/${ncpRecordId}/monitorings`);
}

export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  const body = await req.json().catch(() => ({}));

  return proxy(`/rnd/ncp-records/${ncpRecordId}/monitorings`, {
    method: "POST",
    body,
  });
}
