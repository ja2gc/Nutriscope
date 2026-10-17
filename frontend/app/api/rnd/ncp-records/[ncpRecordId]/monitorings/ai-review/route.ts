import { NextRequest } from "next/server";

import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string }> };

export async function GET(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  return proxy(`/rnd/ncp-records/${ncpRecordId}/monitorings/ai-review`);
}

export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;

  return proxy(`/rnd/ncp-records/${ncpRecordId}/monitorings/ai-review`, {
    method: "POST",
    body: await req.json(),
  });
}
