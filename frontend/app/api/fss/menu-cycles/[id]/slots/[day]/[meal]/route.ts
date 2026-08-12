import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

type RouteParams = { params: Promise<{ id: string; day: string; meal: string }> };

async function path(context: RouteParams): Promise<string> {
  const { id, day, meal } = await context.params;
  return `/fss/menu-cycles/${id}/slots/${day}/${meal}`;
}

export async function GET(_request: NextRequest, context: RouteParams) {
  return proxy(await path(context));
}

export async function PATCH(request: NextRequest, context: RouteParams) {
  return proxy(await path(context), { method: "PATCH", body: await request.json() });
}

export async function DELETE(_request: NextRequest, context: RouteParams) {
  return proxy(await path(context), { method: "DELETE" });
}
