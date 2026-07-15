<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\ValidatesPersonNameChanges;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    use ValidatesPersonNameChanges;

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
            ...$this->splitNameUpdateRules($this->user()),
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'profile_photo' => [
                'nullable',
                'string',
                'max:300000',
                'regex:/^data:image\/(png|jpeg|webp);base64,/',
            ],
        ];
    }
}
