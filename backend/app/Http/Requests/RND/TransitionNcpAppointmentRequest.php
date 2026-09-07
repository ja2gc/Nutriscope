<?php

namespace App\Http\Requests\RND;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionNcpAppointmentRequest extends FormRequest
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
            'action' => ['required', Rule::in(['start', 'finish', 'end_early', 'cancel', 'no_show', 'reschedule', 'discard'])],
            'reason_code' => [
                Rule::requiredIf(in_array($this->input('action'), ['cancel', 'end_early'], true)),
                'nullable',
                Rule::in([
                    'patient_requested', 'provider_unavailable', 'patient_hospitalized_or_discharged',
                    'scheduling_conflict', 'lost_to_follow_up', 'patient_left', 'other',
                ]),
            ],
            'scheduled_at' => [Rule::requiredIf($this->input('action') === 'reschedule'), 'nullable', 'date'],
            'purpose' => [Rule::requiredIf($this->input('action') === 'reschedule'), 'nullable', 'string', 'max:255'],
            'ncp_record_id' => ['nullable', 'uuid'],
        ];
    }
}
