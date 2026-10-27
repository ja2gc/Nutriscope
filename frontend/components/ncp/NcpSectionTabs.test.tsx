import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { expect, it } from "vitest";
import { NcpSectionTabs } from "./NcpSectionTabs";

it("keeps NCP tabs in one horizontal, non-wrapping row with selected tab semantics", () => {
  const html = renderToStaticMarkup(
    <NcpSectionTabs
      ariaLabel="Monitoring views"
      idPrefix="monitoring"
      items={[{ key: "log", label: "Visit Log" }, { key: "progress", label: "Progress Trends" }]}
      value="log"
      onChange={() => undefined}
    />,
  );

  expect(html).toContain('role="tablist" aria-label="Monitoring views"');
  expect(html).toContain('id="monitoring-tab-log"');
  expect(html).toContain('aria-controls="monitoring-panel-log"');
  expect(html).toContain('aria-selected="true"');
  expect(html).toContain("flex-nowrap");
  expect(html).toContain("overflow-x-auto");
  expect(html).toContain("overflow-y-hidden");
  expect(html).toContain("shrink-0");
});
