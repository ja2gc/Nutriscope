import { beforeEach, describe, expect, test, vi } from "vitest";
import { apiFetch } from "@/lib/apiFetch";
import {
  getMenuLineRecipe,
  restoreMenuLineRecipe,
  scaledIngredientQuantity,
  updateMenuLineRecipe,
} from "./menuCycleService";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));
const apiFetchMock = vi.mocked(apiFetch);

describe("menu slot recipe service", () => {
  beforeEach(() => vi.clearAllMocks());

  test("scales baseline quantities using the reference and planned servings", () => {
    expect(scaledIngredientQuantity(3, 25, 100)).toBe(12);
    expect(scaledIngredientQuantity(0.333, 3, 7)).toBeCloseTo(0.777);
  });

  test("uses the public line endpoint for load, save, and restore", async () => {
    apiFetchMock.mockResolvedValue(new Response(JSON.stringify({ data: { name: "Adobo" } }), { status: 200 }));
    const payload = {
      name: "Ward Adobo",
      reference_servings: 25,
      prep_notes: null,
      ingredients: [{ fs_item_id: "item-1", quantity: 3, unit: "kg" }],
    };

    await getMenuLineRecipe("cycle-1", "line-1");
    await updateMenuLineRecipe("cycle-1", "line-1", payload);
    await restoreMenuLineRecipe("cycle-1", "line-1");

    const path = "/api/fss/menu-cycles/cycle-1/lines/line-1";
    expect(apiFetchMock).toHaveBeenNthCalledWith(1, path);
    expect(apiFetchMock).toHaveBeenNthCalledWith(2, path, expect.objectContaining({ method: "PATCH" }));
    expect(apiFetchMock).toHaveBeenNthCalledWith(3, path, { method: "DELETE" });
  });
});
