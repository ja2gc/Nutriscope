// @vitest-environment jsdom
import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { expect, test, vi } from "vitest";
import { MenuEditorSlots, MenuEditorActions, MenuEditorAction } from "./MenuEditorSlots";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
test("slot cards open independently from their compact top-right remove action", async () => {
  const container = document.createElement('div'); document.body.append(container);
  const root = createRoot(container); const open = vi.fn(); const remove = vi.fn();
  try {
    await act(async () => root.render(<MenuEditorSlots items={[
      <div key="food"><MenuEditorActions><MenuEditorAction kind="remove" label="Remove Rice" onClick={remove} /></MenuEditorActions><p>Rice</p></div>,
    ]} itemActions={[{ label: 'View Rice', onOpen: open }]} addLabel="Add item" onAdd={() => {}} />));
    const card = container.querySelector<HTMLButtonElement>('[data-menu-editor-open]');
    expect(card).not.toBeNull();
    expect(container.querySelectorAll('[data-menu-editor-action="view"]')).toHaveLength(0);
    expect(container.querySelector('[data-menu-editor-actions]')?.className).toContain('justify-end');
    await act(async () => card!.click()); expect(open).toHaveBeenCalledTimes(1);
    await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Remove Rice"]')!.click());
    expect(open).toHaveBeenCalledTimes(1); expect(remove).toHaveBeenCalledTimes(1);
    expect(container.querySelector('[aria-label="Remove Rice"]')?.className).toContain('h-6 w-6');
    expect(container.querySelector('[data-menu-editor-add]')?.className).not.toContain('h-32');
    expect(container.querySelector('[data-menu-editor-item]')?.className).toContain('[&>div]:justify-center');
    expect(container.querySelector('[data-menu-editor-actions]')?.className).toContain('absolute right-1 top-1');
  } finally { await act(async () => root.unmount()); container.remove(); }
});
