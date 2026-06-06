import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
type Ctx = { params: Promise<{ ncpRecordId: string; monitoringId: string }> };

async function proxy(req: NextRequest, path: string) {
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${API}/api/rnd/${path}`, {
    method: req.method,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: req.method !== 'GET' ? await req.text() : undefined,
  });
  if (res.status === 204) return new NextResponse(null, { status: 204 });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}

export async function PATCH(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, monitoringId } = await params;
  return proxy(req, `ncp-records/${ncpRecordId}/monitorings/${monitoringId}`);
}

export async function DELETE(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, monitoringId } = await params;
  return proxy(req, `ncp-records/${ncpRecordId}/monitorings/${monitoringId}`);
}
