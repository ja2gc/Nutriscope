import { proxy } from "@/lib/laravelProxy";

export async function GET(req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/fss/budgets/${id}/activity`, { search: new URL(req.url).searchParams });
}
