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
            'etiology' => ['sometimes', 'required', 'string', 'max:400'],
            'signs_symptoms' => ['sometimes', 'required', 'string', 'max:400'],
            'pes_statement' => ['nullable', 'string', 'max:500'],
            'extra_notes' => ['nullable', 'string', 'max:125'],
            'ai_generated' => ['nullable', 'boolean'],
        ];
    }
}
