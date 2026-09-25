<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class AiSuggestDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dismissed_candidate_id' => ['sometimes', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
        ];
    }
}
