// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { fetchAiUsageAnalytics } from "@/services/aiUsageAnalyticsService";
import { AiUsageExplorer, AiUsageTooltip } from "./AiUsageExplorer";

vi.mock("@/services/aiUsageAnalyticsService", () => ({ fetchAiUsageAnalytics: vi.fn() }));
const fetchMock = vi.mocked(fetchAiUsageAnalytics);

const pricing = {
  inputCostPer1mTokensUsd: 1,
  outputCostPer1mTokensUsd: 5,
  phpRate: 56,
};

describe("AiUsageExplorer", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
    fetchMock.mockResolvedValue({
      view: "month",
      year: 2026,
      month: 7,
      timezone: "Asia/Manila",
      total_tokens: 1718,
      total_tokens_input: 1420,
      total_tokens_output: 298,
      points: Array.from({ length: 31 }, (_, index) => ({
        day: index + 1,
        tokens_input: index === 17 ? 1420 : 0,
        tokens_output: index === 17 ? 298 : 0,
        tokens: index === 17 ? 1718 : 0,
      })),
    });
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
    vi.clearAllMocks();
  });

  test("renders every calendar day and keeps the period summary token-only", async () => {
    await act(async () => {
      root.render(<AiUsageExplorer {...pricing} />);
      await Promise.resolve();
    });

    expect(container.querySelector('[data-testid="ai-usage-chart"]')).not.toBeNull();
    expect(container.querySelectorAll("[data-day-label]")).toHaveLength(31);
    expect(container.querySelector('[data-period-total]')?.textContent).toBe("1,718 tokens");
    expect(container.textContent).not.toContain("Zero usage");
    expect(container.textContent).not.toContain("Not occurred yet");
    expect(container.textContent).not.toContain("Token totals are exact");
    expect(container.textContent).not.toContain("Estimated cost");
  });

  test("tooltip shows the token split and a clearly labeled estimated cost", async () => {
    await act(async () => {
      root.render(
        <AiUsageTooltip
          active
          label="18"
          month={7}
          point={{ day: 18, tokens: 1718, tokens_input: 1420, tokens_output: 298 }}
          {...pricing}
        />,
      );
    });

    expect(container.textContent).toContain("July 18");
    expect(container.textContent).toContain("1,718");
    expect(container.textContent).toContain("1,420 / 298");
    expect(container.textContent).toContain("Estimated cost");
    expect(container.textContent).toContain("₱0.16");
  });
});
