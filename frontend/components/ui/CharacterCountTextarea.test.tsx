import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test } from "vitest";
import { CharacterCountTextarea } from "./CharacterCountTextarea";

describe("CharacterCountTextarea", () => {
  test("shows a compact count inside the textarea and enforces its field limit", () => {
    const html = renderToStaticMarkup(
      <CharacterCountTextarea value="abc" onChange={() => undefined} maxLength={250} aria-label="Notes" />,
    );

    expect(html).toContain('maxLength="250"');
    expect(html).toContain('aria-label="Notes"');
    expect(html).toContain("aria-describedby=");
    expect(html).toContain("3/250");
    expect(html).toContain("absolute bottom-2.5 right-3 text-xs font-normal");
    expect(html).toContain("pb-7");
  });
});
