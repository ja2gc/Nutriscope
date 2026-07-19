import { proxy } from "@/lib/laravelProxy";

export async function PATCH(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/notifications/${id}/open`, { method: "PATCH" });
}
