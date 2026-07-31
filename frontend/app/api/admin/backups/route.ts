import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/admin/backups");
}

export async function POST() {
  return proxy("/admin/backups", { method: "POST" });
}
