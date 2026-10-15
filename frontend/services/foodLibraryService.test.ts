import { afterEach, expect, test, vi } from "vitest";
import { updateFoodItem } from "./foodLibraryService";
import { RecipeCorrectionsRequiredError } from "./fsCatalogService";

afterEach(() => vi.unstubAllGlobals());

test("Ingredient updates preserve the affected Menu Item correction response", async () => {
  const affected = [{ recipe_id: "recipe-public-id", recipe_name: "Measured Meal", line_index: 0, quantity: "500", unit: "g" }];
  const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: "Correct affected Menu Items.", affected_recipes: affected }), { status: 422 }));
  vi.stubGlobal("fetch", request);
  try {
    await updateFoodItem("ingredient-public-id", { serving_unit: "piece", serving_size: 1 });
    expect.fail("Expected measured corrections to be required.");
  } catch (error) {
    expect(error).toBeInstanceOf(RecipeCorrectionsRequiredError);
    expect((error as RecipeCorrectionsRequiredError).affectedRecipes).toEqual(affected);
  }
  expect(request).toHaveBeenCalledWith("/api/rnd/food-items/ingredient-public-id", expect.objectContaining({ method: "PUT" }));
});
