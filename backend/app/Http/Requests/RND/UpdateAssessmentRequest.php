<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dietary_intake'       => ['nullable', 'string'],
            'appetite_changes'     => ['nullable', 'string'],
            'dietary_restrictions' => ['nullable', 'string'],
            'supplements'          => ['nullable', 'string'],
            'knowledge_notes'      => ['nullable', 'string'],
            'weight'               => ['nullable', 'numeric', 'min:0'],
            'height'               => ['nullable', 'numeric', 'min:0'],
            'body_composition'     => ['nullable', 'string'],
            'medical_history'      => ['nullable', 'string'],
            'social_history'       => ['nullable', 'string'],
            'lifestyle'            => ['nullable', 'string'],
            'allergies'            => ['nullable', 'array'],
            'food_dislikes'        => ['nullable', 'array'],
            'medications'          => ['nullable', 'array'],
            'rnd_summary'          => ['nullable', 'string'],
        ];
    }
}
