import { describe, expect, test } from "vitest";

import { menuSnapshotToProfile } from "./menuCycleService";

describe("menu cycle PO snapshot profiles", () => {
  test("keeps the population-scaled quantities frozen at PO conversion", () => {
    const profile = menuSnapshotToProfile({
      recipe_id: 12,
      name: "Chicken soup",
      servings: 10,
      population: 120,
      total_cost: 960,
      cost_per_head: 8,
      ingredient_usage: [
        { fs_item_id: 9, name: "Chicken", unit: "kg", quantity: 18, cost: 720 },
      ],
    });

    expect(profile.population).toBe(120);
    expect(profile.ingredient_usage[0].quantity).toBe(18);
    expect(profile.total_cost).toBe(960);
  });

  test("builds a visible profile for a frozen single food item", () => {
    const profile = menuSnapshotToProfile({
      fs_item_id: 7,
      name: "Banana",
      population: 80,
      total_quantity: 80,
      unit: "piece",
      total_cost: 400,
    });

    expect(profile.ingredient_usage).toEqual([
      { fs_item_id: 7, name: "Banana", unit: "piece", quantity: 80, cost: 400 },
    ]);
    expect(profile.cost_per_head).toBe(5);
  });
});
