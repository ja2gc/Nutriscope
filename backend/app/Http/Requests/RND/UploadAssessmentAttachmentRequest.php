<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class UploadAssessmentAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpeg,png,jpg', 'max:10240'],
            'type' => ['nullable', 'string', 'max:50'],
        ];
    }
}
