<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesPersonNameChanges;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    use ValidatesPersonNameChanges;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        abort_unless($user instanceof User, 404);

        return [
            ...$this->splitNameUpdateRules($user),
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'string', 'in:Admin,RND,FSS'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
