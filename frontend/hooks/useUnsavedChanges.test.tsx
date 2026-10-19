// @vitest-environment jsdom
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, test, vi } from 'vitest';
import { useUnsavedChanges } from './useUnsavedChanges';

test('X stays, failed saves stay, successful saves leave, and discard skips saving', async () => {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  const host = document.createElement('div'); document.body.append(host);
  const root = createRoot(host), leave = vi.fn(), save = vi.fn().mockResolvedValue(false);
  function Editor() {
    const guard = useUnsavedChanges({ dirty: true, onSave: save });
    return <><button onClick={() => guard.requestLeave(leave)}>Back</button>{guard.dialog}</>;
  }
  const click = async (text: string) => act(async () => Array.from(host.querySelectorAll('button')).find((button) => button.textContent === text)!.click());
  try {
    await act(async () => root.render(<Editor />));
    await click('Back');
    expect(host.querySelector('[role="dialog"]')).not.toBeNull();
    await act(async () => host.querySelector<HTMLButtonElement>('[aria-label="Stay on this page"]')!.click());
    expect(leave).not.toHaveBeenCalled();
    await click('Back'); await click('Save');
    expect(leave).not.toHaveBeenCalled();
    expect(host.querySelector('[role="dialog"]')).not.toBeNull();
    save.mockResolvedValue(true); await click('Save');
    expect(leave).toHaveBeenCalledOnce();
    await act(async () => root.render(<Editor key="second" />));
    await click('Back'); await click('Discard');
    expect(leave).toHaveBeenCalledTimes(2);
    expect(save).toHaveBeenCalledTimes(2);
  } finally { await act(async () => root.unmount()); host.remove(); }
});
