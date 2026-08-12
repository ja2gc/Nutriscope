import { beforeEach, describe, expect, test, vi } from "vitest";
import { apiFetch } from "@/lib/apiFetch";
import {
  getMenuSlotRecipe,
  restoreMenuSlotRecipe,
  scaledIngredientQuantity,
  updateMenuSlotRecipe,
} from "./menuCycleService";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));
const apiFetchMock = vi.mocked(apiFetch);

describe("menu slot recipe service", () => {
  beforeEach(() => vi.clearAllMocks());

  test("scales baseline quantities using the reference and planned servings", () => {
    expect(scaledIngredientQuantity(3, 25, 100)).toBe(12);
    expect(scaledIngredientQuantity(0.333, 3, 7)).toBeCloseTo(0.777);
  });

  test("uses the dedicated slot endpoint for load, save, and restore", async () => {
    apiFetchMock.mockResolvedValue(new Response(JSON.stringify({ data: { name: "Adobo" } }), { status: 200 }));
    const payload = {
      name: "Ward Adobo",
      reference_servings: 25,
      planned_servings: 100,
      prep_notes: null,
      ingredients: [{ fs_item_id: "item-1", quantity: 3, unit: "kg" }],
    };

    await getMenuSlotRecipe("cycle-1", "Monday", "lunch");
    await updateMenuSlotRecipe("cycle-1", "Monday", "lunch", payload);
    await restoreMenuSlotRecipe("cycle-1", "Monday", "lunch");

    const path = "/api/fss/menu-cycles/cycle-1/slots/Monday/lunch";
    expect(apiFetchMock).toHaveBeenNthCalledWith(1, path);
    expect(apiFetchMock).toHaveBeenNthCalledWith(2, path, expect.objectContaining({ method: "PATCH" }));
    expect(apiFetchMock).toHaveBeenNthCalledWith(3, path, { method: "DELETE" });
  });
});
