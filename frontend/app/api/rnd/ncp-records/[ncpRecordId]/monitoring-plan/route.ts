import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
type Ctx = { params: Promise<{ ncpRecordId: string }> };

export async function GET(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${API}/api/rnd/ncp-records/${ncpRecordId}/monitoring-plan`, {
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}
