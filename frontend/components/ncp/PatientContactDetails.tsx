"use client";
import { updatePatient, type Patient } from '@/services/patientService';
import { useEditorChanges, type EditorLeaveHandle } from '@/hooks/useUnsavedChanges';
import React, { useImperativeHandle, useState, type Ref } from 'react';
import { Button } from '@/components/ui/Button';
import { ContactDetailsFields, ContactDetailsSummary } from '@/components/ui/ContactDetailsFields';
import { contactDetailsFrom, contactDetailsPayload, contactDetailsErrors, type ContactDetailsErrors } from '@/lib/contactDetails';

export function PatientContactDetails({ patient, onSaved, leaveRef }: { patient: Patient; onSaved: (patient: Patient) => void; leaveRef?: Ref<EditorLeaveHandle> }) {
  const [editing, setEditing] = useState(false);
  const [value, setValue] = useState(() => contactDetailsFrom(patient));
  const [errors, setErrors] = useState<ContactDetailsErrors>({});
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const guard = useEditorChanges(JSON.stringify(value), editing, save);
  useImperativeHandle(leaveRef, () => ({ requestLeave: guard.requestLeave }), [guard.requestLeave]);

  async function save(): Promise<boolean> {
    const validation = contactDetailsErrors(value);
    setErrors(validation); setError('');
    if (Object.keys(validation).length) return false;
    setSaving(true);
    try {
      const updated = await updatePatient(patient.id, contactDetailsPayload(value));
      const next = contactDetailsFrom(updated);
      setValue(next); guard.markSaved(JSON.stringify(next)); setEditing(false); setSaved(true);
      onSaved({ ...patient, contact: updated.contact, additional_contacts: updated.additional_contacts, preferred_contact_id: updated.preferred_contact_id, updated_at: updated.updated_at });
      return true;
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not save contact details. Try again.'); return false; }
    finally { setSaving(false); }
  }

  function cancel() { setValue(contactDetailsFrom(patient)); setEditing(false); setErrors({}); setError(''); }

  return <section className="min-w-0 overflow-hidden rounded-2xl border border-warm-200 bg-white" aria-label="Patient contact details">
    {guard.dialog}
    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-warm-100 bg-warm-50 px-5 py-4">
      <h3 className="text-sm font-bold uppercase tracking-wider text-warm-900">Contact details</h3>
      {!editing && <Button type="button" variant="secondary" size="sm" onClick={() => { setValue(contactDetailsFrom(patient)); setSaved(false); setEditing(true); }}>Edit contact details</Button>}
    </div>
    <div className="min-w-0 space-y-4 p-5">
      {editing ? <>
        <ContactDetailsFields value={value} onChange={next => { setValue(next); setErrors({}); setError(''); }} legacyLabel="Contact number" errors={errors} disabled={saving} />
        {error && <p role="alert" className="text-sm text-red-600">{error}</p>}
        <div className="flex flex-wrap justify-end gap-2"><Button type="button" variant="secondary" disabled={saving} onClick={() => guard.requestLeave(cancel)}>Cancel</Button><Button type="button" loading={saving} onClick={() => void save()}>Save contact details</Button></div>
      </> : <ContactDetailsSummary value={contactDetailsFrom(patient)} legacyLabel="Contact number" />}
      {saved && !editing && <p role="status" className="text-sm text-emerald-700">Contact details saved.</p>}
    </div>
  </section>;
}
