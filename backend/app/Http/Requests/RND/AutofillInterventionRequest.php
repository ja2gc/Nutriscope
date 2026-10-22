<?php

namespace App\Http\Requests\RND;

use App\Support\InterventionGoalCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AutofillInterventionRequest extends StoreInterventionRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...StoreInterventionRequest::calculationRules(),
            'goal_type' => ['required', 'string', Rule::in(InterventionGoalCatalog::goalTypes())],
            'guidance_only' => ['nullable', 'boolean'],
            'disease_stage' => ['nullable', 'string'],
            'fluid_ml' => ['nullable', 'numeric', 'gt:0', 'max:10000', 'required_if:fluid_mode,restricted'],
        ];
    }
}
