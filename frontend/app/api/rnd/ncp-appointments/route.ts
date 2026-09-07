import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  return proxy("/rnd/ncp-appointments", { search: req.nextUrl.searchParams });
}
