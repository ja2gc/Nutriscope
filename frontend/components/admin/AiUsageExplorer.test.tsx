// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { fetchAiUsageAnalytics } from "@/services/aiUsageAnalyticsService";
import { AiUsageExplorer } from "./AiUsageExplorer";

vi.mock("@/services/aiUsageAnalyticsService", () => ({ fetchAiUsageAnalytics: vi.fn() }));
const fetchMock = vi.mocked(fetchAiUsageAnalytics);

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
      total_tokens: 100,
      points: [{ day: 1, tokens: 0 }, { day: 2, tokens: null }, { day: 3, tokens: 100 }],
    });
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
    vi.clearAllMocks();
  });

  test("renders zero, future, and used days as distinct bar states", async () => {
    await act(async () => {
      root.render(<AiUsageExplorer />);
      await Promise.resolve();
    });

    expect(container.querySelectorAll('[data-usage-state="zero"]')).toHaveLength(1);
    expect(container.querySelectorAll('[data-usage-state="future"]')).toHaveLength(1);
    expect(container.querySelectorAll('[data-usage-state="used"]')).toHaveLength(1);
    expect(container.textContent).toContain("100 tokens");
    expect(container.querySelector('[aria-label="Previous month"]')).not.toBeNull();
    expect(container.querySelector('[aria-label="Next month"]')).not.toBeNull();
    expect(container.querySelector('[aria-label="Jump to month"]')).not.toBeNull();
    expect(container.querySelector('[aria-label="Jump to year"]')).not.toBeNull();
  });
});
