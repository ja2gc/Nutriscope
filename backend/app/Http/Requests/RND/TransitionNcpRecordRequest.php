<?php

namespace App\Http\Requests\RND;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionNcpRecordRequest extends FormRequest
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
            'action' => ['required', Rule::in(['complete', 'discontinue'])],
            'reason_code' => [
                Rule::requiredIf($this->input('action') === 'discontinue'),
                'nullable',
                Rule::in(['lost_to_follow_up', 'patient_declined', 'transferred', 'discharged_before_completion', 'care_elsewhere', 'other']),
            ],
        ];
    }
}
