import { NextResponse } from "next/server";
import { cookies } from "next/headers";

const LARAVEL_API = process.env.LARAVEL_API_URL ?? "http://127.0.0.1:8000/api";

/**
 * Thin authenticated pass-through to the Laravel API. Forwards the session token
 * from the cookie and relays status + JSON. Keeps the per-route handlers tiny.
 */
export async function proxy(
  path: string,
  init?: { method?: string; body?: unknown; search?: URLSearchParams }
): Promise<NextResponse> {
  const token = (await cookies()).get("nutriscope_token")?.value;
  if (!token) return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });

  const url = new URL(`${LARAVEL_API}${path}`);
  init?.search?.forEach((v, k) => url.searchParams.append(k, v));

  const hasBody = init?.body !== undefined;
  const res = await fetch(url.toString(), {
    method: init?.method ?? "GET",
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
      ...(hasBody ? { "Content-Type": "application/json" } : {}),
    },
    body: hasBody ? JSON.stringify(init!.body) : undefined,
  });

  if (res.status === 204) return new NextResponse(null, { status: 204 });
  const data = await res.json().catch(() => ({}));
  return NextResponse.json(data, { status: res.status });
}
