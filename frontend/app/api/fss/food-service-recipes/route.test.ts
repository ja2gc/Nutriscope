import { afterEach, expect, it, vi } from "vitest";
import { NextRequest } from "next/server";
import { GET } from "./route";

vi.mock("next/headers", () => ({
  cookies: async () => ({ get: () => ({ value: "session-token" }) }),
}));

afterEach(() => vi.unstubAllGlobals());

it("forwards supported Menu Items filters and pagination to the backend", async () => {
  const fetchMock = vi.fn(async () => Response.json({ data: [], meta: { current_page: 3 } }));
  vi.stubGlobal("fetch", fetchMock);

  const response = await GET(new NextRequest("http://localhost/api/fss/food-service-recipes?search=chicken&category=meal&page=3&per_page=10&ignored=x"));

  expect(response.status).toBe(200);
  expect(fetchMock).toHaveBeenCalledWith(
    expect.stringMatching(/\/fss\/food-service-recipes\?category=meal&search=chicken&page=3&per_page=10$/),
    expect.objectContaining({ headers: expect.objectContaining({ Authorization: "Bearer session-token" }) }),
  );
});
