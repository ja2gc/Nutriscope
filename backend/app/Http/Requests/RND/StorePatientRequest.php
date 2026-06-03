<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'dob' => ['required', 'date'],
            'sex' => ['required', 'in:Male,Female'],
            'religion' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'physician' => ['nullable', 'string', 'max:255'],
            'admission_date' => ['required', 'date'],
            'medical_diagnosis' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:Active,Discharged,Transferred'],
            'screening_type' => ['nullable', 'string', 'in:adult,pediatric'],
            'hospital_number' => ['nullable', 'string', 'max:255'],
            'age_group_category' => ['nullable', 'string', 'max:255'],
        ];
    }
}
