import { proxy } from "@/lib/laravelProxy";

export async function GET() {
  return proxy("/sop");
}

export async function POST(req: Request) {
  const body = await req.json();
  return proxy("/sop", { method: "POST", body });
}
