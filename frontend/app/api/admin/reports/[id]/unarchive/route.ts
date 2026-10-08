import { proxy } from "@/lib/laravelProxy";

export async function POST(_request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/admin/reports/${id}/unarchive`, { method: "POST" });
}
