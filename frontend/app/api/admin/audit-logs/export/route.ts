import { cookies } from "next/headers";
import { NextRequest, NextResponse } from "next/server";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";
const PRIVATE_NO_STORE = {
  "Cache-Control": "private, no-store, max-age=0",
  Pragma: "no-cache",
};

function unavailable(status = 502) {
  return NextResponse.json(
    { message: "Audit export unavailable." },
    { status, headers: PRIVATE_NO_STORE },
  );
}

export async function GET(req: NextRequest) {
  const token = (await cookies()).get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401, headers: PRIVATE_NO_STORE });

  const url = new URL(`${LARAVEL_API}/admin/audit-logs/export`);
  req.nextUrl.searchParams.forEach((value, key) => url.searchParams.append(key, value));

  let response: Response;
  try {
    response = await fetch(url.toString(), {
      method: "GET",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "text/csv",
      },
      cache: "no-store",
    });
  } catch {
    return unavailable();
  }

  const contentType = response.headers.get("Content-Type");
  const contentTypeEssence = contentType?.split(";", 1)[0].trim().toLowerCase();
  if (!response.ok || contentTypeEssence !== "text/csv") {
    return unavailable(response.ok ? 502 : response.status);
  }

  const headers = new Headers({
    ...PRIVATE_NO_STORE,
    "X-Content-Type-Options": "nosniff",
  });
  headers.set("Content-Type", contentType ?? "text/csv");
  headers.set("Content-Disposition", 'attachment; filename="nutriscope-audit-events.csv"');

  return new NextResponse(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
}
