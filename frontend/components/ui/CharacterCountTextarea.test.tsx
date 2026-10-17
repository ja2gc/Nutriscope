// @vitest-environment jsdom

import { act, useState } from "react";
import { createRoot } from "react-dom/client";
import { renderToStaticMarkup } from "react-dom/server";
import userEvent from "@testing-library/user-event";
import { describe, expect, test, vi } from "vitest";
import { CharacterCountTextarea } from "./CharacterCountTextarea";

describe("CharacterCountTextarea", () => {
  test("auto-grow includes borders and recalculates when its width changes", () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);
    let resize = () => {};
    vi.stubGlobal("ResizeObserver", class {
      constructor(callback: () => void) { resize = callback; }
      observe() {}
      disconnect() {}
    });
    const width = vi.spyOn(Element.prototype, "clientWidth", "get").mockReturnValue(200);
    const height = vi.spyOn(Element.prototype, "scrollHeight", "get").mockReturnValue(62);
    vi.spyOn(Element.prototype, "clientHeight", "get").mockReturnValue(60);
    vi.spyOn(HTMLElement.prototype, "offsetHeight", "get").mockReturnValue(62);
    try {
      act(() => root.render(<CharacterCountTextarea value="Notes" onChange={() => undefined} maxLength={250} autoGrow />));
      const textarea = container.querySelector("textarea")!;
      expect(textarea.style.height).toBe("64px");
      width.mockReturnValue(120);
      height.mockReturnValue(92);
      act(() => resize());
      expect(textarea.style.height).toBe("94px");
    } finally {
      act(() => root.unmount());
      container.remove();
      vi.restoreAllMocks();
      vi.unstubAllGlobals();
      (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = false;
    }
  });
  test("shows a compact count inside the textarea and enforces its field limit", () => {
    const html = renderToStaticMarkup(
      <CharacterCountTextarea value="abc" onChange={() => undefined} maxLength={250} aria-label="Notes" />,
    );

    expect(html).toContain('aria-label="Notes"');
    expect(html).toContain("aria-describedby=");
    expect(html).toContain("3/250");
    expect(html).toContain("absolute bottom-2.5 right-3 text-xs font-normal");
    expect(html).toContain("pb-7");
  });

  test("pasting at the limit counts complete Unicode characters", async () => {
    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);
    function Notes() {
      const [value, setValue] = useState("");
      return <CharacterCountTextarea value={value} onChange={setValue} maxLength={2} aria-label="Notes" />;
    }
    try {
      await act(async () => root.render(<Notes />));
      const user = userEvent.setup();
      const textarea = container.querySelector("textarea")!;
      await act(async () => {
        await user.click(textarea);
        await user.paste("😀😀x");
      });
      expect(textarea.value).toBe("😀😀");
      expect(container.textContent).toContain("2/2");
    } finally {
      await act(async () => root.unmount());
      container.remove();
      (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = false;
    }
  });

  test("keeps an existing over-limit value visible for correction", () => {
    const html = renderToStaticMarkup(
      <CharacterCountTextarea value="existing text" onChange={() => undefined} maxLength={5} />,
    );
    expect(html).toContain("existing text");
    expect(html).toContain("13/5");
    expect(html).toContain('aria-invalid="true"');
  });
});
