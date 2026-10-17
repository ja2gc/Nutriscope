import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { expect, it } from "vitest";
import ButtonFilterGroup from "./ButtonFilterGroup";

it("exposes the selected filter without depending on color", () => {
  const html = renderToStaticMarkup(<ButtonFilterGroup value="all" onChange={() => undefined} options={[{ value: "all", label: "All" }, { value: "lab", label: "Clinical Labs" }]} />);
  expect(html).toContain('aria-pressed="true"');
  expect(html).toContain('aria-pressed="false"');
});
