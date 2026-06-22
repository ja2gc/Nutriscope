import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ screeningDocumentId: string }> }
) {
  const { screeningDocumentId } = await params;
  return proxy(`/rnd/screening-documents/${screeningDocumentId}`, { method: "DELETE" });
}
