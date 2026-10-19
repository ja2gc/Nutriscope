<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesContactDetails
{
    protected function contactDetailsRules(): array
    {
        return [
            'additional_contacts' => ['sometimes', 'nullable', 'array', 'list', 'max:5'],
            'additional_contacts.*' => ['required', 'array:id,method,label,value'],
            'additional_contacts.*.id' => ['required', 'uuid', 'distinct:ignore_case'],
            'additional_contacts.*.method' => ['required', 'in:phone,email,messenger,other'],
            'additional_contacts.*.label' => ['nullable', 'string', 'max:50'],
            'additional_contacts.*.value' => ['required', 'string', 'max:255'],
            'preferred_contact_id' => ['sometimes', 'nullable', 'string', 'max:36'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $record = $this->route('patient') ?? $this->route('supplier');
            $contacts = $this->input('additional_contacts', $record?->additional_contacts) ?? [];
            $preferred = $this->input('preferred_contact_id', $record?->preferred_contact_id);
            $primary = $this->input('contact', $record?->contact);
            foreach ($contacts as $index => $entry) {
                if ($entry['method'] === 'other' && trim($entry['label'] ?? '') === '') {
                    $validator->errors()->add("additional_contacts.$index.label", 'Enter a contact method, such as Viber or WhatsApp.');
                }
                if ($entry['method'] === 'email' && ! filter_var($entry['value'], FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add("additional_contacts.$index.value", 'Enter a valid email address.');
                }
                if ($entry['method'] === 'phone' && ! preg_match('/^\+?(?=.*[0-9])[0-9()\s.-]+$/', $entry['value'])) {
                    $validator->errors()->add("additional_contacts.$index.value", 'Enter a phone number using digits and phone punctuation.');
                }
            }
            if ($preferred !== null && (($preferred === 'primary' && ! filled($primary)) || ($preferred !== 'primary' && ! in_array($preferred, array_column($contacts, 'id'), true)))) {
                $validator->errors()->add('preferred_contact_id', 'Select an available contact or clear the preference.');
            }
        }];
    }
}
