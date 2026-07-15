<?php

namespace App\Http\Requests\RND;

use App\Http\Requests\Concerns\ValidatesPersonNameChanges;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientRequest extends FormRequest
{
    use ValidatesPersonNameChanges;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $patient = $this->route('patient');
        abort_unless($patient instanceof Patient, 404);

        return [
            ...$this->splitNameUpdateRules($patient),
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'dob' => ['sometimes', 'required', 'date'],
            'sex' => ['sometimes', 'required', 'in:Male,Female'],
            'religion' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'physician' => ['nullable', 'string', 'max:255'],
            'admission_date' => ['sometimes', 'required', 'date'],
            'medical_diagnosis' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:Active,Discharged,Transferred'],
            'screening_type' => ['nullable', 'string', 'in:adult,pediatric'],
            'hospital_number' => ['nullable', 'string', 'max:255'],
            'age_group_category' => ['nullable', 'string', 'max:255'],
        ];
    }
}
