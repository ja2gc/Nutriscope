<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\PaginatedRequest;
use Illuminate\Validation\Rule;

class ListBackupsRequest extends PaginatedRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'section' => ['sometimes', 'string', Rule::in(['available', 'in_progress', 'failed', 'recently_deleted'])],
            'category' => ['sometimes', 'string', Rule::in(['all', 'daily', 'weekly', 'monthly', 'manual', 'safety'])],
        ];
    }

    public function section(): string
    {
        return $this->string('section', 'available')->toString();
    }

    public function category(): string
    {
        return $this->string('category', 'all')->toString();
    }
}
