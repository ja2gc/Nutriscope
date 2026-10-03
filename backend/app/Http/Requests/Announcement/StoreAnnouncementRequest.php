<?php

namespace App\Http\Requests\Announcement;

use App\Rules\AnnouncementImageDataUrl;
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
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::in(['General', 'Event', 'Operational', 'Urgent', 'Memo'])],
            'attachment' => ['nullable', 'string', new AnnouncementImageDataUrl],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['string', new AnnouncementImageDataUrl],
            'visibility' => ['required', Rule::in(['FSS', 'Admin', 'All'])],
            'pinned' => ['sometimes', 'boolean'],
        ];
    }
}
