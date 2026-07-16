<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Foundation\Http\FormRequest;

class PaginatedRequest extends FormRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return $this->paginationRules();
    }
}
