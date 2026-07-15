import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  return proxy(`/rnd/patients/${id}`);
}

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  return proxy(`/rnd/patients/${id}`, { method: "DELETE" });
}

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const body = await req.json();
  return proxy(`/rnd/patients/${id}`, { method: "PATCH", body });
}
