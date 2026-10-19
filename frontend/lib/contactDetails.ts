export type ContactMethod = 'phone' | 'email' | 'messenger' | 'other';
export type ContactEntry = { id: string; method: ContactMethod; label: string | null; value: string };
export type ContactDetailsRecord = { contact?: string | null; additional_contacts?: ContactEntry[] | null; preferred_contact_id?: string | null };
export type ContactDetailsData = { contact: string; additional_contacts: ContactEntry[]; preferred_contact_id: string | null };
export type ContactDetailsErrors = Record<string, string>;
export const CONTACT_METHODS: Record<ContactMethod, string> = { phone: 'Phone', email: 'Email', messenger: 'Messenger', other: 'Other' };

export function contactMethodLabel(entry: ContactEntry): string {
  return entry.method === 'other' ? entry.label?.trim() || 'Other' : CONTACT_METHODS[entry.method];
}

export function contactDetailsFrom(record: ContactDetailsRecord): ContactDetailsData {
  return { contact: record.contact ?? '', additional_contacts: (record.additional_contacts ?? []).map(entry => ({ ...entry })), preferred_contact_id: record.preferred_contact_id ?? null };
}

export function contactDetailsPayload(value: ContactDetailsData): ContactDetailsRecord {
  return { contact: value.contact.trim() || null, additional_contacts: value.additional_contacts.map(entry => ({ ...entry, label: entry.method === 'other' ? entry.label?.trim() || null : null, value: entry.value.trim() })), preferred_contact_id: value.preferred_contact_id };
}

export function contactDetailsErrors(value: ContactDetailsData): ContactDetailsErrors {
  const errors: ContactDetailsErrors = {};
  if (value.contact.length > 255) errors.contact = 'Use at most 255 characters.';
  if (value.additional_contacts.length > 5) errors.additional_contacts = 'Add up to 5 additional contacts.';
  for (const entry of value.additional_contacts) {
    if (entry.method === 'other' && !entry.label?.trim()) errors[`${entry.id}.label`] = 'Enter a contact method.';
    else if ((entry.label?.length ?? 0) > 50) errors[`${entry.id}.label`] = 'Use at most 50 characters.';
    if (!entry.value.trim()) errors[`${entry.id}.value`] = 'Enter the contact details.';
    else if (entry.value.length > 255) errors[`${entry.id}.value`] = 'Use at most 255 characters.';
    else if (entry.method === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(entry.value.trim())) errors[`${entry.id}.value`] = 'Enter a valid email address.';
    else if (entry.method === 'phone' && !/^\+?(?=.*[0-9])[0-9()\s.-]+$/.test(entry.value.trim())) errors[`${entry.id}.value`] = 'Enter a phone number using digits and phone punctuation.';
  }
  if (value.preferred_contact_id && (value.preferred_contact_id === 'primary' ? !value.contact.trim() : !value.additional_contacts.some(entry => entry.id === value.preferred_contact_id && entry.value.trim()))) errors.preferred_contact_id = 'Select an available contact or clear the preference.';
  return errors;
}
