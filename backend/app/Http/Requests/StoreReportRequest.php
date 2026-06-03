<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_code' => ['required', 'string', 'exists:report_templates,type'],
            'parameters'    => ['nullable', 'array'],
        ];
    }
}
