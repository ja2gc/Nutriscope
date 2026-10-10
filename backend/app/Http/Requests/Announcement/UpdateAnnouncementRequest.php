<?php

namespace App\Http\Requests\Announcement;

use App\Rules\AnnouncementImageDataUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:5000'],
            'category' => ['sometimes', 'required', Rule::in(['General', 'Event', 'Operational', 'Urgent', 'Memo'])],
            'attachment' => ['sometimes', 'nullable', 'string', new AnnouncementImageDataUrl],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['string', new AnnouncementImageDataUrl],
            'visibility' => ['sometimes', 'required', Rule::in(['FSS', 'Admin', 'All'])],
            'pinned' => ['sometimes', 'boolean'],
        ];
    }
}
