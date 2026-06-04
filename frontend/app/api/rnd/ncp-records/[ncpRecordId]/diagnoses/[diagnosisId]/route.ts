import { NextRequest, NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function PATCH(
  req: NextRequest,
  { params }: { params: Promise<{ ncpRecordId: string; diagnosisId: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { ncpRecordId, diagnosisId } = await params;
  const body = await req.json().catch(() => ({}));

  const laravelRes = await fetch(
    `${LARAVEL_API}/rnd/ncp-records/${ncpRecordId}/diagnoses/${diagnosisId}`,
    {
      method: "PATCH",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(body),
    }
  );

  const data = await laravelRes.json().catch(() => ({}));

  if (!laravelRes.ok) {
    return NextResponse.json(
      { message: data.message ?? "Failed to update diagnosis." },
      { status: laravelRes.status }
    );
  }

  return NextResponse.json(data, { status: 200 });
}

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ ncpRecordId: string; diagnosisId: string }> }
) {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { ncpRecordId, diagnosisId } = await params;

  const laravelRes = await fetch(
    `${LARAVEL_API}/rnd/ncp-records/${ncpRecordId}/diagnoses/${diagnosisId}`,
    {
      method: "DELETE",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    }
  );

  if (laravelRes.status === 204) {
    return new NextResponse(null, { status: 204 });
  }

  const data = await laravelRes.json().catch(() => ({}));
  return NextResponse.json(
    { message: data.message ?? "Failed to delete diagnosis." },
    { status: laravelRes.status }
  );
}
