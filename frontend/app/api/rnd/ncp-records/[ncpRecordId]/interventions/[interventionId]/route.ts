import { type NextRequest } from "next/server";

import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string; interventionId: string }> };

export async function GET(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, interventionId } = await params;

  return proxy(`/rnd/ncp-records/${ncpRecordId}/interventions/${interventionId}`);
}

export async function PATCH(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, interventionId } = await params;
  const body = await req.json().catch(() => ({}));
  return proxy(`/rnd/ncp-records/${ncpRecordId}/interventions/${interventionId}`, { method: 'PATCH', body });
}

export async function DELETE(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, interventionId } = await params;
  return proxy(`/rnd/ncp-records/${ncpRecordId}/interventions/${interventionId}`, { method: 'DELETE' });
}
