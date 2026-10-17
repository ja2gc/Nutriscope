import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { expect, it } from "vitest";
import { CharacterCountListInput } from "./CharacterCountListInput";

it("shows per-entry counts, preserves legacy text, and limits new entry slots", () => {
  const html = renderToStaticMarkup(<CharacterCountListInput value={Array(5).fill("x".repeat(51))} onChange={() => undefined} maxLength={50} maxEntries={5} label="Food dislike" />);
  expect(html.match(/51\/50/g)).toHaveLength(5);
  expect(html).toContain("5/5 entries");
  expect(html).not.toContain("Add food dislike");
  expect(html).toContain("aria-invalid=\"true\"");
});
