import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(_req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/fss/menu-cycles/${id}`);
}

export async function PATCH(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/fss/menu-cycles/${id}`, { method: "PATCH", body: await req.json() });
}

export async function DELETE(_req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/fss/menu-cycles/${id}`, { method: "DELETE" });
}
