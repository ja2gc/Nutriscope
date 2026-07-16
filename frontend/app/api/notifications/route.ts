import { proxy } from "@/lib/laravelProxy";
import { NextRequest } from "next/server";

export async function GET(req: NextRequest) {
  return proxy("/notifications", { search: new URL(req.url).searchParams });
}
