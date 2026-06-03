<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarEventRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'      => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'event_type' => ['nullable', 'string'],
            'notes'      => ['nullable', 'string'],
        ];
    }
}
