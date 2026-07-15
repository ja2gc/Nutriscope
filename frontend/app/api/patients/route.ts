import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  return proxy("/rnd/patients", { search: req.nextUrl.searchParams });
}

export async function POST(req: NextRequest) {
  const body = await req.json();
  return proxy("/rnd/patients", { method: "POST", body });
}
