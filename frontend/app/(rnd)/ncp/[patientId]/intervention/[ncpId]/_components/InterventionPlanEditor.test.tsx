// @vitest-environment jsdom
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, test, vi } from 'vitest';
import { getByRole } from '@testing-library/dom';
import InterventionPlanEditor from './InterventionPlanEditor';

test('shows cancel progress and blocks a competing save until cancel finishes', async () => {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  const container = document.createElement('div'); document.body.append(container);
  const root = createRoot(container);
  let finish!: () => void;
  const pending = new Promise<void>((resolve) => { finish = resolve; });
  const cancel = vi.fn(() => pending), save = vi.fn();
  try {
    await act(async () => root.render(<InterventionPlanEditor mode="edit" plan={null} saving={false} error={null} onCancel={cancel} onSave={save}>Prescription</InterventionPlanEditor>));
    const button = getByRole(container, 'button', { name: 'Cancel' });
    await act(async () => button.click());
    expect(button.getAttribute('aria-busy')).toBe('true');
    expect((getByRole(container, 'button', { name: 'Save Intervention Plan' }) as HTMLButtonElement).disabled).toBe(true);
    expect(cancel).toHaveBeenCalledOnce();
    await act(async () => finish());
    expect(getByRole(container, 'button', { name: 'Cancel' }).hasAttribute('disabled')).toBe(false);
  } finally { await act(async () => root.unmount()); container.remove(); }
});
