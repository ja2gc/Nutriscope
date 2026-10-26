import { beforeEach, describe, expect, test, vi } from "vitest";

import { apiFetch } from "@/lib/apiFetch";
import { listRecipeOptions } from "./menuCycleService";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));

describe("menu-cycle picker search", () => {
  beforeEach(() => {
    vi.resetAllMocks();
    vi.mocked(apiFetch).mockResolvedValue(new Response(JSON.stringify({ data: [] })));
  });

  test("requests the requested recipe page and returns pagination metadata", async () => {
    const meta = { current_page: 3, per_page: 5, total: 16, last_page: 4 };
    vi.mocked(apiFetch).mockResolvedValueOnce(new Response(JSON.stringify({ data: [{ id: "recipe-1", name: "Chicken" }], meta })));
    const result = await listRecipeOptions("chicken", 3, 5);
    expect(apiFetch).toHaveBeenCalledWith("/api/fss/food-service-recipes?page=3&per_page=5&search=chicken");
    expect(result.meta).toEqual(meta);
    expect(result.data).toHaveLength(1);
  });

  test("throws when recipe options cannot be loaded", async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce(new Response("{}", { status: 500 }));
    await expect(listRecipeOptions("", 1, 5)).rejects.toThrow();
  });
});
