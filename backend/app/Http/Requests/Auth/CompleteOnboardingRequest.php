<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->requiresOnboarding() === true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'recovery_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
                Rule::unique('users', 'recovery_email')->ignore($this->user()->id),
                Rule::unique('users', 'pending_recovery_email')->ignore($this->user()->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['recovery_email' => strtolower(trim((string) $this->input('recovery_email')))]);
    }
}
