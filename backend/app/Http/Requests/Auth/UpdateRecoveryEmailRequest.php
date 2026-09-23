<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

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
