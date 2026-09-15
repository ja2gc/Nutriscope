import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest, { params }: { params: Promise<{ patientId: string }> }) {
  const { patientId } = await params;
  return proxy(`/rnd/reports/patients/${patientId}/instances`, { search: req.nextUrl.searchParams });
}
