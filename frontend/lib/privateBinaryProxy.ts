import { cookies } from "next/headers";
import { NextResponse } from "next/server";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

export async function privateBinaryProxy(path: string): Promise<NextResponse> {
  const cookieStore = await cookies();
  const token = cookieStore.get("nutriscope_token")?.value;

  if (!token) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  try {
    const upstream = await fetch(`${LARAVEL_API}${path}`, {
      headers: { Authorization: `Bearer ${token}` },
      cache: "no-store",
    });

    if (!upstream.ok) {
      return NextResponse.json(
        { message: "File not found or access denied." },
        { status: upstream.status },
      );
    }

    const headers = new Headers({
      "Content-Type": upstream.headers.get("Content-Type") ?? "application/octet-stream",
      "Cache-Control": "private, no-store",
      "X-Content-Type-Options": "nosniff",
    });
    const contentDisposition = upstream.headers.get("Content-Disposition");
    if (contentDisposition) headers.set("Content-Disposition", contentDisposition);

    return new NextResponse(await upstream.arrayBuffer(), {
      status: 200,
      headers,
    });
  } catch {
    return NextResponse.json({ message: "File service unavailable." }, { status: 502 });
  }
}
