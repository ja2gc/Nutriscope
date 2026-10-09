// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import { AuditFilters } from "./AuditFilters";
import { auditSearchParams } from "./useAuditUrlState";
import type { AuditFilterMetadata } from "@/types/audit";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

const metadata: AuditFilterMetadata = {
  modules: [], actions: [], outcomes: [], severities: [],
  module_subfilters: { security_administration: [], nutrition_care: [], food_service_operations: [], reports: [] },
  module_actions: { security_administration: [], nutrition_care: [], food_service_operations: [], reports: [] },
  module_counts: { all: 0, security_administration: 0, nutrition_care: 0, food_service_operations: 0, reports: 0 },
};

afterEach(() => { document.body.innerHTML = ""; });

describe("audit month filter", () => {
  it("uses the chosen month and year, with no day or range controls", async () => {
    const onChange = vi.fn();
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);
    await act(async () => root.render(<AuditFilters metadata={metadata} value={{ month: "2026-06" }} onChange={onChange} onClear={vi.fn()} />));

    expect(container.querySelector<HTMLSelectElement>('[aria-label="Audit month"]')?.value).toBe("6");
    expect(container.querySelector<HTMLSelectElement>('[aria-label="Audit year"]')?.value).toBe("2026");
    expect(container.querySelector('[aria-label="Start date"]')).toBeNull();
    expect(container.querySelector('[aria-label="End date"]')).toBeNull();

    const month = container.querySelector<HTMLSelectElement>('[aria-label="Audit month"]')!;
    await act(async () => { month.value = "7"; month.dispatchEvent(new Event("change", { bubbles: true })); });
    expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ month: "2026-07" }));
    await act(async () => root.unmount());
  });

  it("keeps one month in the URL instead of a start/end range", () => {
    const query = auditSearchParams({ month: "2026-06", module: "reports" }).toString();
    expect(query).toContain("month=2026-06");
    expect(query).not.toContain("start=");
    expect(query).not.toContain("end=");
  });
});
