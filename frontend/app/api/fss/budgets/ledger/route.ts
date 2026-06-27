import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  return proxy("/fss/budgets/ledger", { search: new URL(req.url).searchParams });
}
