import { privateBinaryProxy } from "@/lib/privateBinaryProxy";

export async function GET(_request: Request, { params }: { params: Promise<{ side: string }> }) {
  const { side } = await params;
  if (side !== "left" && side !== "right") return new Response(null, { status: 404 });

  return privateBinaryProxy(`/rnd/report-branding/logo/${side}`);
}
