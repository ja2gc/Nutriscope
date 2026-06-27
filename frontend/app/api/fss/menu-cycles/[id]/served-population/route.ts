import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

// Backfill served population for one date of a cycle. Editable by FSS + RND before
// the related food PO completes. Drives PO completion + PPA actual output.
export async function PATCH(req: NextRequest, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const body = await req.json();
  return proxy(`/fss/menu-cycles/${id}/served-population`, { method: "PATCH", body });
}
