<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecoveryEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recovery_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
                Rule::unique('users', 'recovery_email')->ignore($this->user()->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recovery_email' => strtolower(trim((string) $this->input('recovery_email'))),
        ]);
    }
}
