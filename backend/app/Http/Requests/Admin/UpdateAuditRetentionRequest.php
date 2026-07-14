<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAuditRetentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => [
                'required',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_bool($value)) {
                        $fail("The {$attribute} field must be true or false.");
                    }
                },
            ],
        ];
    }
}
