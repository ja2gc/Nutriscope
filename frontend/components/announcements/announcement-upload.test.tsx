/** @vitest-environment jsdom */
import React, { act } from "react";
import { createRoot } from "react-dom/client";
import userEvent from "@testing-library/user-event";
import { expect, test, vi } from "vitest";
import { AnnouncementsBoard } from "./AnnouncementsBoard";

vi.mock("next/navigation", () => ({ useSearchParams: () => new URLSearchParams() }));
vi.mock("@/contexts/AuthContext", () => ({ useAuth: () => ({ user: { id: 1, role: "RND" } }) }));
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

test.each(["rnd", "admin"] as const)("%s waits for selected announcement images before allowing Post", async variant => {
  const readers: FileReader[] = [];
  let posted: { attachments: string[] } | undefined;
  vi.spyOn(FileReader.prototype, "readAsDataURL").mockImplementation(function (this: FileReader) { readers.push(this); });
  vi.stubGlobal("fetch", async (_url: string, init?: RequestInit) => {
    if (init?.method === "POST") posted = JSON.parse(init.body as string);
    return Response.json({ data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } });
  });
  const container = document.createElement("div");
  document.body.append(container);
  const root = createRoot(container);
  const button = (name: string) => [...container.querySelectorAll<HTMLButtonElement>("button")].find(item => item.textContent?.trim() === name);
  try {
    await act(async () => root.render(<AnnouncementsBoard variant={variant} />));
    await act(async () => button("Add Announcement")!.click());
    await act(async () => userEvent.setup().type(container.querySelector('textarea')!, "Example announcement"));
    const event = new Event("drop", { bubbles: true, cancelable: true });
    Object.defineProperty(event, "dataTransfer", { value: { files: [new File(["image"], "pending.png", { type: "image/png" })] } });
    await act(async () => container.querySelector('[aria-label="Drop files for Images"]')!.dispatchEvent(event));
    expect(button("Post Announcement")?.disabled).toBe(true);
    await act(async () => button("Post Announcement")!.click());
    expect(posted).toBeUndefined();
    await act(async () => {
      const reader = readers[0];
      Object.defineProperty(reader, "result", { value: "data:image/png;base64,aW1hZ2U=" });
      reader.dispatchEvent(new ProgressEvent("load"));
    });
    expect(button("Post Announcement")?.disabled).toBe(false);
    await act(async () => button("Post Announcement")!.click());
    expect(posted?.attachments).toEqual(["data:image/png;base64,aW1hZ2U="]);
  } finally {
    await act(async () => root.unmount());
    container.remove();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  }
});
