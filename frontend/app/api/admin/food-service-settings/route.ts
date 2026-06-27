import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/admin/food-service-settings");
}

export async function PUT(req: NextRequest) {
  return proxy("/admin/food-service-settings", { method: "PUT", body: await req.json() });
}
