import { privateBinaryProxy } from "@/lib/privateBinaryProxy";

export async function GET(
  _request: Request,
  { params }: { params: Promise<{ id: string }> },
) {
  const { id } = await params;
  return privateBinaryProxy(`/announcements/${id}/author-photo`);
}
