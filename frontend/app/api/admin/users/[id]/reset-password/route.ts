import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function POST(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const body = await req.json();
  return proxy(`/admin/users/${id}/reset-password`, { method: "POST", body });
}
