<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain'         => ['required', 'string', 'in:NI,NC,NB'],
            'problem'        => ['required', 'string', 'max:255'],
            'etiology'       => ['required', 'string'],
            'signs_symptoms' => ['required', 'string'],
            'pes_statement'  => ['nullable', 'string'],
            'extra_notes'    => ['nullable', 'string'],
            'ai_generated'   => ['nullable', 'boolean'],
        ];
    }
}
