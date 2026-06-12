import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function DELETE(_req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/rnd/reports/${id}`, { method: "DELETE" });
}
