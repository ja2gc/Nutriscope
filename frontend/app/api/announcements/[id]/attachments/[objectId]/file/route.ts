import { privateBinaryProxy } from "@/lib/privateBinaryProxy";

export async function GET(
  _request: Request,
  { params }: { params: Promise<{ id: string; objectId: string }> },
) {
  const { id, objectId } = await params;
  return privateBinaryProxy(`/announcements/${encodeURIComponent(id)}/attachments/${encodeURIComponent(objectId)}/file`);
}
