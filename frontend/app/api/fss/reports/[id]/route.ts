import { proxy } from "@/lib/laravelProxy";

export async function DELETE(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/fss/reports/${id}`, { method: "DELETE" });
}
