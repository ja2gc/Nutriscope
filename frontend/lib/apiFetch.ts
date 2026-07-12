export interface ApiFetchOptions {
  redirectOnUnauthorized?: boolean;
}

export async function apiFetch(
  input: RequestInfo | URL,
  init?: RequestInit,
  options: ApiFetchOptions = {},
): Promise<Response> {
  const response = await fetch(input, init);
  if (response.status === 401 && options.redirectOnUnauthorized !== false && typeof window !== "undefined") {
    window.location.replace("/login");
  }
  return response;
}
