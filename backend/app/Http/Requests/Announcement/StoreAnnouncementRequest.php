<?php

namespace App\Http\Requests\Announcement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'category' => ['required', Rule::in(['General', 'Event', 'Operational', 'Urgent'])],
            'attachment' => ['nullable', 'string'],
            'visibility' => ['required', Rule::in(['FSS', 'Admin', 'All'])],
            'pinned' => ['sometimes', 'boolean'],
        ];
    }
}
