import { describe, expect, it } from "vitest";
import {
  DEFAULT_AI_INPUT_COST_PER_1M_TOKENS_USD,
  DEFAULT_AI_OUTPUT_COST_PER_1M_TOKENS_USD,
  calcTokenCostUsd,
} from "./aiTokenCost";

describe("aiTokenCost", () => {
  it("defaults to the current Haiku input and output rates", () => {
    expect(DEFAULT_AI_INPUT_COST_PER_1M_TOKENS_USD).toBe(1);
    expect(DEFAULT_AI_OUTPUT_COST_PER_1M_TOKENS_USD).toBe(5);
  });

  it("calculates USD cost from separate input and output rates", () => {
    expect(calcTokenCostUsd(500_000, 100_000, 1, 5)).toBe(1);
  });
});
