<?php

namespace App\Http\Requests\RND;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNcpAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'RND';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['scheduled', 'walk_in'])],
            'purpose' => ['required', 'string', 'max:255'],
            'scheduled_at' => [Rule::requiredIf($this->input('source') === 'scheduled'), 'nullable', 'date'],
            'ncp_record_id' => ['nullable', 'uuid'],
        ];
    }
}
