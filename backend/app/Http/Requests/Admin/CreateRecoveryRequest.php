<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecoveryIncidentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRecoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'Admin';
    }

    public function rules(): array
    {
        return [
            'incident_type' => ['required', Rule::enum(RecoveryIncidentType::class)],
            'note' => ['required', 'string', 'min:10', 'max:500'],
            'current_password' => ['required', 'string', 'current_password'],
            'confirmation' => ['required', 'string', Rule::in(['RESTORE '.$this->route('backupRun')?->uuid])],
        ];
    }
}
