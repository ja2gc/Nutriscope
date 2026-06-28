export const DEFAULT_AI_COST_PER_1M_TOKENS_USD = 1.92;

export function calcTokenCostUsd(totalTokens: number, costPer1mTokensUsd: number): number {
  return (totalTokens / 1_000_000) * costPer1mTokensUsd;
}

