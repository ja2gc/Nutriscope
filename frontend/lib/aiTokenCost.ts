export const DEFAULT_AI_INPUT_COST_PER_1M_TOKENS_USD = 1;
export const DEFAULT_AI_OUTPUT_COST_PER_1M_TOKENS_USD = 5;

export function calcTokenCostUsd(
  inputTokens: number,
  outputTokens: number,
  inputCostPer1mTokensUsd: number,
  outputCostPer1mTokensUsd: number,
): number {
  return (inputTokens / 1_000_000) * inputCostPer1mTokensUsd
    + (outputTokens / 1_000_000) * outputCostPer1mTokensUsd;
}
