import { proxy } from "@/lib/laravelProxy";

export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/admin/budgets/${id}`);
}
