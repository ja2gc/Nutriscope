import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  const search = new URL(req.url).searchParams;
  return proxy("/admin/announcements", { search });
}

export async function POST(req: NextRequest) {
  const body = await req.json();
  return proxy("/admin/announcements", { method: "POST", body });
}
