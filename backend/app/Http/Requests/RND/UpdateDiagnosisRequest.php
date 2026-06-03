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
        return [
            'domain'         => ['nullable', 'string', 'in:NI,NC,NB'],
            'problem'        => ['nullable', 'string', 'max:255'],
            'etiology'       => ['nullable', 'string'],
            'signs_symptoms' => ['nullable', 'string'],
            'extra_notes'    => ['nullable', 'string'],
            'ai_generated'   => ['nullable', 'boolean'],
        ];
    }
}
