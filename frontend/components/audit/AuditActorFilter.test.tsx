// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import { listAuditActors } from "@/services/auditActorService";
import { AuditActorFilter } from "./AuditActorFilter";

vi.mock("@/services/auditActorService", () => ({ listAuditActors: vi.fn() }));
const listMock = vi.mocked(listAuditActors);

describe("AuditActorFilter", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    vi.useFakeTimers();
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
    vi.useRealTimers();
  });

  test("searches names, exposes more pages, and selects an actor by public id", async () => {
    const onChange = vi.fn();
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    listMock.mockImplementation(async (params = {}) => ({
      data: params.page === 2
        ? [{ id: "actor-2", name: "Jose Santos", role: "FSS" }]
        : [{ id: "actor-1", name: "Maria Dela Cruz", role: "RND" }],
      meta: { current_page: params.page || 1, last_page: 2, per_page: 10, total: 2 },
    }));

    act(() => root.render(<AuditActorFilter onChange={onChange} />));
    const input = container.querySelector<HTMLInputElement>('[role="combobox"]')!;
    act(() => input.focus());
    await act(async () => {
      vi.advanceTimersByTime(250);
      await Promise.resolve();
    });

    expect(listMock).toHaveBeenCalledWith({ search: undefined, page: 1, per_page: 10 }, expect.any(AbortSignal));
    expect(container.textContent).toContain("Maria Dela Cruz");
    expect(container.textContent).toContain("Load more actors");

    await act(async () => user.type(input, "Maria"));
    await act(async () => {
      vi.advanceTimersByTime(250);
      await Promise.resolve();
    });
    expect(listMock).toHaveBeenCalledWith({ search: "Maria", page: 1, per_page: 10 }, expect.any(AbortSignal));

    await act(async () => container.querySelector<HTMLButtonElement>("button:last-child")!.click());
    expect(listMock).toHaveBeenCalledWith({ search: "Maria", page: 2, per_page: 10 });
    expect(container.textContent).toContain("Jose Santos");

    const actor = Array.from(container.querySelectorAll<HTMLButtonElement>('[role="option"]'))
      .find((option) => option.textContent?.includes("Jose Santos"))!;
    act(() => actor.click());
    expect(onChange).toHaveBeenCalledWith("actor-2");
    expect(input.value).toBe("Jose Santos");
  });
});
