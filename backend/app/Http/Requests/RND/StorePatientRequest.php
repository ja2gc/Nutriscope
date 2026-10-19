<?php

namespace App\Http\Requests\RND;

use App\Http\Requests\Concerns\ValidatesContactDetails;
use App\Rules\PersonName;
use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    use ValidatesContactDetails;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->contactDetailsRules(),
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'first_name' => ['required', 'string', new PersonName],
            'last_name' => ['required', 'string', new PersonName],
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
        ];
    }
}
