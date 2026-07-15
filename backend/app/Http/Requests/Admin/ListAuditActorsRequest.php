<?php

namespace App\Http\Requests\Admin;

use App\Models\AuditActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListAuditActorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', AuditActivity::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'selected_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
