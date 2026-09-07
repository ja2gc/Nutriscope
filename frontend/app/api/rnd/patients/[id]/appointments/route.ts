import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/rnd/patients/${id}/appointments`, { search: req.nextUrl.searchParams });
}

export async function POST(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return proxy(`/rnd/patients/${id}/appointments`, { method: "POST", body: await req.json() });
}
