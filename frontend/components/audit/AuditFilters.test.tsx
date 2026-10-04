// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test } from "vitest";
import type { AuditFilterMetadata } from "@/types/audit";
import { AuditFilters } from "./AuditFilters";

const metadata = {
  modules: [],
  actions: [],
  outcomes: [],
  severities: [],
  module_subfilters: {},
  module_actions: {},
  module_counts: {},
} as unknown as AuditFilterMetadata;

describe("AuditFilters", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  test("labels both ends of the shared date range visibly", () => {
    act(() => root.render(<AuditFilters metadata={metadata} value={{}} onChange={() => undefined} onClear={() => undefined} />));

    expect(container.textContent).toContain("Start date");
    expect(container.textContent).toContain("End date");
    expect(container.querySelectorAll('select[aria-label^="Start date "]')).toHaveLength(3);
    expect(container.querySelectorAll('select[aria-label^="End date "]')).toHaveLength(3);
    const dateRange = container.querySelector("fieldset");
    expect(dateRange?.className).toContain("flex-wrap");
    expect(dateRange?.className).toContain("items-start");
    expect(dateRange?.className).toContain("justify-start");
    expect(dateRange?.className).not.toContain("sm:grid-cols-2");
    expect(dateRange?.className).toContain("lg:col-span-3");
    expect(dateRange?.className).toContain("xl:col-span-4");
    const startDateSelects = Array.from(container.querySelectorAll<HTMLSelectElement>('select[aria-label^="Start date "]'));
    expect(startDateSelects.map((select) => select.options[0]?.textContent)).toEqual(["MM", "DD", "YYYY"]);
  });
});
