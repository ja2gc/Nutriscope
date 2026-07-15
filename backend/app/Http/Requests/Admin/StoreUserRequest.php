<?php

namespace App\Http\Requests\Admin;

use App\Rules\PersonName;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'first_name' => ['required', 'string', new PersonName],
            'last_name' => ['required', 'string', new PersonName],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'in:Admin,RND,FSS'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
