import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function PATCH(req: NextRequest, { params }: { params: Promise<{ appointmentId: string }> }) {
  const { appointmentId } = await params;
  return proxy(`/rnd/ncp-appointments/${appointmentId}`, { method: "PATCH", body: await req.json() });
}
