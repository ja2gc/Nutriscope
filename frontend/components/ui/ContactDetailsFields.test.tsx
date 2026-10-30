// @vitest-environment jsdom
import React, { act, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { fireEvent, getAllByRole, getByRole } from '@testing-library/dom';
import { expect, it } from 'vitest';
import { ContactDetailsFields, ContactDetailsSummary } from './ContactDetailsFields';
import { contactDetailsErrors, type ContactDetailsData } from '@/lib/contactDetails';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

it('adds five contacts, chooses a preference and clears it when that contact is removed', async () => {
  let current: ContactDetailsData;
  function Form() {
    const [value, setValue] = useState<ContactDetailsData>({ contact: '09171234567', additional_contacts: [], preferred_contact_id: null });
    current = value;
    return <ContactDetailsFields value={value} onChange={setValue} legacyLabel="Contact number" />;
  }
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<Form />));
  try {
    for (let index = 0; index < 5; index++) await act(async () => getByRole(container, 'button', { name: 'Add contact' }).click());
    expect(getAllByRole(container, 'combobox', { name: /Contact method/ })).toHaveLength(5);
    expect((getByRole(container, 'button', { name: 'Add contact' }) as HTMLButtonElement).disabled).toBe(true);
    await act(async () => fireEvent.change(getByRole(container, 'combobox', { name: 'Contact method 1' }), { target: { value: 'email' } }));
    await act(async () => fireEvent.input(getByRole(container, 'textbox', { name: 'Email contact 1' }), { target: { value: 'demo@example.test' } }));
    await act(async () => getByRole(container, 'checkbox', { name: 'Preferred contact number' }).click());
    expect(current!.preferred_contact_id).toBe('primary');
    await act(async () => getByRole(container, 'checkbox', { name: 'Preferred Email contact 1' }).click());
    expect(current!.preferred_contact_id).toBe(current!.additional_contacts[0].id);
    expect((getByRole(container, 'checkbox', { name: 'Preferred contact number' }) as HTMLInputElement).checked).toBe(false);
    await act(async () => getByRole(container, 'button', { name: 'Remove contact 1' }).click());
    expect(current!.preferred_contact_id).toBeNull();
    expect(current!.contact).toBe('09171234567');
  } finally { await act(async () => root.unmount()); container.remove(); }
});

it('shows one preferred badge in the profile and only the preferred contact in compact cards', async () => {
  const value: ContactDetailsData = { contact: 'Original number', additional_contacts: [{ id: 'email', method: 'email', label: null, value: 'demo@example.test' }], preferred_contact_id: 'email' };
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  try {
    await act(async () => root.render(<ContactDetailsSummary value={value} />));
    expect(container.textContent).not.toContain('Preferred contact');
    expect(container.querySelectorAll('dt')[1].textContent).toBe('EmailPreferred');
    await act(async () => root.render(<ContactDetailsSummary value={value} compact />));
    expect(container.textContent).not.toContain('Preferred');
    expect(container.textContent).not.toContain('Original number');
    expect(container.querySelectorAll('dd')).toHaveLength(1);
    expect(container.querySelectorAll('dd')[0].textContent).toBe('demo@example.test');
  } finally { await act(async () => root.unmount()); container.remove(); }
});

it('validates custom labels, email, phone, limits and dangling preferences', () => {
  const value: ContactDetailsData = { contact: '', additional_contacts: [{ id: 'one', method: 'email', label: null, value: 'bad' }], preferred_contact_id: 'missing' };
  expect(contactDetailsErrors(value)).toMatchObject({ 'one.value': expect.any(String), preferred_contact_id: expect.any(String) });
  value.additional_contacts[0] = { id: 'one', method: 'other', label: '', value: 'Demo Viber handle' };
  expect(contactDetailsErrors(value)['one.label']).toBeTruthy();
  value.additional_contacts[0] = { id: 'one', method: 'phone', label: null, value: 'letters' };
  expect(contactDetailsErrors(value)['one.value']).toBeTruthy();
});
