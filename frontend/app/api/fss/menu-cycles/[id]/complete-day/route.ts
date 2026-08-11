import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function POST(request: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/fss/menu-cycles/${id}/complete-day`, { method: "POST", body: await request.json() });
}
