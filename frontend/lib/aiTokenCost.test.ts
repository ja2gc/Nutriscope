import { describe, expect, it } from "vitest";
import { DEFAULT_AI_COST_PER_1M_TOKENS_USD, calcTokenCostUsd } from "./aiTokenCost";

describe("aiTokenCost", () => {
  it("defaults to the current blended Haiku estimate", () => {
    expect(DEFAULT_AI_COST_PER_1M_TOKENS_USD).toBe(1.92);
  });

  it("calculates USD cost from total tokens and editable per-1M token rate", () => {
    expect(calcTokenCostUsd(500_000, 2.5)).toBe(1.25);
  });
});

