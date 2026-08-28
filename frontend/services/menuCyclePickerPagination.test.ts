import { beforeEach, describe, expect, test, vi } from "vitest";

import { apiFetch } from "@/lib/apiFetch";
import { listFsItemOptions, listRecipeOptions } from "./menuCycleService";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));

describe("menu-cycle picker search", () => {
  beforeEach(() => {
    vi.resetAllMocks();
    vi.mocked(apiFetch).mockResolvedValue(new Response(JSON.stringify({ data: [] })));
  });

  test("requests only five matching recipes", async () => {
    await listRecipeOptions("chicken");
    expect(apiFetch).toHaveBeenCalledWith("/api/fss/food-service-recipes?search=chicken&per_page=5");
  });

  test("requests only five matching single items", async () => {
    await listFsItemOptions("banana");
    expect(apiFetch).toHaveBeenCalledWith("/api/fss/fs-items?search=banana&per_page=5");
  });
});
