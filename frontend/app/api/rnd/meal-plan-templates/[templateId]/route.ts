import { NextRequest } from "next/server";

import { proxy } from "@/lib/laravelProxy";

type Ctx = { params: Promise<{ templateId: string }> };

export async function GET(_req: NextRequest, { params }: Ctx) {
  const { templateId } = await params;

  return proxy(`/rnd/meal-plan-templates/${templateId}`);
}

export async function DELETE(_req: NextRequest, { params }: Ctx) {
  const { templateId } = await params;

  return proxy(`/rnd/meal-plan-templates/${templateId}`, {
    method: "DELETE",
  });
}
