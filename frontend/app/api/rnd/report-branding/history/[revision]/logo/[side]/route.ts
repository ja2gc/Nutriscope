import { privateBinaryProxy } from "@/lib/privateBinaryProxy";

export async function GET(
  _req: Request,
  { params }: { params: Promise<{ revision: string; side: string }> },
) {
  const { revision, side } = await params;
  return privateBinaryProxy(`/rnd/report-branding/history/${revision}/logo/${side}`);
}
