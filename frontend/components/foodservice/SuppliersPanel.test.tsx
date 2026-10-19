// @vitest-environment jsdom
import React, { act, createRef } from 'react';
import { createRoot } from 'react-dom/client';
import { fireEvent, getByRole } from '@testing-library/dom';
import { expect, it, vi } from 'vitest';
import { SuppliersPanel } from './SuppliersPanel';
import { listSuppliers, saveSupplier, type Supplier } from '@/services/supplierService';
import type { EditorLeaveHandle } from '@/hooks/useUnsavedChanges';

vi.mock('@/services/supplierService', () => ({ listSuppliers: vi.fn(), saveSupplier: vi.fn(), deleteSupplier: vi.fn() }));
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

it('uses the shared contacts editor and protects its draft when leaving the vendor tab', async () => {
  vi.useFakeTimers();
  const vendor = { id: 1, name: 'Demo vendor', contact: 'Legacy contact', additional_contacts: [], preferred_contact_id: null } as unknown as Supplier;
  const meta = { current_page: 1, last_page: 1, per_page: 10, total: 1 };
  vi.mocked(listSuppliers).mockResolvedValue({ data: [vendor], meta });
  vi.mocked(saveSupplier).mockImplementation(async (_id, payload) => {
    const updated = { ...vendor, ...payload };
    vi.mocked(listSuppliers).mockResolvedValue({ data: [updated], meta });
    return updated;
  });
  const leaveRef = createRef<EditorLeaveHandle>(); const next = vi.fn();
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<SuppliersPanel leaveRef={leaveRef} />));
  await act(async () => { vi.advanceTimersByTime(300); });
  try {
    await act(async () => getByRole(container, 'button', { name: 'Edit Demo vendor' }).click());
    await act(async () => getByRole(container, 'button', { name: 'Add contact' }).click());
    await act(async () => fireEvent.change(getByRole(container, 'combobox', { name: 'Contact method 1' }), { target: { value: 'messenger' } }));
    await act(async () => fireEvent.input(getByRole(container, 'textbox', { name: 'Messenger contact 1' }), { target: { value: 'Demo vendor page' } }));
    const preference = getByRole(container, 'checkbox', { name: 'Preferred Messenger contact 1' }) as HTMLInputElement;
    const id = preference.value;
    await act(async () => preference.click());
    await act(async () => leaveRef.current!.requestLeave(next));
    expect(getByRole(container, 'dialog')).toBeTruthy();
    await act(async () => getByRole(container, 'button', { name: 'Save' }).click());
    expect(next).toHaveBeenCalledOnce();
    expect(saveSupplier).toHaveBeenCalledWith(1, expect.objectContaining({ contact: 'Legacy contact', additional_contacts: [{ id, method: 'messenger', label: null, value: 'Demo vendor page' }], preferred_contact_id: id }));
  } finally { await act(async () => root.unmount()); container.remove(); vi.useRealTimers(); }
});
