import { proxy } from "@/lib/laravelProxy";
import { NextRequest } from "next/server";

export async function GET(req?: NextRequest) {
  if (!req) return proxy("/sop/history");
  return proxy("/sop/history", { search: new URL(req.url).searchParams });
}
