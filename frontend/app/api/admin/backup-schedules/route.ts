import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/admin/backup-schedules");
}

export async function PUT(request: NextRequest) {
  return proxy("/admin/backup-schedules", {
    method: "PUT",
    body: await request.json(),
  });
}
