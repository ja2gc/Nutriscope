<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class RecommendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*' => ['required', 'string'],
            'stages' => ['nullable', 'array'],
            'stages.*' => ['nullable', 'string'],
        ];
    }
}
