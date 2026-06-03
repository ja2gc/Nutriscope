<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class AiApproveDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain'   => ['required', 'string', 'in:NI,NC,NB'],
            'label'    => ['required', 'string', 'max:500'],
            'etiology' => ['required', 'string'],
            'signs'    => ['required', 'string'],
            'priority' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
