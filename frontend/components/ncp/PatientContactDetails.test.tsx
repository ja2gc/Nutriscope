// @vitest-environment jsdom
import React, { act, createRef } from 'react';
import { createRoot } from 'react-dom/client';
import { fireEvent, getByRole } from '@testing-library/dom';
import { expect, it, vi } from 'vitest';
import { PatientContactDetails } from './PatientContactDetails';
import { updatePatient, type Patient } from '@/services/patientService';
import type { EditorLeaveHandle } from '@/hooks/useUnsavedChanges';

vi.mock('@/services/patientService', () => ({ updatePatient: vi.fn() }));
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

it('keeps an invalid draft and uses the shared leave guard before saving contact preferences', async () => {
  const patient = { id: 1, contact: '09171234567', additional_contacts: [], preferred_contact_id: null, can_delete: false, next_appointment_at: '2026-11-01' } as unknown as Patient;
  const saved = vi.fn(); const leaveRef = createRef<EditorLeaveHandle>(); const next = vi.fn();
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<PatientContactDetails patient={patient} onSaved={saved} leaveRef={leaveRef} />));
  try {
    await act(async () => getByRole(container, 'button', { name: 'Edit contact details' }).click());
    await act(async () => getByRole(container, 'button', { name: 'Add contact' }).click());
    await act(async () => getByRole(container, 'button', { name: 'Save contact details' }).click());
    expect(updatePatient).not.toHaveBeenCalled();
    expect(container.textContent).toContain('Enter the contact details.');
    await act(async () => fireEvent.input(getByRole(container, 'textbox', { name: 'Phone contact 1' }), { target: { value: '09179876543' } }));
    const preferred = getByRole(container, 'checkbox', { name: 'Preferred Phone contact 1' }) as HTMLInputElement;
    const preferredId = preferred.value;
    await act(async () => preferred.click());
    await act(async () => leaveRef.current!.requestLeave(next));
    expect(getByRole(container, 'dialog', { name: 'Unsaved changes' })).toBeTruthy();
    vi.mocked(updatePatient).mockRejectedValueOnce(new Error('Temporary save failure'));
    await act(async () => getByRole(container, 'button', { name: 'Save' }).click());
    expect(next).not.toHaveBeenCalled();
    expect(container.textContent).toContain('Temporary save failure');
    expect((getByRole(container, 'textbox', { name: 'Phone contact 1' }) as HTMLInputElement).value).toBe('09179876543');
    vi.mocked(updatePatient).mockResolvedValue({ ...patient, can_delete: null, next_appointment_at: null, additional_contacts: [{ id: preferredId, method: 'phone', label: null, value: '09179876543' }], preferred_contact_id: preferredId });
    await act(async () => getByRole(container, 'button', { name: 'Save' }).click());
    expect(next).toHaveBeenCalledOnce();
    expect(saved).toHaveBeenCalledOnce();
    expect(saved).toHaveBeenCalledWith(expect.objectContaining({ can_delete: false, next_appointment_at: '2026-11-01' }));
    expect(updatePatient).toHaveBeenCalledWith(1, expect.objectContaining({ contact: '09171234567', preferred_contact_id: preferredId }));
  } finally { await act(async () => root.unmount()); container.remove(); }
});
