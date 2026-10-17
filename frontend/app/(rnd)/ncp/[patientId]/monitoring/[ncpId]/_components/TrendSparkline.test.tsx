import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { expect, it } from "vitest";
import TrendSparkline from "./TrendSparkline";

it("uses a neutral measurement color and includes an outlying target", () => {
  const html = renderToStaticMarkup(<TrendSparkline values={[90, 85]} target={60} />);
  expect(html).toContain("stroke-dasharray");
  expect(html).toContain("Target: 60");
  expect(html).not.toContain("#f43f5e");
  expect(html).toContain("#2563eb");
});
