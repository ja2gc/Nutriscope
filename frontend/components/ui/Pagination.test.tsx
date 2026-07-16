/** @vitest-environment jsdom */

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";

import { Pagination } from "./Pagination";

declare global {
  var IS_REACT_ACT_ENVIRONMENT: boolean;
}

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

describe("Pagination", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
  });

  afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
  });

  test("shows a disabled visible footer for a one-page result", async () => {
    const onPageChange = vi.fn();

    await act(async () => root.render(
      <Pagination
        meta={{ current_page: 1, per_page: 10, total: 3, last_page: 1 }}
        page={1}
        onPageChange={onPageChange}
      />
    ));

    expect(container.textContent).toContain("Page 1 of 1");
    expect(container.querySelector<HTMLButtonElement>('button[aria-label="Previous page"]')?.disabled).toBe(true);
    expect(container.querySelector<HTMLButtonElement>('button[aria-label="Next page"]')?.disabled).toBe(true);
  });
});
