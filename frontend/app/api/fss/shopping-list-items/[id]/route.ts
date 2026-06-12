import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function PATCH(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/fss/shopping-list-items/${id}`, { method: "PATCH", body: await req.json() });
}

export async function DELETE(_req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/fss/shopping-list-items/${id}`, { method: "DELETE" });
}
