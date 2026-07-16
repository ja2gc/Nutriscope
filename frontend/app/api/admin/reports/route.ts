import { proxy } from "@/lib/laravelProxy";
import { NextRequest } from "next/server";

export async function GET(req: NextRequest) {
  return proxy("/admin/reports", { search: new URL(req.url).searchParams });
}
