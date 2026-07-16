<?php

namespace Tests\Unit;

use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Foundation\Http\FormRequest;
use Tests\TestCase;

class HasPaginationRulesTest extends TestCase
{
    public function test_defaults_to_ten_items_and_caps_requests_at_ten(): void
    {
        $request = new class extends FormRequest
        {
            use HasPaginationRules;

            public function rules(): array
            {
                return $this->paginationRules();
            }
        };

        $request->initialize([]);

        $this->assertSame(10, $request->perPage());
        $this->assertSame(['nullable', 'integer', 'min:1', 'max:10'], $request->rules()['per_page']);
        $this->assertSame(['nullable', 'integer', 'min:1'], $request->rules()['page']);
    }
}
