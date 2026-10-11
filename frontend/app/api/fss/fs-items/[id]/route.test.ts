import { NextRequest } from "next/server";
import { afterEach, describe, expect, test, vi } from "vitest";
import { PATCH } from "./route";

vi.mock("next/headers", () => ({
  cookies: vi.fn(async () => ({ get: () => ({ value: "fictional-session-token" }) })),
}));

describe("Inventory item update proxy", () => {
  afterEach(() => vi.unstubAllGlobals());

  test("returns affected Food and menu rows from a correction response", async () => {
    const affectedRecipes = [
      { recipe_id: "recipe-uuid", recipe_name: "Soup", line_index: 0, quantity: 2, unit: "tbsp" },
      { slot_id: "slot-uuid", recipe_name: "Soup — Monday lunch", line_index: 0, quantity: 2, unit: "tbsp" },
    ];
    vi.stubGlobal("fetch", vi.fn(async () => new Response(JSON.stringify({
      message: "Update measurements before saving.",
      affected_recipes: affectedRecipes,
    }), { status: 422, headers: { "Content-Type": "application/json" } })));

    const response = await PATCH(
      new NextRequest("http://localhost/api/fss/fs-items/item-uuid", {
        method: "PATCH",
        body: JSON.stringify({ base_unit: "kg" }),
      }),
      { params: Promise.resolve({ id: "item-uuid" }) },
    );

    expect(response.status).toBe(422);
    expect(await response.json()).toEqual({
      message: "Update measurements before saving.",
      affected_recipes: affectedRecipes,
    });
  });
});
