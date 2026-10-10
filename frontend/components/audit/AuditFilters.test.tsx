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

  test("uses the shared month and year filter instead of a date range", () => {
    act(() => root.render(<AuditFilters metadata={metadata} value={{}} onChange={() => undefined} onClear={() => undefined} />));

    expect(container.querySelector('select[aria-label="Audit month"]')).not.toBeNull();
    expect(container.querySelector('select[aria-label="Audit year"]')).not.toBeNull();
    expect(container.textContent).not.toContain("Start date");
    expect(container.textContent).not.toContain("End date");
  });
});
