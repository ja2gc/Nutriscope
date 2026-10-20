<?php

namespace App\Http\Requests\RND;

use App\Support\DiagnosisBuilder;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(DiagnosisBuilder::rules(), [
            'domain' => ['required', 'string', 'in:NI,NC,NB'],
            'problem' => ['required', 'string', 'max:255'],
            'etiology' => ['required', 'string', 'max:400'],
            'signs_symptoms' => ['required', 'string', 'max:400'],
            'pes_statement' => ['nullable', 'string', 'max:500'],
            'extra_notes' => ['nullable', 'string', 'max:125'],
            'ai_generated' => ['nullable', 'boolean'],
        ]);
    }
}
