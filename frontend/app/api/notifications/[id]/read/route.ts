import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function PATCH(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  return proxy(`/notifications/${id}/read`, { method: "PATCH" });
}
