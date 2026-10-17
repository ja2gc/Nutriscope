// @vitest-environment jsdom

import { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, expect, it, vi } from "vitest";
import { FoodServiceMenuItems } from "./FoodServiceMenuItems";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }) }));

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

afterEach(() => {
  document.body.innerHTML = "";
  vi.unstubAllGlobals();
});

it("names edit and delete actions for each Menu Item", async () => {
  vi.stubGlobal("fetch", vi.fn(async () => Response.json({ data: [{ id: 7, name: "QA stew", category: "meal", servings: 5 }], meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 } })));
  const container = document.createElement("div");
  document.body.append(container);
  const root = createRoot(container);

  await act(async () => root.render(<FoodServiceMenuItems canManage />));

  expect(container.querySelector('[aria-label="Edit QA stew"]')).not.toBeNull();
  expect(container.querySelector('[aria-label="Delete QA stew"]')).not.toBeNull();
  await act(async () => root.unmount());
});
