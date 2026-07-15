// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { useAuditUrlState } from "./useAuditUrlState";

const navigation = vi.hoisted(() => ({
  query: "module=security_administration&subfilter=accounts&page=2",
  replace: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  usePathname: () => "/admin/audit-logs",
  useRouter: () => ({ replace: navigation.replace }),
  useSearchParams: () => new URLSearchParams(navigation.query),
}));

function Harness() {
  const state = useAuditUrlState();
  return (
    <button type="button" onClick={() => state.updateFilters({ module: "reports", subfilter: "menu_calendar" })}>
      {state.filters.module}:{state.filters.subfilter}:{state.page}
    </button>
  );
}

describe("useAuditUrlState", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    navigation.query = "module=security_administration&subfilter=accounts&page=2";
    navigation.replace.mockReset();
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
    act(() => root.render(<Harness />));
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  test("same-path back-forward query changes replace state without a write loop", () => {
    expect(container.textContent).toBe("security_administration:accounts:2");

    navigation.query = "module=nutrition_care&subfilter=patients_ncp&page=3";
    act(() => root.render(<Harness />));
    expect(container.textContent).toBe("nutrition_care:patients_ncp:3");
    expect(navigation.replace).not.toHaveBeenCalled();

    act(() => container.querySelector("button")!.click());
    expect(navigation.replace).toHaveBeenCalledTimes(1);
    expect(navigation.replace).toHaveBeenCalledWith("/admin/audit-logs?module=reports&subfilter=menu_calendar", { scroll: false });
  });
});
