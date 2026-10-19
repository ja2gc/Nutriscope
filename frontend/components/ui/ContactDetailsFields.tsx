"use client";

import React, { useId } from 'react';
import { Button } from './Button';
import { InfoHint } from './InfoHint';
import { CONTACT_METHODS, contactMethodLabel, type ContactDetailsData, type ContactDetailsErrors, type ContactEntry, type ContactMethod } from '@/lib/contactDetails';

const inputClass = 'min-h-11 w-full min-w-0 rounded-lg border border-warm-200 bg-white px-3 py-2 text-base text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50';
const labelClass = 'mb-1 block break-all text-xs font-bold uppercase tracking-wider text-warm-500';

export function ContactDetailsFields({ value, onChange, legacyLabel = 'Contact', errors = {}, disabled = false }: {
  value: ContactDetailsData; onChange: (value: ContactDetailsData) => void; legacyLabel?: string; errors?: ContactDetailsErrors; disabled?: boolean;
}) {
  const prefix = useId();
  const fieldId = (key: string) => `${prefix}-${key}`;
  const error = (key: string) => errors[key] ? <p id={fieldId(`${key}-error`)} className="mt-1 text-sm text-red-600">{errors[key]}</p> : null;
  const errorProps = (key: string) => ({ 'aria-invalid': Boolean(errors[key]), 'aria-describedby': errors[key] ? fieldId(`${key}-error`) : undefined });
  const update = (id: string, patch: Partial<ContactEntry>) => onChange({ ...value, additional_contacts: value.additional_contacts.map(entry => entry.id === id ? { ...entry, ...patch } : entry) });
  return <fieldset disabled={disabled} className="min-w-0 space-y-4">
    <legend className="sr-only">Contact details</legend>
    <div><label htmlFor={fieldId('primary')} className={labelClass}>{legacyLabel}</label>
      <input id={fieldId('primary')} value={value.contact} maxLength={255} onChange={event => onChange({ ...value, contact: event.target.value, preferred_contact_id: !event.target.value.trim() && value.preferred_contact_id === 'primary' ? null : value.preferred_contact_id })} className={inputClass} {...errorProps('contact')} />{error('contact')}
    </div>
    <div className="flex flex-wrap items-center justify-between gap-2">
      <span className="text-sm font-semibold text-warm-600">Additional contacts ({value.additional_contacts.length}/5)</span>
      <Button type="button" variant="secondary" size="sm" disabled={disabled || value.additional_contacts.length >= 5} onClick={() => onChange({ ...value, additional_contacts: [...value.additional_contacts, { id: crypto.randomUUID(), method: 'phone', label: null, value: '' }] })}>Add contact</Button>
    </div>
    {error('additional_contacts')}
    {value.additional_contacts.map((entry, index) => <div key={entry.id} className="min-w-0 space-y-3 rounded-xl border border-warm-200 p-3">
      <div className="flex items-center justify-between gap-2"><span className="text-sm font-bold text-warm-600">Contact {index + 1}</span><Button type="button" variant="ghost" size="sm" aria-label={`Remove contact ${index + 1}`} onClick={() => onChange({ ...value, additional_contacts: value.additional_contacts.filter(contact => contact.id !== entry.id), preferred_contact_id: value.preferred_contact_id === entry.id ? null : value.preferred_contact_id })}>Remove</Button></div>
      <div className="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
        <div><label htmlFor={fieldId(`${entry.id}-method`)} className={labelClass}>Contact method</label>
          <select id={fieldId(`${entry.id}-method`)} aria-label={`Contact method ${index + 1}`} value={entry.method} onChange={event => update(entry.id, { method: event.target.value as ContactMethod, label: null })} className={inputClass}>{Object.entries(CONTACT_METHODS).map(([method, label]) => <option key={method} value={method}>{label}</option>)}</select>
        </div>
        {entry.method === 'other' && <div><div className="flex items-center gap-1"><label htmlFor={fieldId(`${entry.id}-label`)} className={labelClass}>Other contact method <span aria-hidden="true" className="text-red-500">*</span></label><InfoHint label="About other contact methods">For example, Viber, WhatsApp or a landline extension.</InfoHint></div>
          <input id={fieldId(`${entry.id}-label`)} aria-label={`Other contact method ${index + 1}`} required maxLength={50} value={entry.label ?? ''} onChange={event => update(entry.id, { label: event.target.value })} className={inputClass} {...errorProps(`${entry.id}.label`)} />{error(`${entry.id}.label`)}
        </div>}
        <div className={entry.method === 'other' ? 'sm:col-span-2' : ''}><label htmlFor={fieldId(`${entry.id}-value`)} className={labelClass}>{contactMethodLabel(entry)} <span aria-hidden="true" className="text-red-500">*</span></label>
          <input id={fieldId(`${entry.id}-value`)} aria-label={`${contactMethodLabel(entry)} contact ${index + 1}`} required type={entry.method === 'email' ? 'email' : entry.method === 'phone' ? 'tel' : 'text'} maxLength={255} value={entry.value} onChange={event => update(entry.id, { value: event.target.value })} className={inputClass} {...errorProps(`${entry.id}.value`)} />{error(`${entry.id}.value`)}
        </div>
      </div>
    </div>)}
    <div><label htmlFor={fieldId('preferred')} className={labelClass}>Preferred contact</label>
      <select id={fieldId('preferred')} value={value.preferred_contact_id ?? ''} onChange={event => onChange({ ...value, preferred_contact_id: event.target.value || null })} className={inputClass} {...errorProps('preferred_contact_id')}>
        <option value="">No preference selected</option>
        {value.contact.trim() && <option value="primary">{legacyLabel}: {value.contact}</option>}
        {value.additional_contacts.filter(entry => entry.value.trim()).map(entry => <option key={entry.id} value={entry.id}>{contactMethodLabel(entry)}: {entry.value}</option>)}
      </select>{error('preferred_contact_id')}
    </div>
  </fieldset>;
}

export function ContactDetailsSummary({ value, legacyLabel = 'Contact' }: { value: ContactDetailsData; legacyLabel?: string }) {
  const preferredEntry = value.additional_contacts.find(entry => entry.id === value.preferred_contact_id);
  const preferred = value.preferred_contact_id === 'primary' ? legacyLabel : preferredEntry ? contactMethodLabel(preferredEntry) : 'Not selected';
  return <div className="min-w-0 space-y-4 text-sm">
    <div><span className={labelClass}>Preferred contact</span><span className="font-semibold text-warm-800">{preferred}</span></div>
    <dl className="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
      {value.contact && <div className="min-w-0"><dt className={labelClass}>{legacyLabel}</dt><dd className="break-all text-warm-800">{value.contact}</dd></div>}
      {value.additional_contacts.map(entry => <div key={entry.id} className="min-w-0"><dt className={labelClass}>{contactMethodLabel(entry)}{value.preferred_contact_id === entry.id && <span className="ml-2 font-semibold text-emerald-700">Preferred</span>}</dt><dd className="break-all text-warm-800">{entry.value}</dd></div>)}
    </dl>
    {!value.contact && value.additional_contacts.length === 0 && <p className="text-warm-500">No contact details provided.</p>}
  </div>;
}
