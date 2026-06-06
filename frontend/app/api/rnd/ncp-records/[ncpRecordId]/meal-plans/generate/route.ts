import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const LARAVEL_API = process.env.LARAVEL_API_URL ?? 'http://127.0.0.1:8000/api';
type Ctx = { params: Promise<{ ncpRecordId: string }> };

export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  if (!token) return NextResponse.json({ message: 'Unauthenticated.' }, { status: 401 });
  const res = await fetch(`${LARAVEL_API}/rnd/ncp-records/${ncpRecordId}/meal-plans/generate`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: await req.text(),
  });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}
