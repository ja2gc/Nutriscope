<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'dob'               => fake()->date('Y-m-d', '-20 years'),
            'sex'               => fake()->randomElement(['Male', 'Female']),
            'religion'          => fake()->randomElement(['Catholic', 'Christian', null]),
            'address'           => fake()->address(),
            'contact'           => fake()->phoneNumber(),
            'physician'         => 'Dr. ' . fake()->name(),
            'admission_date'    => fake()->date('Y-m-d', 'now'),
            'medical_diagnosis' => fake()->randomElement(['CKD', 'DM', 'HTN', 'CKD + DM']),
            'ward'              => fake()->randomElement(['General', 'ICU', 'Pediatric']),
            'status'            => 'Active',
            'hospital_number'   => strtoupper(fake()->bothify('HN-####??')),
            'screening_type'    => fake()->randomElement(['adult', 'pediatric']),
            'age_group_category' => fake()->randomElement(['adult', 'pediatric', 'elderly']),
        ];
    }
}
