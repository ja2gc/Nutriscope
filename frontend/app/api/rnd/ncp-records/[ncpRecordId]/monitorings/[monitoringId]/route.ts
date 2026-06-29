import { NextRequest } from "next/server";

import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ ncpRecordId: string; monitoringId: string }> };

export async function PATCH(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, monitoringId } = await params;
  const body = await req.json().catch(() => ({}));

  return proxy(`/rnd/ncp-records/${ncpRecordId}/monitorings/${monitoringId}`, {
    method: "PATCH",
    body,
  });
}

export async function DELETE(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, monitoringId } = await params;

  return proxy(`/rnd/ncp-records/${ncpRecordId}/monitorings/${monitoringId}`, {
    method: "DELETE",
  });
}
