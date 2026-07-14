<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // `sometimes|required` enforces the PES invariant (DP-03): a field may be
        // omitted, but if present it cannot be blanked — preventing edits that leave
        // a malformed "... related to  as evidenced by ..." statement.
        return [
            'domain' => ['sometimes', 'required', 'string', 'in:NI,NC,NB'],
            'problem' => ['sometimes', 'required', 'string', 'max:255'],
            'etiology' => ['sometimes', 'required', 'string'],
            'signs_symptoms' => ['sometimes', 'required', 'string'],
            'pes_statement' => ['nullable', 'string'],
            'extra_notes' => ['nullable', 'string'],
            'ai_generated' => ['nullable', 'boolean'],
        ];
    }
}
