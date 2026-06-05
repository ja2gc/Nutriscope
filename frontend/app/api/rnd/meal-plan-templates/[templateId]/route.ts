import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
type Ctx = { params: Promise<{ templateId: string }> };

async function proxy(req: NextRequest, path: string) {
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${API}/api/rnd/${path}`, {
    method: req.method,
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  if (res.status === 204) return new NextResponse(null, { status: 204 });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}

export async function GET(req: NextRequest, { params }: Ctx) {
  const { templateId } = await params;
  return proxy(req, `meal-plan-templates/${templateId}`);
}

export async function DELETE(req: NextRequest, { params }: Ctx) {
  const { templateId } = await params;
  return proxy(req, `meal-plan-templates/${templateId}`);
}
