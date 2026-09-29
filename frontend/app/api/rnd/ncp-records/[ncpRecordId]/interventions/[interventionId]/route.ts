import { type NextRequest } from "next/server";

import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string; interventionId: string }> };

export async function GET(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, interventionId } = await params;

  return proxy(`/rnd/ncp-records/${ncpRecordId}/interventions/${interventionId}`);
}
