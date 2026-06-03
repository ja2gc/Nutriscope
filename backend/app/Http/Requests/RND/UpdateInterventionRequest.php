<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInterventionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goal_type'            => ['nullable', 'string', 'max:255'],
            'disease_stage'        => ['nullable', 'string', 'max:255'],
            'displayed_nutrients'  => ['nullable', 'array'],
            'energy_kcal'          => ['nullable', 'numeric', 'min:0'],
            'protein_g'            => ['nullable', 'numeric', 'min:0'],
            'carbs_g'              => ['nullable', 'numeric', 'min:0'],
            'fat_g'                => ['nullable', 'numeric', 'min:0'],
            'fluid_ml'             => ['nullable', 'numeric', 'min:0'],
            'micronutrient_limits' => ['nullable', 'array'],
            'education_notes'      => ['nullable', 'string'],
            'counseling_goals'     => ['nullable', 'string'],
            'barriers'             => ['nullable', 'string'],
            'strategies'           => ['nullable', 'string'],
            'session_type'         => ['nullable', 'string', 'max:255'],
            'next_followup_date'   => ['nullable', 'date'],
        ];
    }
}
