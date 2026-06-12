import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  return proxy("/fss/menu-cycles", { search: new URL(req.url).searchParams });
}

export async function POST(req: NextRequest) {
  return proxy("/fss/menu-cycles", { method: "POST", body: await req.json() });
}
