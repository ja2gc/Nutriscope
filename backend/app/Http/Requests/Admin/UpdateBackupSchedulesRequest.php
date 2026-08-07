<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBackupSchedulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'Admin';
    }

    public function rules(): array
    {
        return [
            'daily' => ['required', 'boolean'],
            'weekly' => ['required', 'boolean'],
            'monthly' => ['required', 'boolean'],
            'confirm_disable_all' => ['sometimes', 'boolean'],
        ];
    }
}
