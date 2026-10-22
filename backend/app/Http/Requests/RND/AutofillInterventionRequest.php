<?php

namespace App\Http\Requests\RND;

use App\Models\NcpRecord;
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
            'goal_type' => ['required', 'string', Rule::in([
                ...InterventionGoalCatalog::goalTypes(),
                ...($this->acceptsSavedGoalSelection() ? [$this->input('goal_type')] : []),
            ])],
            'guidance_only' => ['nullable', 'boolean'],
            'disease_stage' => ['nullable', 'string', 'max:255'],
            'fluid_ml' => ['nullable', 'numeric', 'gt:0', 'max:10000', 'required_if:fluid_mode,restricted'],
        ];
    }

    protected function acceptsSavedGoalSelection(): bool
    {
        $record = $this->route('ncpRecord');

        return $this->boolean('guidance_only') && $record instanceof NcpRecord
            && is_string($this->input('goal_type'))
            && (is_null($this->input('disease_stage')) || is_string($this->input('disease_stage')))
            && $record->interventions()->where('goal_type', $this->input('goal_type'))
                ->where('disease_stage', $this->input('disease_stage'))->exists();
    }
}
